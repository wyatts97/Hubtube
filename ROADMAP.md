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
  **Done:** Dropped `'unsafe-eval'` and every plain `http:` origin across all directives in production (kept only in non-production so `php artisan serve` / a non-TLS Reverb dev server keep working) — no ad format in use needs eval or cleartext, so this closes off cleartext MITM/downgrade injection while preserving `https:` + `'unsafe-inline'` for the admin-configurable VideoAd HTML/VAST/VPAID creatives, which can point at any HTTPS origin. A full nonce-based policy (dropping `'unsafe-inline'` too) remains future work, noted in-code.

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

- [ ] **Videos/Show.vue is a 940-line God component**
  `resources/js/Pages/Videos/Show.vue`
  Mixes video player wiring, ad orchestration, like/dislike, report modal, share modal, and playlist-save logic in one file — a maintainability problem and a large reactive re-render surface for any state change.
  **Direction:** Extract cohesive pieces into subcomponents (e.g. `ReportModal`, `ShareModal`, `PlaylistSaveMenu`, an ad-orchestration composable) so the page component becomes primarily composition/layout.

- [ ] **Report modal duplicated between Show.vue and Shorts/Index.vue**
  `resources/js/Pages/Videos/Show.vue`, `resources/js/Pages/Shorts/Index.vue`
  The report modal markup/logic is copy-pasted near-verbatim in both places.
  **Direction:** Extract a single shared `ReportModal` component (this overlaps directly with the extraction above) and use it from both pages.

- [ ] **Ad-ready detection polls instead of listening for an event**
  `resources/js/Pages/Videos/Show.vue` (`waitForVideoAndSetupAds`)
  Polls every 200ms for up to 10s to detect the Vidstack player element being ready, instead of using a ready/can-play event — fragile and wastes cycles.
  **Direction:** Use Vidstack's `ready`/`can-play` event (or equivalent lifecycle hook) to trigger ad setup instead of a polling `setInterval`.

- [ ] **No manual Vite chunk-splitting strategy**
  `vite.config.js`
  Heavy dependencies (hls.js, Vidstack, Sentry, lucide-vue-next) are bundled per Vite's default heuristics with no `rollupOptions.output.manualChunks` strategy. hls.js is also eagerly imported at the top of `VideoPlayer.vue` rather than dynamically imported, adding it to the bundle even for MP4-only sources.
  **Direction:** Add a manual chunking strategy separating vendor/video-player/admin bundles, and dynamically `import('hls.js')` only when an HLS source is actually being played.

- [ ] **Inertia's native partial-reload/prefetch API is unused**
  Pages hand-roll `fetch`/infinite-scroll (e.g. `Home.vue`'s `loadMore`) instead of using Inertia's built-in `only` partial-reload option or `router.prefetch`.
  **Direction:** Where a page re-requests a subset of props (paginated lists, related data), switch to Inertia's native `router.reload({ only: [...] })` / prefetch APIs to reduce payload size and take advantage of Inertia's built-in request de-duplication.

---

## P2 — Medium-Term

- [ ] **useVirtualList.js exists but is unused**
  `resources/js/Composables/useVirtualList.js`
  Wraps VueUse's virtual list utility, but the home video grid, search results, and shorts feed all render full arrays via `v-for` with pagination/infinite-scroll rather than DOM virtualization.
  **Direction:** Apply `useVirtualList` to the home grid and/or search results once list lengths grow large enough that DOM node count becomes a real rendering cost.

- [ ] **Home.vue has a dead skeleton-loader code path**
  `resources/js/Pages/Home.vue`
  `isInitialLoad` is hardcoded to `false` (Inertia already hydrates data server-side), making the entire skeleton-loader branch unreachable dead code.
  **Direction:** Either wire `isInitialLoad` to a real loading condition (e.g. client-side re-fetch/filter changes) or remove the unused branch and `VideoCardSkeleton` usage tied to it.

- [ ] **Repeated Setting::get() calls in hot paths**
  e.g. `app/Services/VideoService.php` (`awardAutoApprovePoints`/`shouldAutoApprove`), `app/Services/StorageManager.php`
  Several call sites fetch 2-4+ individual settings per invocation instead of using the batched `Setting::getAll()` pattern already used elsewhere in the codebase (e.g. `ProcessVideoJob`), adding up under bulk operations (e.g. bulk-approve loops, thumbnail-heavy listing pages).
  **Direction:** Replace repeated individual `Setting::get()` calls in hot/loop-prone paths with a single `Setting::getAll()` batch fetch up front.

- [ ] **No DMCA/takedown request workflow**
  Not found anywhere in `app/` — no model, controller, or admin resource for intake, review, or audit of takedown requests.
  **Direction:** Add a takedown request flow: a public intake form/endpoint, a Filament admin resource for reviewing/actioning requests (with states like pending/actioned/rejected), and an audit trail tied to the affected video/user, consistent with the existing `AdminLogger` pattern used for other moderation actions.
