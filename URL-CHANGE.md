# HubTube — Changing Your Domain (URL Migration)

> Example: migrating from `wedgieme.com` (staging) to `wedgietube.com` (production).
> This guide covers every place the URL matters and the exact steps to switch.

---

## Quick Answer

Yes — you can safely host on a temporary domain first, import content, run tests, then switch to your real domain. The app stores almost nothing with hardcoded URLs. The switch takes about 10 minutes.

---

## Step-by-Step

### 1. Update `.env`

```bash
sudo nano /www/wwwroot/hubtube/.env
```

Change:
```env
APP_URL=https://wedgietube.com

# If switching from HTTP staging to HTTPS production:
SESSION_SECURE_COOKIE=true
```

If using Reverb WebSockets in production, also update:
```env
REVERB_HOST=wedgietube.com
REVERB_SCHEME=https
VITE_REVERB_HOST=wedgietube.com
VITE_REVERB_SCHEME=https
```

### 2. Update Nginx / Web Server

In aaPanel: **Website** → click your site → **Domain Management**:
- Add `wedgietube.com` and `www.wedgietube.com`
- Remove `wedgieme.com` (or keep it as a redirect)

Update your Nginx config's `server_name`:
```nginx
server_name wedgietube.com www.wedgietube.com;
```

Optional — redirect old domain to new:
```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name wedgieme.com www.wedgieme.com;
    # SSL certs for old domain here if needed
    return 301 https://wedgietube.com$request_uri;
}
```

### 3. SSL Certificate

In aaPanel: **Website** → click your site → **SSL** → **Let's Encrypt**:
- Request a new certificate for `wedgietube.com` (and `www.wedgietube.com`)
- Enable "Force HTTPS"

### 4. DNS Records

At your domain registrar (Cloudflare, Namecheap, etc.), create:

| Type | Name | Value | TTL |
|------|------|-------|-----|
| A | `@` | `YOUR_SERVER_IP` | Auto |
| CNAME | `www` | `wedgietube.com` | Auto |

If using Cloudflare proxy (orange cloud), that works fine.

### 5. Fix Existing URLs in Database

Most content uses relative URLs (`/video-slug`, `/storage/thumbnails/...`), so no DB changes are needed for videos, thumbnails, or pages.

However, some places may have stored full URLs:

```bash
cd /www/wwwroot/hubtube

# Check if any full URLs exist in the database
php artisan tinker
```

```php
// Check for hardcoded old domain in video descriptions
App\Models\Video::where('description', 'LIKE', '%wedgieme.com%')->count();

// Check pages/legal content
App\Models\Page::where('content', 'LIKE', '%wedgieme.com%')->count();

// If any exist, do a find-and-replace:
App\Models\Video::where('description', 'LIKE', '%wedgieme.com%')
    ->each(fn($v) => $v->update([
        'description' => str_replace('wedgieme.com', 'wedgietube.com', $v->description)
    ]));

App\Models\Page::where('content', 'LIKE', '%wedgieme.com%')
    ->each(fn($p) => $p->update([
        'content' => str_replace('wedgieme.com', 'wedgietube.com', $p->content)
    ]));

// Check admin settings (logo URLs, custom CSS, ad code, etc.)
App\Models\Setting::where('value', 'LIKE', '%wedgieme.com%')->count();

// If any exist:
App\Models\Setting::where('value', 'LIKE', '%wedgieme.com%')
    ->each(fn($s) => $s->update([
        'value' => str_replace('wedgieme.com', 'wedgietube.com', $s->value)
    ]));
```

### 6. Update Admin Panel Settings

In **Admin → SEO Settings**, update:
- **Canonical URL** (if set manually)
- **Sitemap URL** (auto-generated from `APP_URL`, just regenerate)
- **OG URL** (auto-generated from `APP_URL`)

In **Admin → Social Networks**, update:
- **Twitter callback URL** → `https://wedgietube.com/auth/twitter/callback`
- **Google callback URL** → `https://wedgietube.com/auth/google/callback`
- **Reddit callback URL** → `https://wedgietube.com/auth/reddit/callback`

You must also update the callback URLs in each OAuth provider's dashboard:
- **Google Console** → Credentials → OAuth Client → Authorized redirect URIs
- **Twitter Developer Portal** → App Settings → Callback URLs
- **Reddit Apps** → Edit → Redirect URI

In **Admin → Integrations** (Email/SMTP):
- Update **From Address** if it used the old domain (e.g., `noreply@wedgieme.com` → `noreply@wedgietube.com`)

### 7. Rebuild Frontend Assets

The frontend embeds `VITE_REVERB_HOST` and `VITE_APP_NAME` at build time:

```bash
cd /www/wwwroot/hubtube
npm run build
```

### 8. Clear All Caches & Restart Workers

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart background workers to pick up new .env values
sudo supervisorctl restart hubtube-horizon
sudo supervisorctl restart hubtube-reverb

# Restart PHP-FPM to clear OPcache
sudo systemctl restart php-fpm-84
```

### 9. Re-index Search (if using Meilisearch)

Video URLs in search results are generated dynamically from `APP_URL`, so no re-index is needed. But if you stored full URLs in the searchable array:

```bash
php artisan scout:flush "App\Models\Video"
php artisan scout:import "App\Models\Video"
```

### 10. Update External Services

| Service | What to Update |
|---------|---------------|
| **Google OAuth** | Authorized redirect URIs → `https://wedgietube.com/auth/google/callback` |
| **Twitter/X OAuth** | Callback URL → `https://wedgietube.com/auth/twitter/callback` |
| **Reddit OAuth** | Redirect URI → `https://wedgietube.com/auth/reddit/callback` |
| **Sentry** (if used) | Allowed Domains → add `wedgietube.com` |
| **BillionMail** | Update domain/DKIM/SPF records for new domain |
| **Cloudflare** (if used) | Add new domain, copy DNS records |
| **Google Search Console** | Add new property, submit sitemap: `https://wedgietube.com/sitemap.xml` |
| **Google Analytics** (if used) | Update property URL |

### 11. Email DNS Records (BillionMail / SMTP)

If you're using BillionMail or any self-hosted mail server, you need DNS records for the **new** domain:

| Type | Name | Value |
|------|------|-------|
| MX | `@` | `mail.wedgietube.com` (priority 10) |
| A | `mail` | `YOUR_SERVER_IP` |
| TXT | `@` | `v=spf1 ip4:YOUR_SERVER_IP ~all` |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:admin@wedgietube.com` |
| TXT | `default._domainkey` | *(DKIM key from BillionMail)* |

---

## What Does NOT Need Changing

- **Video files** — stored with relative paths (`videos/slug/file.mp4`)
- **Thumbnails** — relative paths (`storage/thumbnails/...`)
- **User avatars** — relative paths
- **Database schema** — no URL columns in core tables
- **Meilisearch index** — URLs generated dynamically
- **Session data** — Redis sessions will expire naturally; users just re-login
- **Queue jobs** — no hardcoded URLs in job payloads
- **Translations** — no domain-specific content

---

## Rollback Plan

If something goes wrong, just:
1. Change `APP_URL` (and `REVERB_HOST`/`VITE_REVERB_HOST`) back to `https://wedgieme.com`
2. Point DNS back to old domain
3. `php artisan config:clear && php artisan cache:clear && php artisan config:cache`
4. `npm run build`
5. `sudo supervisorctl restart all && sudo systemctl restart php-fpm-84`

The switch is fully reversible.
