# HubTube — Release Notes

What changed in past releases, and what each one needs when you deploy it.
The routine update steps are in [DEPLOY.md](./DEPLOY.md#deploying-updates).

## Security, performance and backups review (September 2026)

Nothing to configure for most sites, but check these when deploying:

- **Backups were not using `config/backup.php`.** The file was written in a
  layout the backup package does not read, so the nightly backup fell back to
  the package defaults: it zipped the whole media library, and every earlier
  backup, into `storage/app/{APP_NAME}/`. Backups now go to
  `storage/app/backups/` (the folder the Backups page lists) and exclude media
  as intended. **Delete the old `storage/app/HubTube/` folder** (named after
  `APP_NAME`) once you have checked the new backups — it may be large.
- Set `BACKUP_ARCHIVE_PASSWORD`, and consider `BACKUP_DISKS=local,wasabi` for
  an off-site copy. Media is still not backed up; see DEPLOY.md → Backups.
- **Stripe webhooks are refused until a webhook secret is set** in Admin →
  Payment Settings. Without one, anyone could post a fake "subscription
  active" event.
- `X-Forwarded-*` headers are trusted only from loopback and Cloudflare. If
  another proxy or CDN sits in front of the site, set `TRUSTED_PROXIES`.
- The seeded admin (`db:seed` on a production install) gets a random password,
  printed once, instead of `password`.
- Run `php artisan horizon:terminate` after deploying: a new job
  (background backups) runs on the `media-compress` queue.

## Roles, bans and embedding

- **Roles** (Admin → System → Roles) narrow what an administrator can reach. `php artisan hubtube:sync-roles` seeds `moderator`, `editor`, `uploader` and `trusted_uploader`; assign them per user in Admin → Users. An administrator with **no** roles keeps the access they had before roles existed, so nobody is locked out by deploying this. Super-admins always pass every check.
- **Bans** (Admin → Users → Ban / Suspend) stop an account signing in and end its open sessions, in the panel as well as the site. Suspensions lift by themselves.
- **Registration controls** live in Admin → Settings → Site → Users: the registration switch is now enforced, and there are blocklists for email domains and IPs plus a bundled list of disposable email providers (`resources/data/disposable-email-domains.txt`).
- **Embedding** is Admin → Settings → Site → Videos → "Allow Embedding on Other Sites". It serves `/embed/{slug}` for iframes, answers oEmbed at `/api/oembed`, and puts an embed code in the share dialog. Only that route may be framed cross-origin; everything else stays same-origin. Private, draft and unapproved videos are never embeddable.
- **Drafts** are private to their uploader and stay out of every listing. Anything queued for scheduled publishing is a draft until its time arrives, which closes the old gap where a scheduled video was already openable by URL.

## Comments, playlists and the Creator Studio

Nothing here needs configuring to work, but three things are worth knowing after this release.

- **Comment moderation** (Admin → Settings → Site → Moderation) gained a **Blocked Words** list and a **Maximum Links Per Comment** limit (default 2). A comment that trips either is held for approval rather than refused, so the author is not handed the word list; it stays visible to them and to nobody else until a moderator clears it in Admin → Comments. The existing **Enable Comments** switch is now actually read — turning it off hides the comment section and refuses new comments, which it previously did not.
- **`videos.comments_count` is now maintained by the model**, so approving or deleting a comment from the admin panel moves the counter too. Counts that drifted while that was handled per-controller will correct themselves the next time a comment on that video is added, approved or removed. To recount everything at once, `Comment::where('is_approved', true)->selectRaw('video_id, COUNT(*) c')->groupBy('video_id')` compared against `videos.comments_count` will show the difference.
- **Watch Later** is created for each account the first time it is needed (opening `/playlists`, or the Watch Later button on a video), so no backfill is required. It cannot be deleted or renamed and starts private.
- **Creator Studio** is at `/studio/videos` for every signed-in account — a filterable list of the creator's own videos with bulk privacy, category, tag and delete actions, plus per-video analytics at `/studio/videos/{id}/analytics`. Analytics read `video_views` and `watch_history`, which are already being written; the daily figures only reach as far back as `HUBTUBE_VIEW_LOG_RETENTION_DAYS` (90 by default), and watch time covers signed-in viewers only.

This release adds two migrations: `edited_at` on `comments` (with an index for paginated replies) and a `video_id` index on `watch_history` for the analytics aggregates. Both are covered by the `php artisan migrate --force` above.

## Media Library

The admin Media Library used to read `storage/app/public` live on every render: it listed the directory, then stat'd, thumbnailed, ffprobed and ran two reference queries for **every file in the folder**, and only then sliced down to one page of fifty. A folder of a few thousand files took tens of seconds to open, and clicking a single file to see its details paid the whole cost again.

It now reads an index (`media_files`, `media_folders`), so opening a folder is one query with a `LIMIT` regardless of how many files it holds.

- **Run `php artisan media:index --full` once after migrating.** Until you do, folders show a "this folder hasn't been indexed yet" state with a Refresh button rather than pretending to be empty. The command writes nothing to the filesystem and is safe to re-run.
- The scheduler keeps it current: an incremental pass every ten minutes (it only re-reads directories whose mtime has moved), plus `--full --prune` weekly at 03:40. **This needs the Laravel scheduler to be running** — it already is, for scheduled publishing and translations.
- Files written outside the app — encoder renditions, imports, anything done over SSH — are picked up by that pass. If you have just dropped files in by hand and want them immediately, use **Refresh this folder** in the page's ↻ menu, or run `php artisan media:index --path=media/whatever`.
- `thumbnails` is no longer a browsable path: it only ever held the file manager's own generated thumbnail cache, sharded across 256 subdirectories, and browsing it made the folder tree walk that cache on every render. The new `media_library.excluded_paths` config keeps it, `temp` and `livewire-tmp` out of both the tree and the index.
- **Two bugs worth knowing were fixed here.** Thumbnails were being regenerated — a full GD decode, resize and WebP encode — for every image on the page on *every* render for five minutes at a time, because the cache stored the answer from before generation and never corrected it. And a soft-deleted video's files were deletable from the library, because the reference lookup did not include trashed videos; deleting them silently broke the 30-day restore window that `videos:prune-deleted` provides.

**Thumbnails are now generated by a queue worker**, not while the page renders.

- **There is a new Horizon supervisor, `media-thumbnails`.** Run `php artisan horizon:terminate` after deploying so the workers restart and pick it up — without that, thumbnail jobs queue up and nothing drains them. It is `nice 10` with 2 processes in production, so it always yields to video encoding.
- A tile whose thumbnail has not been made yet shows the file-type icon on a pulsing placeholder, and the grid polls every 5s **only while** that folder has one outstanding — the poll switches itself off once everything is settled.
- Formats GD cannot decode (`bmp`, `ico`, `tiff`, `heic`) are recorded as `unsupported` and never attempted again; an SVG is served as itself. Previously each of those threw inside the generator and wrote a log line **per file per render**.
- A video with no `ffmpeg` on the box is `unavailable` rather than `failed`, so it retries by itself once one is installed. A genuinely corrupt file is `failed` and is only retried by `php artisan media:thumbnails --retry-failed`, or by "Regenerate thumbnail" in the details panel.
- `media:thumbnails --limit=0 --prune` (weekly, in the scheduler) deletes generated thumbnails whose source file is gone. Nothing used to remove them, so the cache only ever grew.
- Video durations and image dimensions are now read once by that job and stored, replacing an `ffprobe` subprocess per video per page render.

**Media now indexes itself as it is written.** The scheduled pass is the safety net, not the mechanism.

- A finished video indexes its own `videos/{slug}` folder, image uploads index their `images/{ulid}` folder, ad-creative HLS output indexes itself, and avatar and banner uploads index the file they just wrote. These all go through `IndexMediaDirectoryJob` on the `media-thumbnails` queue, so they never delay encoding.
- "In use" flags are maintained by the `Video` and `Image` models themselves, so pointing a video at a different file releases the old one immediately. A **soft-deleted** video keeps its files reserved (it can still be restored); a force delete releases them.
- The page's ↻ menu has **Rescan whole library** for a full background rebuild, and **Refresh this folder** for something you just dropped in over SSH.
- Acting on a file that has since vanished from disk drops its index row and says so, instead of reporting "file not found" and leaving the row in the listing.
- **`allowed_paths` changed.** `channel-covers` was listed but appears nowhere else in the codebase — nothing has ever written to it. Channel banners go to `banners/{user}`, which was missing, so **channel banners were never visible in the Media Library**. That entry is now correct. If you have anything under `storage/app/public/channel-covers` from an older release, it will no longer be browsable; move it under `media/` if you still want it.

### The explorer layout

The page is laid out like Windows File Explorer: an address bar with Back / Up and clickable breadcrumbs, a command bar, the folder tree on the left, the folder's contents in the middle with a status bar, and a details pane on the right. It replaced a page that had three view modes, a three-way search scope, a storage summary strip and seven hand-rolled dialogs.

- **Two layouts:** Details (a sortable table — the default) and Icons (thumbnails). Subfolders are listed above the files, with their recursive size. The choice is remembered per admin.
- **Filtering searches below you, like Explorer.** Typing a search, picking a type or a minimum size, or ticking *Include subfolders* lists matching files in the current folder **and everything under it**. The tree's **All files** node spans every root at once.
- **Finding what is eating the disk:** click *All files*, set the size filter to *1 GB or more*, and click the **Size** column (it sorts biggest-first). There is no separate report view — the storage summary strip and the flat "all files" mode are gone, because these controls answer the same question in the real browser, with the real actions.
- **Browsing state is in the URL** (`?path=…&q=…&type=…&min=1gb&deep=1&sort=size&dir=desc&view=icons`), so a filtered view is a link you can send, and browser Back walks back up the folders.
- **Selection works as in any file manager:** click selects (and opens the details pane), Ctrl/Cmd-click toggles, Shift-click extends, and the header checkbox selects the page. The status bar shows how many are selected and their total size, with Compress, Move, Rename and Delete.
- **Rename, Move, Delete and New folder are Filament actions.** Every one re-validates the paths the browser sent. Renaming a file or folder updates every Video and Image record pointing at or inside it; `videos/{slug}` and `images/{ulid}` folders are refused, because those records find their directory by convention. A move never overwrites — a name collision gets a `-1` suffix. Anything still used by a record is skipped by Delete, with the reason.
- **Upload** from the command bar, or drop files onto the listing. An extension allowlist (`media_library.allowed_upload_extensions`) is enforced server-side — `storage/app/public` is served directly by nginx.
- The ↻ menu has **Refresh this folder** (synchronous) and **Rescan whole library** (queued; you are notified when it finishes).
- One page shows 100 files. The page-size picker is gone, and so is the `media_library.per_page` config key.

## One stored copy per rendition

Every video used to be stored about three times. Measured on a live library of 2,218 videos:

| | Size | Files |
|---|---|---|
| Originals | 66.2 GB | 2,218 |
| HLS segments | 21.7 GB | 19,280 |
| Rendition MP4s | 21.0 GB | 1,455 |
| Artwork | 1.0 GB | 14,103 |
| **Total** | **111 GB** | |

HLS matching the renditions almost exactly is the giveaway: `HlsPackager` remuxes each `processed/{quality}.mp4` with `-c copy`, so the segments hold the same bytes again. Meanwhile the player is handed the HLS manifest whenever one exists, so the progressive MP4 ladder was the copy nothing streamed from.

**What happens now:**

- **Renditions are packaged as one file each.** `-hls_flags single_file` writes `hls/{quality}/stream.ts` with the playlist addressing it by `#EXT-X-BYTERANGE`. Same bytes, same directory layout, same `master.m3u8` — but one file per rendition instead of a dozen-plus, which is ~19,000 files gone from the disk and from the media index. Videos packaged the old way keep playing untouched.
- **The rendition MP4 is deleted once its stream is verified.** `FinalizeRenditionJob` only deletes after `HlsPackager::verifyPackaged()` confirms the playlist exists, the segment data is there, and ffprobe reads the *playlist* at the same duration as the MP4. If any of that fails, both copies are kept and a warning is logged — there are no media backups, so an exit code is not evidence.
- **The upload is compressed and swapped in.** Once encoding finishes, `CompleteVideoProcessingJob` queues the upload for re-compression (AV1, or H.265 where AV1 is unavailable) and `MediaCompressService::replaceOriginal()` swaps it in after the same verification the Media Library button uses. A master already compressed is skipped, so re-runs never re-encode it.
- Downloads and the progressive fallback need no change: `quality_urls` and `bestDownloadPath()` already test each file and fall back to the master.
- With `generate_hls` off, nothing is deleted — the MP4 ladder is then the only playable copy.

**Backfilling the existing library.** Both commands are dry runs until `--apply`, both log to the admin log, and neither deletes anything it has not verified:

```
php artisan videos:repack-hls                 # report what would be reclaimed
php artisan videos:repack-hls --apply --limit=5
php artisan videos:encode-backlog             # report imported videos with no ladder
php artisan videos:encode-backlog --apply --limit=10
php artisan videos:compress-originals         # report, biggest uploads first
php artisan videos:compress-originals --apply --limit=25
```

**Run them in that order**, because they depend on each other. An audit of the live library found 2,231 video directories in three states:

| | Dirs | Uploads |
|---|---|---|
| Upload + renditions + HLS (processed) | 533 | 19.7 GB |
| **Upload only — imported, never encoded** | **1,684** | **46.5 GB** |
| Renditions + HLS but no upload | 12 | — |

`repack-hls` only touches `processed/{quality}.mp4` for qualities with a completed `video_encodings` row, so imported videos are invisible to it — it cannot delete a video that has no ladder. It also skips any video whose upload is missing, because there the renditions are the only progressive copy rather than a duplicate.

`encode-backlog` is for those 1,684: it hands them to the normal pipeline, which builds the ladder, packages HLS and then compresses the upload. Expect roughly break-even on disk for these — the compressed master saves about as much as the new ladder costs — the point is that they gain adaptive streaming. It refuses to run while a watermark is configured, because these videos have never been through this pipeline and some already carry the old site's burnt-in watermark; pass `--watermark` if drawing it on is what you want.

`compress-originals` **skips any video whose upload is its only playable copy**. Without an HLS ladder the player streams the upload itself, so re-encoding it to AV1 would leave viewers without that decoder unable to play it at all. Those videos have to go through `encode-backlog` first.

Realistic end state for the measured library: 111 GB → roughly 70 GB, with every video streaming adaptively. The 21 GB from `repack-hls` and ~12 GB from compressing the 533 processed uploads are the certain parts.

Run the HLS repack first and check a few of those videos play, then let it run out. `repack-hls` repackages each rendition, verifies it, then removes the MP4 — about 21 GB on the measured library. `compress-originals` queues uploads onto `media-compress` (one niced worker), replacing each only after it verifies smaller and the same length — roughly another 35–40 GB. Afterwards run `php artisan media:index --full --prune` so the library index matches the disk.

A rendition that fails verification is left with both copies; re-run the command after looking at it.

## Compressing videos

Select one or more videos and choose **Compress…** — from the status bar, the film-strip button on a row, or the details pane. Pick a codec and a quality level:

| Codec | Output | Plays in | Needs in ffmpeg |
|---|---|---|---|
| **AV1** | `name.av1.mp4` | every modern browser; the smallest files; the slowest to encode | `libaom-av1` or `libsvtav1` (SVT-AV1 is used automatically when libaom is missing, and is much faster) |
| **VP9** | `name.vp9.webm` | every modern browser | `libvpx-vp9` |
| **H.265 / HEVC** | `name.h265.mp4` | most devices, but **not every desktop browser** (Firefox, and Chrome without hardware support) | `libx265` |

Codecs your ffmpeg build lacks are greyed out in the dialog. Check with `ffmpeg -hide_banner -encoders | grep -E 'x265|vpx-vp9|aom|svtav1'`.

Every encode forces a keyframe every 5 seconds. Without that ffmpeg uses the encoder's default, and libaom's is effectively one keyframe at the start — a 60-second test clip came out with a single keyframe, which made seeking stall the player. Anything compressed before this fix needs compressing again to become seekable.

**An encode never overwrites anything.** It writes a new file beside the source and verifies it before keeping it: at least 10 KB, a real video stream, the same length to within 250 ms, and smaller than the source. Anything else is discarded, with the reason. The row shows a live *Compressing 42%* badge, and you get a notification with the saving when it finishes. After that, keep whichever file you want and delete the other.

**A video's original upload** cannot simply be deleted — `video_path` points at it. So when you select one, the details pane lists its compressed copies with a **Replace original** button. That repoints `video_path` (and `videos.size`) to the copy and then deletes the old upload. That order means a failure can never leave the video without a file. **It is the one irreversible step, and there are no backups of media files.** Before you use it:

- The original upload is the player's fallback source, the *Original* entry in the quality menu, and the Pro download's fallback. **The HLS stream and the renditions are not touched** — HLS is what the player actually streams, so nothing here offers to shrink or remove it.
- Only an **MP4** copy (AV1 or H.265) can replace an original, because the player declares every progressive source as `video/mp4`. **Prefer AV1**, because of the H.265 browser caveat above.
- Any later re-encode of that video starts from the compressed copy.
- Replace is refused for a video that is still encoding, embedded, stored off-box, soft-deleted, or waiting for its watermark to be drawn.

**Deploying this:**

- **There is a Horizon supervisor, `media-compress`** — `maxProcesses: 1`, `nice 15`, `timeout 7200` — so a bulk compress runs one file at a time and always yields CPU to live uploads. It replaced the `storage-reclaim` supervisor. **Until this release, compress jobs queued onto `media-compress` with no supervisor for it, so they never ran.** Run `php artisan horizon:terminate` after deploying and confirm the supervisor appears. `retry_after` on the redis and database queue connections stays at 7500, so a merely-slow encode is never handed to a second worker.
- `php artisan migrate --force` drops the old `storage_reclaims` table and its four `reclaim_*` settings. The Storage Reclaim review page and `php artisan storage:reclaim` are gone. A reclaim that was still awaiting review leaves its `{stem}_r{id}.mp4` beside the original, as an ordinary file to keep or delete.
- `npm run build` for the page's CSS, then `php artisan optimize:clear && php artisan filament:optimize`.

**Also fixed here: the Pro download served the wrong file.** `download()` sorted the quality labels as *strings*, which orders them `original, 720p, 480p, 360p, 1080p` — so a 1080p+720p ladder handed out **720p**, and the `original` branch never broke out of the loop, so a video with no rendition on disk served the raw upload. It now picks the highest rendition numerically with the original as a genuine fallback (`Video::bestDownloadPath()`).

