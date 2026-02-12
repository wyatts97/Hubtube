# HubTube — Production Readiness Checklist

> Use this file to track all issues that must be resolved before going live.
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
- [ ] **Switch `SCOUT_DRIVER=meilisearch`** — Currently `database` which uses `LIKE %query%` with no relevance ranking or typo tolerance. Meilisearch is already configured in `.env` — just switch the driver.

### 2. Sensitive Credential Encryption
- [x] **Encrypt secrets in the `settings` DB table** — SMTP password, Wasabi keys, B2 keys, S3 keys, Bunny API keys, and BunnyCDN keys are stored as plaintext strings. Now uses Laravel's `encrypt()`/`decrypt()` for all sensitive settings via `Setting::setEncrypted()` / `Setting::getDecrypted()`.
- [x] **Admin pages updated** — `IntegrationSettings`, `StorageSettings`, and `DynamicConfigServiceProvider` now use encrypted read/write for all secret fields.

### 3. Content Security Policy (CSP)
- [x] **Add CSP headers middleware** — No Content Security Policy headers were configured. Added `AddSecurityHeaders` middleware that sets `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, and `Permissions-Policy`. Registered globally in `bootstrap/app.php`.

### 4. SMTP / Email
- [x] **Add "Send Test Email" button** — Admin had no way to verify SMTP configuration works. Added a `sendTestEmail()` action to the Integration Settings page that sends a test email to the logged-in admin and reports success/failure.

### 5. Web Server Configuration
- [x] **Create Nginx config** — The app runs on `php artisan serve` which lacks HTTP Range support, gzip, and proper static file serving. Created `nginx.example.conf` with SSL, gzip, static caching, security headers, and PHP-FPM proxy.

---

## 🟡 HIGH — Should Fix Before Launch

### 6. ThumbnailProxyController SSRF Hardening
- [x] **Tighten URL allowlisting** — The proxy already has a domain allowlist, but uses `str_contains()` which could match substrings. Hardened to use exact domain matching or strict suffix matching. Also added private/internal IP blocking to prevent SSRF to internal services.

### 7. Scheduled Cleanup Commands
- [x] **Temp file cleanup** — `StorageManager::cleanupTemp()` exists but was never called on a schedule. Added `storage:cleanup` artisan command and scheduled it daily.
- [x] **Abandoned chunk cleanup** — Chunk upload directory (`storage/app/chunks/`) accumulates orphaned files from abandoned uploads. Added `uploads:cleanup-chunks` artisan command and scheduled it daily.

### 8. Account Deletion
- [x] **Allow users to delete their account** — No account deletion flow existed. Added `deleteAccount()` method to `SettingsController`, a confirmation UI section in the Settings page, and proper cascade cleanup (videos, channel, comments, etc.).

### 9. Custom Error Pages for Non-Inertia Requests
- [x] **Add `404.blade.php` and `500.blade.php`** — `Error.vue` handles Inertia errors, but direct browser requests (e.g. missing static files, API errors) got generic Laravel error pages. Added styled Blade error pages.

### 10. Rate Limiting
- [ ] **Add rate limiting to video view increment** — Currently every page load increments `views_count` with no deduplication. Add IP/session-based throttle or deduplication window.
- [ ] **Add rate limiting to public API endpoints** — The `/api/video-ads` and search endpoints have no per-IP throttle beyond the global 60/min.

---

## 🟢 MEDIUM — Polish Before Launch

### 11. Skeleton Loading Components
- [ ] **Add skeleton loaders for paginated views** — `VideoCardSkeleton` exists but channel pages, playlists, search results, and history have no skeleton loading states. Add skeleton components for consistent UX.

### 12. Mobile Shorts Carousel
- [ ] **Test and polish touch interactions** — `mobileVideoGrid` setting exists but the Shorts carousel may not be optimized for all screen sizes. Test swipe gestures, snap scrolling, and touch targets on various devices.

### 13. Dead Code Cleanup
- [ ] **Remove `EmbeddedVideo` model** — Embedded videos were migrated to the unified `videos` table. The old model file is dead code.
- [ ] **Remove `EmbeddedVideos/` Vue pages** — `Index.vue`, `Show.vue`, `Featured.vue` in `resources/js/Pages/EmbeddedVideos/` are likely dead routes after migration.
- [ ] **Remove `wedgietu_wp_nnfpq.sql`** — 117MB SQL dump committed to repo root. Remove from git history with `git filter-branch` or BFG Repo-Cleaner.

### 14. Wallet / Payments
- [ ] **Complete deposit flow** — `processDeposit()` in `WalletController` validates input but does nothing (no payment gateway integration). Either integrate Stripe/PayPal/CCBill or hide the deposit UI until ready.
- [ ] **Implement 2FA** — `two_factor_enabled` / `two_factor_secret` fields exist on User model but no implementation. Either implement or remove the fields.

### 15. Error Monitoring & Backups
- [ ] **Set up error monitoring** — No Sentry/Bugsnag/Flare configured. Add an error tracking service for production visibility.
- [ ] **Configure automated backups** — No backup strategy. Use `spatie/laravel-backup` or similar for DB + storage backups.
- [ ] **Configure log rotation** — Default Laravel single-file logging. Switch to `daily` driver with retention in `config/logging.php`.

### 16. SSL / HTTPS
- [ ] **Obtain SSL certificate** — Use Let's Encrypt / Certbot or Cloudflare for free SSL.
- [ ] **Force HTTPS** — Add `URL::forceScheme('https')` in `AppServiceProvider` or handle via Nginx redirect.

---

## ✅ Already Good

- **Video processing pipeline** — Multi-resolution transcoding, HLS, watermarks, scrubber sprites, faststart — production-grade.
- **Storage abstraction** — `StorageManager` with runtime disk config, Wasabi/B2/S3, CDN URL override, pre-signed URLs.
- **SEO system** — JSON-LD schema, OG tags, video sitemap, hreflang, configurable robots.txt.
- **i18n** — Auto-translation, translated slugs, hreflang, RTL support.
- **Admin panel** — Comprehensive Filament admin with 14 settings pages, CRUD resources, import tools.
- **Auth flow** — Registration, login, email verification, password reset, session management, rate limiting on login.
- **Wallet service** — Proper DB transactions with `lockForUpdate()` for credits/debits.
- **Autoloader optimization** — `optimize-autoloader` set to `true` in `composer.json`.

---

## Current Score: 6.0 / 10

**Target after completing this checklist: 8.5+ / 10**
