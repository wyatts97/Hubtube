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
- [ ] **`unsafe-inline` and `unsafe-eval` in CSP** — `script-src` allows `'unsafe-inline' 'unsafe-eval'` which significantly weakens XSS protection. Audit inline scripts and migrate to nonces or hashes. This is an ongoing effort but should be tracked.

---

## 🟠 MEDIUM-HIGH — Should Fix Soon After Launch

### 3. Dead Code Cleanup
- [ ] **Remove `EmbeddedVideos/` Vue pages** — `Index.vue`, `Show.vue`, `Featured.vue` in `resources/js/Pages/EmbeddedVideos/` are orphaned (no routes point to them). Embedded videos now use the unified `videos` table.
- [ ] **Remove `two_factor_enabled` / `two_factor_secret`** — User model has these fields in fillable/casts/hidden but no controller, middleware, or UI implements 2FA. Remove the fields to avoid confusion.
- [ ] **Remove `FeatureTestOutput` from repo root** — 69KB test output file.
---

## 🟢 MEDIUM — Polish Before Launch

### 4. Skeleton Loading Components
- [ ] **Add skeleton loaders for paginated views** — `VideoCardSkeleton` exists but channel pages, playlists, search results, and history have no skeleton loading states.

---

## 🔵 LOW — Nice to Have

### 5. Automated Backups
- [ ] **Configure `spatie/laravel-backup`** — No backup strategy exists. Set up daily DB + storage backups with S3/Wasabi destination.

### 6. SSL / HTTPS
- [ ] **Obtain SSL certificate** — Use Let's Encrypt / Certbot or Cloudflare.
- [ ] **Force HTTPS** — Add `URL::forceScheme('https')` in `AppServiceProvider` or handle via Nginx redirect.

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
