# HubTube — Production Readiness Checklist

> Full audit performed 2026-02-13. Covers security, bugs, missing features, dead code, and infrastructure.
> Mark items `[x]` as they are completed.

---

## 🔴 CRITICAL — Must Fix Before Launch

### 1. Environment Configuration (Ignore for scoring as these are not required for the app to function and will be changed during install/setup)
- [ ] **Generate `APP_KEY`** — Run `php artisan key:generate`. Without this, sessions, encryption, and signed URLs will not work.
- [ ] **Set `APP_ENV=production`** — Currently `local`. Must be `production` for proper error handling, caching, and security.
- [ ] **Set `APP_DEBUG=false`** — Currently `true`. Debug mode leaks stack traces, env vars, and DB credentials to users.
- [ ] **Set correct `APP_URL`** — Currently `http://localhost`. Must match the actual domain (e.g. `https://yourdomain.com`). Affects emails, SEO canonical URLs, asset URLs, and CORS.
- [ ] **Set `DB_PASSWORD`** — Currently `password`. Use a strong, unique password in production.
- [ ] **Set `SESSION_SECURE_COOKIE=true`** — Currently `false`. Session cookies must only be sent over HTTPS to prevent session hijacking.

---

## 🟡 HIGH — Should Fix Before Launch

### 2. Content Security Policy Tightening
- [x] **CSP nonce support added** — Replaced `'unsafe-inline'` in `script-src` with nonce-based authorization via `Vite::useCspNonce()`. Ziggy `@routes` also receives the nonce. `'unsafe-eval'` remains because Vue's runtime compiler requires it. `style-src` keeps `'unsafe-inline'` because Filament/Livewire inject inline styles dynamically.

---

## 🟠 MEDIUM-HIGH — Should Fix Soon After Launch

### 3. Dead Code Cleanup
- [x] **Removed `EmbeddedVideos/` Vue pages** — Deleted `Index.vue`, `Show.vue`, `Featured.vue` from `resources/js/Pages/EmbeddedVideos/`.
- [x] **Removed `two_factor_enabled` / `two_factor_secret`** — Removed from User model `$fillable`, `$hidden`, and `casts()`. No migration needed (columns can remain in DB harmlessly).
- [x] **Removed `FeatureTestOutput`** — Deleted 69KB test output file from repo root.

---

## 🟢 MEDIUM — Polish Before Launch

### 4. Skeleton Loading Components
- [x] **Added skeleton loaders for paginated views** — `VideoCardSkeleton` now used on Trending, Search, History, Playlists, and Channel/Videos pages with a brief initial loading state.

---

## 🔵 LOW — Nice to Have

### 5. Automated Backups
- [ ] **Install `spatie/laravel-backup` on production server** — Requires `ext-redis` and `ext-pcntl` (not available on Windows dev). Run `composer require spatie/laravel-backup` on the production server, then publish config and set up daily DB + storage backups with S3/Wasabi destination.

### 6. SSL / HTTPS
- [x] **Force HTTPS** — `AppServiceProvider::boot()` already calls `URL::forceScheme('https')` in production.
- [ ] **Obtain SSL certificate** — Use Let's Encrypt / Certbot or Cloudflare. This is a server-level task.

---

## ✅ Already Good

- **Video processing pipeline** — Multi-resolution transcoding, HLS, watermarks, scrubber sprites, faststart — production-grade.
- **Storage abstraction** — `StorageManager` with runtime disk config, Wasabi/B2/S3, CDN URL override, pre-signed URLs.
- **SEO system** — JSON-LD VideoObject schema, OG tags, video sitemap with hreflang, configurable robots.txt, translated slugs.
- **i18n** — Google Translate auto-translation, translated slugs, hreflang, RTL support, per-locale JSON UI files.
- **Admin panel** — 17 Filament pages (settings, importers, dashboard, activity log), CRUD resources for users/videos/categories/etc. Audited for lazy loading violations, deprecated BadgeColumn usage, and missing eager loads.
- **Auth flow** — Registration, login (including WP password migration with HMAC-SHA384 + phpass support), email verification, password reset, rate limiting.
- **Wallet service** — Proper DB transactions with `lockForUpdate()` for credits/debits. Gift system with platform cut.
- **Security headers** — CSP with nonce-based script authorization, X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy.
- **SSRF protection** — ThumbnailProxy has strict domain suffix matching + private IP blocking.
- **File upload security** — Chunk upload with extension allowlist, size validation, ULID filenames, directory isolation.
- **Authorization** — Policies for Video, Comment, Playlist, LiveStream. Gate checks on upload, withdraw.
- **Scheduled jobs** — Horizon snapshots, batch pruning, Sanctum token pruning, deleted video cleanup, storage cleanup, chunk cleanup.
- **Activity logging** — Spatie activity log on User/Video models, AdminLogger service for admin actions, error logging in exception handler.
- **WordPress migration** — Full import pipeline for videos (Bunny Stream) and users (with HMAC-SHA384 bcrypt + phpass password verification and transparent bcrypt upgrade on first login).
- **Skeleton loading** — VideoCardSkeleton used across Home, Trending, Search, History, Playlists, and Channel pages.

---

## Current Score: 8.5 / 10

### Score Breakdown:
| Area | Score | Notes |
|------|-------|-------|
| **Security** | 9/10 | CSP nonce-based scripts, SSRF protection, auth policies, rate limiting, HTTPS forcing. Only `unsafe-eval` remains (Vue requirement). |
| **Functionality** | 8/10 | Core features complete. Withdrawal admin, wallet, live streaming, ads, SEO all functional. Shorts vertical viewer not yet implemented. |
| **Infrastructure** | 7/10 | Logging configured (daily, 14-day retention), HTTPS forcing in place, Meilisearch configured. Backups and SSL cert are server-level tasks. |
| **Code Quality** | 9/10 | Clean architecture, services pattern, proper policies. Dead code removed. Admin panel audited and fixed (deprecated APIs, lazy loading). |
| **Data Hygiene** | 8/10 | Test output removed, unused 2FA fields cleaned, `.env.example` updated. |
| **UX Polish** | 8/10 | Skeleton loaders on all major paginated views. PWA support. Responsive design. |

**Remaining server-level tasks: SSL certificate, spatie/laravel-backup installation**
