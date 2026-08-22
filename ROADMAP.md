# HubTube Roadmap

This roadmap tracks a selected set of findings from a full-app analysis (backend architecture/performance, frontend/UX, security/ops/monetization). Each item below includes the concrete problem location, what's wrong, and a suggested direction for the fix. Check items off as they land.

---

## P0 — Fix Now

Correctness/security issues with real financial or legal exposure.

- [x] **Wallet locking is a no-op — race condition on money**
  `app/Services/WalletService.php`
  Credit/debit methods call `$user->lockForUpdate()` on an already-hydrated Eloquent *model* instance. `lockForUpdate()` isn't a Model method — Eloquent's magic `__call` proxies it to a brand-new `newQuery()->lockForUpdate()` that is never executed (no `->get()`/`->first()` follows it), so nothing is actually locked or re-fetched inside the surrounding `DB::transaction()`. Concurrent credit/debit calls on the same user (simultaneous gift sends, PPV purchases, withdrawals) can race and lose updates or allow double-spending.
  **Done:** `credit()`/`debit()` now re-fetch a locked row inside the transaction (`User::whereKey($user->id)->lockForUpdate()->first()`), mutate/save that instance, and mirror the new balance back onto the caller's `$user`. Added `tests/Feature/WalletServiceTest.php` covering credit, debit, insufficient-balance, and sequential-update correctness (5 tests passing).

- [x] **CSP is decorative, not protective**
  `app/Http/Middleware/AddSecurityHeaders.php`
  The `script-src` directive includes `'unsafe-inline' 'unsafe-eval' https: http:`, which permits essentially any script from any origin plus inline/eval — negating CSP's core XSS mitigation. Done for ad-network compatibility, per the existing code comment.
  **Done, then corrected in production:** Initially dropped both `'unsafe-eval'` and every plain `http:` origin in production. **`'unsafe-eval' broke live ad rendering** — JuicyAds' `jads.js` calls `eval()` directly, and its CSP violation was confirmed in the browser console on a live video page, so `'unsafe-eval'` was restored unconditionally (it's a real, currently-unavoidable ad-network requirement, not just defense-in-depth we could drop). The plain `http:` removal (production only; kept in non-production for `php artisan serve`/a non-TLS Reverb dev server) was not implicated in that regression and stays. A full nonce-based policy (dropping `'unsafe-inline'`/`'unsafe-eval'` for good) remains future work blocked on ad-network compatibility, noted in-code.

- [x] **ProcessVideoJob silently degrades on transcode failure**
  `app/Jobs/ProcessVideoJob.php`
  Any exception during transcoding falls back to `markAsProcessedWithOriginal()`, which marks the video "processed" while actually serving the raw unprocessed upload — no multi-quality renditions, no HLS, no watermark — with no visible admin alert. Persistent ffmpeg failures (bad codec, corrupt upload, missing binary) go unnoticed indefinitely.
  **Done:** Added a `processing_fallback_reason` column (migration `2026_08_21_000001_add_processing_fallback_reason_to_videos_table.php`) — kept separate from `failure_reason`, which is already reused for admin rejection reasons, and from `status`, which ~15 files compare directly against `'processed'` for publish/approval/listing logic. The genuine-failure catch path now records the reason there and calls `AdminLogger::error(...)`, and the Filament video list/edit views surface a "Degraded" badge and reason field whenever it's set.

---

## P1 — Backend / Performance

- [x] **StorageManager rebuilds the Wasabi disk config on every call**
  `app/Services/StorageManager.php`
  Any call touching a cloud path calls `buildWasabiDisk()`, including a `Storage::forgetDisk()`, rebuilding the entire disk configuration from scratch. A single video listing page rendering dozens of thumbnail/video URLs can trigger dozens of disk-rebuild cycles in one request.
  **Done:** `buildWasabiDisk()` now hashes the resolved settings (`key`/`secret`/`region`/`bucket`/`endpoint`/`url`) and skips the `config()` rewrite + `Storage::forgetDisk()` cycle when they're unchanged from the last build, reusing the already-configured disk instead.

- [x] **ProcessVideoJob pipeline is fully serial with limited worker capacity**
  `app/Jobs/ProcessVideoJob.php`, `config/horizon.php`
  Probe → thumbnails → sprite → watermark → multi-quality transcode → HLS remux → cloud upload all run serially inside one job in one process, with only 3 Horizon workers configured for the `video-processing` queue in production and a 3600s job timeout. A burst of uploads, or a single long video, can starve the queue for up to an hour. Shell commands run via raw `proc_open`/blocking `stream_get_contents`, so ffmpeg itself has no per-command timeout — only the outer job timeout, which can leave orphaned ffmpeg children or partial temp files when it fires.
  **Done:** `runCommand()` now uses Symfony `Process` (`Process::fromShellCommandline`) with a per-command timeout (`ffmpeg_command_timeout` setting, default 1800s) instead of raw `proc_open`, so a single hung ffmpeg invocation is killed cleanly — process tree and all — well before the job's outer 3600s timeout, and a timeout is logged distinctly. Bumped the production `video-processing` Horizon supervisor from 3 to 5 `maxProcesses` to reduce queue starvation risk, with an in-config note to tune further to the server's actual CPU core count. Splitting the pipeline into parallel chained sub-jobs was considered but deferred — it's a larger architectural change with more risk than this scoped fix.

- [x] **Unverified indexes on hot comment/like lookups**
  `database/migrations/`
  No index was confirmed on `comments.video_id` or `likes.video_id`, despite the `Video` model itself having solid composite/fulltext indexing elsewhere. These are almost certainly hot lookup paths (comment counts, like counts, listing comments per video).
  **Verified, no change needed:** Both migrations already cover this — `comments` has `['video_id', 'is_approved', 'created_at']` and `['video_id', 'is_pinned']` composite indexes, and `likes` has `['video_id', 'type']`, on top of the implicit index MySQL/InnoDB creates for the `foreignId(...)->constrained()` foreign key itself. The original finding was a hedge from not having read the migrations directly.

- [x] **Thumbnail proxy SSRF check has a DNS-rebinding TOCTOU gap**
  `app/Http/Controllers/ThumbnailProxyController.php`
  The domain allowlist / private-IP-blocking check (`filter_var(..., FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)`) resolves the hostname once via `gethostbyname`, then the actual fetch (`Http::get()`) resolves the same hostname independently. A DNS-rebinding attacker could pass the validation check and then have the real fetch resolve to an internal IP.
  **Done:** Split `isInternalHost()` into `resolveHostIp()` (resolve once) + `isInternalIp()` (validate the resolved IP), and the outbound fetch now pins the connection to that exact validated IP via `CURLOPT_RESOLVE` instead of letting curl re-resolve the hostname independently — closing the TOCTOU window.

---

## P1 — Frontend / UX

- [x] **Videos/Show.vue is a 940-line God component**
  `resources/js/Pages/Videos/Show.vue`
  Mixes video player wiring, ad orchestration, like/dislike, report modal, share modal, and playlist-save logic in one file — a maintainability problem and a large reactive re-render surface for any state change.
  **Done:** Extracted the report modal into a shared `ReportModal` component (see next item) and moved ad-readiness detection off polling and onto an event emitted by `VideoPlayer` (see "Ad-ready detection" below), shrinking Show.vue's own state/logic surface. Playlist-rail and like/dislike/save-to-playlist logic were left in place — they're already fairly cohesive single-purpose blocks within the file rather than genuinely tangled cross-cutting concerns, so further splitting was judged not worth the added indirection right now.

- [x] **Report modal duplicated between Show.vue and Shorts/Index.vue**
  `resources/js/Pages/Videos/Show.vue`, `resources/js/Pages/Shorts/Index.vue`
  The report modal markup/logic is copy-pasted near-verbatim in both places.
  **Done:** Extracted a shared `resources/js/Components/ReportModal.vue` (self-contained, `v-model` + `reportable-id`/`reportable-type` props, matching the existing `ShareModal.vue` pattern) and switched both `Show.vue` and `Shorts/Index.vue` to use it, removing ~90 lines of duplicated markup/state/submit logic from each.

- [x] **Ad-ready detection polls instead of listening for an event**
  `resources/js/Pages/Videos/Show.vue` (`waitForVideoAndSetupAds`)
  Polls every 200ms for up to 10s to detect the Vidstack player element being ready, instead of using a ready/can-play event — fragile and wastes cycles.
  **Done:** `VideoPlayer.vue` now emits a `ready` event off Vidstack's own `can-play` event and exposes `getPlayer()` via `defineExpose`. `Show.vue` binds a template ref to `VideoPlayer` and listens for `@ready="onPlayerReady"` instead of polling `querySelector('media-player')` in a `setInterval`/`setTimeout` chain — no more DOM polling, no 10s safety timeout needed.

- [x] **No manual Vite chunk-splitting strategy**
  `vite.config.js`
  Heavy dependencies (hls.js, Vidstack, Sentry, lucide-vue-next) are bundled per Vite's default heuristics with no `rollupOptions.output.manualChunks` strategy. hls.js is also eagerly imported at the top of `VideoPlayer.vue` rather than dynamically imported, adding it to the bundle even for MP4-only sources.
  **Done, with one part reverted after a production regression:** Added `manualChunks` splitting Vidstack (+ its own small deps `@floating-ui/dom`/`lit-html`/`media-captions`, needed to avoid a circular-chunk warning), hls.js, Sentry, and lucide-vue-next into their own chunks — this part is safe and stays (hls.js still ships as its own chunk via `manualChunks` even with a static import, so pages that don't reference it aren't forced to load it eagerly at the entry level). The lazy-loader half of this fix — replacing `import HLS from 'hls.js'` with Vidstack's `provider.library = () => import('hls.js')...` loader form — **broke HLS video playback in production**: Vidstack fell back to a native `<video type="application/x-mpegurl">` source, which only Safari supports, so every video failed to load ("All candidate resources failed to load") on real users' pages. Reverted to the synchronous `import HLS from 'hls.js'` + `provider.library = HLS` assignment, confirmed working before, and left a comment in the code warning against retrying the lazy form without also verifying actual HLS playback (not just the build output) first.

- [x] **Inertia's native partial-reload/prefetch API is unused**
  Pages hand-roll `fetch`/infinite-scroll (e.g. `Home.vue`'s `loadMore`) instead of using Inertia's built-in `only` partial-reload option or `router.prefetch`.
  **Done:** `Home.vue`'s `loadMore` now calls `router.reload({ only: ['latestVideos'], data: { page } })` against the same `HomeController::index` route instead of hand-rolling a `fetch` with manual CSRF-header wiring against the separate `/api/videos/load-more` JSON endpoint (left in place, unused by this page now, in case other consumers rely on it).

---

## P2 — Medium-Term

- [x] **useVirtualList.js exists but is unused**
  `resources/js/Composables/useVirtualList.js`
  Wraps VueUse's virtual list utility, but the home video grid, search results, and shorts feed all render full arrays via `v-for` with pagination/infinite-scroll rather than DOM virtualization.
  **Verified — not actually dead code, and Home.vue's grid was deliberately left alone:** `useVirtualList.js` is already consumed by `useVirtualGrid.js` (a row-bucketing wrapper that chunks a flat item list into rows of N-per-viewport-width and virtualizes by row), which `Search.vue` already uses for its video-results grid. Extending the same treatment to `Home.vue`'s infinite-scroll grid was evaluated and deliberately skipped: that grid interleaves ad slots and sponsored cards at index-based intervals with irregular column-spans (`col-span-2` on mobile), which breaks the uniform-N-items-per-row assumption `useVirtualGrid` relies on. Forcing it through without a way to visually verify the ad layout in this environment risked silently breaking ad placement on a revenue-facing page, so it was left as-is rather than shipped half-verified.

- [x] **Home.vue has a dead skeleton-loader code path**
  `resources/js/Pages/Home.vue`
  `isInitialLoad` is hardcoded to `false` (Inertia already hydrates data server-side), making the entire skeleton-loader branch unreachable dead code.
  **Done:** Removed the dead `isInitialLoad` ref, its `watch`/`onMounted` gating, and every `v-if="isInitialLoad"` / `VideoCardSkeleton` branch in the template (featured, latest, and popular sections) — the real (`v-else`) branches are now unconditional.

- [x] **Repeated Setting::get() calls in hot paths**
  e.g. `app/Services/VideoService.php` (`awardAutoApprovePoints`/`shouldAutoApprove`), `app/Services/StorageManager.php`
  Several call sites fetch 2-4+ individual settings per invocation instead of using the batched `Setting::getAll()` pattern already used elsewhere in the codebase (e.g. `ProcessVideoJob`), adding up under bulk operations (e.g. bulk-approve loops, thumbnail-heavy listing pages).
  **Done:** `VideoService::shouldAutoApprove`/`awardAutoApprovePoints` now call `Setting::getAll()` once each instead of 2–3 individual `Setting::get()` calls. `StorageManager` gained a request-scoped memoized `static::setting()` helper (backed by one `Setting::getAll()` call, reused for the life of the request) and all ~13 individual `Setting::get()` call sites in the class were switched to it — `Setting::getDecrypted()` calls (encrypted Wasabi credentials) were left untouched since those aren't part of the plain-settings cache blob.

- [x] **No DMCA/takedown request workflow**
  Not found anywhere in `app/` — no model, controller, or admin resource for intake, review, or audit of takedown requests.
  **Done:** Added a full takedown-request flow: a `dmca_requests` migration/model (complainant details, copyrighted-work description, infringing URLs, required good-faith/perjury statements + typed signature, status `pending`/`actioned`/`rejected`, admin notes, resolver/audit fields); a public `DmcaController` + `/dmca-request` route (throttled like the existing contact form) that best-effort-matches the submitted URL to a `Video` by slug; a Vue intake page (`Dmca.vue`, linked from the footer); and a `DmcaRequestResource` Filament admin resource (list/view, status filter, navigation badge for pending count, and "Action & Remove Video" / "Mark Actioned" / "Reject" actions logged via the same audit pattern as `ReportResource`). Covered by `tests/Feature/DmcaRequestTest.php` (3 tests: form loads, valid submission links the video and persists, missing legal statements are rejected) — all passing. Deliberately did not wire an admin-notification email (would require registering a new FinMail `EmailTemplate` record via the mail-template system, which risks a runtime "template not found" if seeded incorrectly) or a complainant-facing counter-notice flow — the Filament navigation badge plus `AdminLogger` entry give admins visibility without that added risk.
