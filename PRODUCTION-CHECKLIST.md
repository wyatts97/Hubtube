# HubTube — Production Readiness Checklist

> Full audit performed 2026-02-13. Covers security, bugs, missing features, dead code, and infrastructure.
> Mark items `[x]` as they are completed.

---

## 🔴 CRITICAL — Must Fix Before Launch

### 1. Environment Configuration
- [ ] **Generate `APP_KEY`** — Run `php artisan key:generate`. Without this, sessions, encryption, and signed URLs will not work.
- [ ] **Set `APP_ENV=production`** — Currently `local`. Must be `production` for proper error handling, caching, and security.
- [ ] **Set `APP_DEBUG=false`** — Currently `true`. Debug mode leaks stack traces, env vars, and DB credentials to users.
- [ ] **Set correct `APP_URL`** — Currently `http://localhost`. Must match the actual domain (e.g. `https://yourdomain.com`). Affects emails, SEO canonical URLs, asset URLs, and CORS.
- [ ] **Set `DB_PASSWORD`** — Currently `password`. Use a strong, unique password in production.
- [ ] **Set `SESSION_SECURE_COOKIE=true`** — Currently `false`. Session cookies must only be sent over HTTPS to prevent session hijacking.

### 2. WP Imported User: "Change Password" Broken for Non-Bcrypt Hashes
- [x] **`current_password` validation fails for WP users** — `SettingsController::updatePassword()` uses Laravel's `current_password` validation rule, which internally calls `Hash::check()`. For users with `$wp$2y$` or `$P$B` hashes, this throws a `RuntimeException` (same issue we fixed in login). Either: (a) wrap in try-catch and verify via `WordPressPasswordHasher`. Same issue affects `deleteAccount()` which also uses `current_password`. DO NOT FORCE FORGOT PASSWORD FLOW ON IMPORTED WP USERS.

### 3. Installer Routes Not Locked Down Post-Install
- [x] **Verify `installed` middleware blocks install routes** — The `CheckInstalled` middleware with `block` mode should prevent access to `/install/*` after `storage/installed` exists. Verify this works and that the installer cannot be re-run to overwrite the admin account or DB credentials. Add "You should now delete the /install directory from your server" message on install success page after installation success.

### 4. `VITE_PUBLIC_BUILDER_KEY` Leaked in `.env`
- [x] **Remove unused Builder.io key** — `.env` contains `VITE_PUBLIC_BUILDER_KEY=1ecd0197e63a4365a880d4476bcd25dc`. No code references it. This is a leaked API key committed to the repo. Remove it from `.env` and `.env.example`.

---

## 🟡 HIGH — Should Fix Before Launch

### 8. Meilisearch Not Configured
- [ ] **Switch `SCOUT_DRIVER=meilisearch`** — Currently `database` which uses `LIKE %query%` with no relevance ranking, typo tolerance, or faceted search. Meilisearch host/key are in `.env` but the driver isn't switched. For production, install Meilisearch and switch the driver.

### 12. Content Security Policy Tightening
- [ ] **`unsafe-inline` and `unsafe-eval` in CSP** — `script-src` allows `'unsafe-inline' 'unsafe-eval'` which significantly weakens XSS protection. Audit inline scripts and migrate to nonces or hashes. This is an ongoing effort but should be tracked.

---

## 🟠 MEDIUM-HIGH — Should Fix Soon After Launch

### 13. Dead Code Cleanup
- [ ] **Remove `EmbeddedVideos/` Vue pages** — `Index.vue`, `Show.vue`, `Featured.vue` in `resources/js/Pages/EmbeddedVideos/` are orphaned (no routes point to them). Embedded videos now use the unified `videos` table.
- [ ] **Remove `two_factor_enabled` / `two_factor_secret`** — User model has these fields in fillable/casts/hidden but no controller, middleware, or UI implements 2FA. Remove the fields to avoid confusion.

### 15. 2FA Fields Unused
- [ ] **`two_factor_enabled` / `two_factor_secret` exist but are dead** — User model has these fields in fillable/casts/hidden but no controller, middleware, or UI implements 2FA. Either implement TOTP-based 2FA or remove the fields to avoid confusion.

---

## 🟢 MEDIUM — Polish Before Launch

### 16. Dead Code Cleanup
- [ ] **Remove `EmbeddedVideos/` Vue pages** — `Index.vue`, `Show.vue`, `Featured.vue` in `resources/js/Pages/EmbeddedVideos/` are orphaned (no routes point to them). Embedded videos now use the unified `videos` table.
- [ ] **Remove `FeatureTestOutput` from repo root** — 69KB test output file.
- [ ] **Remove `PANEL-DEPLOY.md` if outdated** — 25KB deployment guide; verify it's still accurate or remove.

### 17. Account Deletion Doesn't Clean Cloud Storage
- [x] **`deleteAccount()` only deletes from local `public` disk** — If user's videos were offloaded to Wasabi/B2/S3, the cloud files are not deleted. Should use `StorageManager` to delete from the correct disk based on each video's `storage_disk` field.

### 18. Wallet Withdrawal Has No Admin Approval UI
- [x] **WithdrawalRequests created but no admin page to process them** — `processWithdraw()` creates a `WithdrawalRequest` and debits the user's wallet, but there's no Filament admin page for reviewing/approving/rejecting withdrawals. Add a `WithdrawalRequestResource` or admin page.

### 19. Password Reset for WP Users
- [x] **Password reset works but doesn't log migration** — When a WP user resets their password via "Forgot Password", `PasswordResetController::reset()` sets a bcrypt hash via `Hash::make()`. This works correctly but doesn't log the WP→bcrypt migration like the login flow does. Minor, but good for tracking.

### 20. Skeleton Loading Components
- [ ] **Add skeleton loaders for paginated views** — `VideoCardSkeleton` exists but channel pages, playlists, search results, and history have no skeleton loading states.

### 21. Shorts Not Fully Implemented
- [ ] **`is_short` never set during upload** — `VideoService::create()` doesn't check for `?type=short` query param. Shorts page shows a grid but there's no TikTok-style vertical viewer. Upload flow doesn't distinguish shorts from regular videos.

---

## 🔵 LOW — Nice to Have

### 22. Error Monitoring
- [ ] **Set up Sentry** — `config/sentry.php` exists and `bootstrap/app.php` has Sentry integration hooks, but no `SENTRY_DSN` is configured. Add DSN for production error tracking.

### 23. Automated Backups
- [ ] **Configure `spatie/laravel-backup`** — No backup strategy exists. Set up daily DB + storage backups with S3/Wasabi destination.

### 24. SSL / HTTPS
- [ ] **Obtain SSL certificate** — Use Let's Encrypt / Certbot or Cloudflare.
- [ ] **Force HTTPS** — Add `URL::forceScheme('https')` in `AppServiceProvider` or handle via Nginx redirect.

### 25. Test Coverage
- [ ] **19 test files exist but coverage is unknown** — Feature tests cover auth, comments, playlists, security headers, SEO, subscriptions, settings, videos, and production readiness. Run `php artisan test --coverage` to verify coverage percentage and add tests for wallet, live streaming, and WP password migration.

---

## ✅ Already Good

- **Video processing pipeline** — Multi-resolution transcoding, HLS, watermarks, scrubber sprites, faststart — production-grade.
- **Storage abstraction** — `StorageManager` with runtime disk config, Wasabi/B2/S3, CDN URL override, pre-signed URLs.
- **SEO system** — JSON-LD VideoObject schema, OG tags, video sitemap with hreflang, configurable robots.txt, translated slugs.
- **i18n** — Google Translate auto-translation, translated slugs, hreflang, RTL support, per-locale JSON UI files.
- **Admin panel** — 17 Filament pages (settings, importers, dashboard, activity log), CRUD resources for users/videos/categories/etc.
- **Auth flow** — Registration, login (including WP password migration with HMAC-SHA384 + phpass support), email verification, password reset, rate limiting.
- **Wallet service** — Proper DB transactions with `lockForUpdate()` for credits/debits. Gift system with platform cut.
- **Security headers** — CSP, X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy.
- **SSRF protection** — ThumbnailProxy has strict domain suffix matching + private IP blocking.
- **File upload security** — Chunk upload with extension allowlist, size validation, ULID filenames, directory isolation.
- **Authorization** — Policies for Video, Comment, Playlist, LiveStream. Gate checks on upload, withdraw.
- **Scheduled jobs** — Horizon snapshots, batch pruning, Sanctum token pruning, deleted video cleanup, storage cleanup, chunk cleanup.
- **Activity logging** — Spatie activity log on User/Video models, AdminLogger service for admin actions, error logging in exception handler.
- **WordPress migration** — Full import pipeline for videos (Bunny Stream) and users (with HMAC-SHA384 bcrypt + phpass password verification and transparent bcrypt upgrade on first login).

---

## Current Score: 6.5 / 10

### Score Breakdown:
| Area | Score | Notes |
|------|-------|-------|
| **Security** | 7/10 | CSP, SSRF, auth policies solid. `unsafe-inline` in CSP, WP password change bug, broadcast auth too permissive. |
| **Functionality** | 7/10 | Core features complete. Deposit stub, withdrawal admin missing, shorts incomplete. |
| **Infrastructure** | 5/10 | No logging config, no backups, no SSL, no Meilisearch, no Sentry. |
| **Code Quality** | 7/10 | Clean architecture, services pattern, proper policies. Some dead code (EmbeddedVideos). |
| **Data Hygiene** | 4/10 | SQL dump with real passwords in repo, test output committed, unused API key in .env. |
| **Testing** | 6/10 | 19 test files covering core flows. No WP auth tests, no wallet tests. |

**Target after completing CRITICAL + HIGH items: 8.0 / 10**
**Target after completing all items: 9.0+ / 10**
