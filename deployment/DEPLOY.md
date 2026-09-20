# HubTube — Production Deployment Guide

> **Stack**: Ubuntu 22.04 + Nginx + PHP-FPM + MariaDB + Redis + Cloudflare CDN + Wasabi S3

---

## Table of Contents

1. [Server Requirements](#1-server-requirements)
2. [Initial Server Setup](#2-initial-server-setup)
3. [Install Dependencies](#3-install-dependencies)
4. [Deploy Application](#4-deploy-application)
5. [Database Setup](#5-database-setup)
6. [Cloudflare Setup](#6-cloudflare-setup)
7. [SSL Certificates](#7-ssl-certificates)
8. [Nginx Configuration](#8-nginx-configuration)
9. [PHP-FPM Tuning](#9-php-fpm-tuning)
10. [Supervisor (Background Services)](#10-supervisor-background-services)
11. [Environment Configuration](#11-environment-configuration)
12. [Final Deployment Steps](#12-final-deployment-steps)
13. [Cloudflare-Specific Settings](#13-cloudflare-specific-settings)
14. [Video Upload Considerations](#14-video-upload-considerations)
15. [Maintenance & Updates](#15-maintenance--updates)
16. [Troubleshooting](#16-troubleshooting)

---

## 1. Server Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| **CPU** | 2 cores | 4+ cores (FFmpeg is CPU-heavy) |
| **RAM** | 4 GB | 8+ GB |
| **Disk** | 50 GB SSD | 100+ GB NVMe (temp video processing) |
| **OS** | Ubuntu 22.04 LTS | Ubuntu 22.04 LTS |
| **PHP** | 8.2 | 8.3 |
| **Node.js** | 18 | 20 LTS |

**Note**: If using Wasabi cloud offloading with "delete local after upload", disk usage stays low since processed videos are moved to S3. You still need enough temp space for FFmpeg to process the largest expected upload.

---

## 2. Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Create deploy user (optional, or use existing)
sudo adduser hubtube
sudo usermod -aG sudo hubtube

# Basic security
sudo apt install -y ufw fail2ban
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Set timezone
sudo timedatectl set-timezone UTC
```

---

## 3. Install Dependencies

### PHP 8.2+ with required extensions

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-common php8.2-mysql \
    php8.2-xml php8.2-curl php8.2-gd php8.2-mbstring php8.2-zip \
    php8.2-bcmath php8.2-intl php8.2-readline php8.2-redis \
    php8.2-imagick php8.2-soap
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Node.js 20 LTS

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### MariaDB

```bash
sudo apt install -y mariadb-server
sudo mysql_secure_installation
```

### Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

### FFmpeg

```bash
sudo apt install -y ffmpeg
ffmpeg -version  # verify
```

### Nginx & Supervisor

```bash
sudo apt install -y nginx supervisor
sudo systemctl enable nginx supervisor
```

### Meilisearch (optional, for production search)

```bash
curl -L https://install.meilisearch.com | sh
sudo mv ./meilisearch /usr/local/bin/
# Create systemd service — see Meilisearch docs
```

---

## 4. Deploy Application

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/wyatts97/Hubtube.git hubtube
sudo chown -R www-data:www-data hubtube
cd hubtube

# Install PHP dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader

# Install Node dependencies & build frontend
sudo -u www-data npm ci
sudo -u www-data npm run build

# Install scraper dependencies
cd scraper
sudo -u www-data npm ci --production
cd ..

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 5. Database Setup

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE hubtube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hubtube'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON hubtube.* TO 'hubtube'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
cd /var/www/hubtube

# Run migrations
sudo -u www-data php artisan migrate --force

# Seed default data (categories, gifts, settings, etc.)
sudo -u www-data php artisan db:seed --force

# Create storage symlink
sudo -u www-data php artisan storage:link
```

---

## 6. Cloudflare Setup

### DNS Records

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| **A** | `@` | `YOUR_SERVER_IP` | ☁️ Proxied (orange cloud) |
| **A** | `www` | `YOUR_SERVER_IP` | ☁️ Proxied |
| **A** | `upload` | `YOUR_SERVER_IP` | ☁️ DNS only (gray cloud)* |

*\*Only needed if video uploads exceed Cloudflare's plan limit (100MB free, 500MB Pro).*

### Cloudflare Dashboard Settings

#### SSL/TLS
- **Encryption mode**: `Full (Strict)`
- **Always Use HTTPS**: On
- **Minimum TLS Version**: 1.2
- **Automatic HTTPS Rewrites**: On
- **HSTS**: Enable (max-age 6 months, include subdomains)

#### Speed → Optimization
- **Auto Minify**: CSS, JS, HTML — all On
- **Brotli**: On
- **Early Hints**: On
- **Rocket Loader**: **Off** (can break Vue/Inertia SPA)

#### Caching
- **Caching Level**: Standard
- **Browser Cache TTL**: Respect Existing Headers
- **Always Online**: On

#### Caching → Cache Rules (important for video assets)
Create a rule:
- **When**: URI Path contains `/storage/videos/`
- **Then**: Cache Level = Bypass
- **Reason**: Video files are served from Wasabi via pre-signed URLs, not from origin. If serving locally, set a long edge TTL instead.

#### Network
- **WebSockets**: **On** (required for Reverb/live streaming)
- **gRPC**: Off
- **Onion Routing**: Off

#### Scrape Shield
- **Email Address Obfuscation**: On
- **Server-side Excludes**: On
- **Hotlink Protection**: Off (or On if you want — but Wasabi serves assets directly)

---

## 7. SSL Certificates

### Option A: Cloudflare Origin Certificate (recommended)

1. Go to **Cloudflare → SSL/TLS → Origin Server**
2. Click **Create Certificate**
3. Keep defaults (15-year validity, covers `*.yourdomain.com` and `yourdomain.com`)
4. Copy the certificate and private key

```bash
sudo mkdir -p /etc/ssl/cloudflare
sudo nano /etc/ssl/cloudflare/hubtube-origin.pem      # paste certificate
sudo nano /etc/ssl/cloudflare/hubtube-origin-key.pem   # paste private key
sudo chmod 600 /etc/ssl/cloudflare/hubtube-origin-key.pem
```

### Option B: Let's Encrypt (for upload subdomain or non-Cloudflare)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d upload.yourdomain.com
```

---

## 8. Nginx Configuration

```bash
# Copy the provided config
sudo cp /var/www/hubtube/deployment/nginx/hubtube.conf /etc/nginx/sites-available/hubtube

# Edit: replace yourdomain.com with your actual domain
sudo nano /etc/nginx/sites-available/hubtube

# Enable the site
sudo ln -s /etc/nginx/sites-available/hubtube /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default  # remove default site

# Test and reload
sudo nginx -t
sudo systemctl reload nginx
```

### Key Nginx settings to verify:

- `server_name` — your actual domain
- `root` — `/var/www/hubtube/public`
- `ssl_certificate` / `ssl_certificate_key` — paths to your Cloudflare origin cert
- `client_max_body_size` — `5G` (or match your max upload size)
- PHP-FPM socket path matches your PHP version
- The **private video** block's storage paths (`/var/www/hubtube/storage/app/public/...`) match your install

### Private video protection

Video files are served straight from `/storage/` by nginx. So that a private video can't be fetched by anyone who knows its URL, the app drops an empty `.private` marker into that video's directory. The server-level block at the top of `hubtube.conf` sends any request into a marked directory to Laravel, which checks the viewer first. Public and unlisted videos are unaffected.

1. Add the "Private videos" block from `hubtube.conf` to your vhost:
   - **Single server block with PHP-FPM** (as in `hubtube.conf`): paste both parts inside `server { … }`, above the first `location`, and set the `alias` path to your install.
   - **CloudPanel**: open the site's *Vhost* editor. Paste the `set`/`if` lines into the HTTPS `server` block (the one with `listen 443`), above its first `location`. Paste the `location ^~ /_protected-media/` block into the `listen 8080` server block, with `alias /home/<site-user>/htdocs/<domain>/storage/app/public/;`. The marker check uses `$document_root`, so it needs no path edits.
2. Create markers for videos that are already private (safe to re-run):
   ```bash
   php artisan videos:sync-media-protection
   ```
3. Optional, recommended once step 1 is live: turn on **Admin → Settings → Storage & CDN → Serve private videos via X-Accel-Redirect**. Nginx then sends the file after PHP has authorised the request, instead of PHP streaming it.
4. Check it: open a private video's file URL (e.g. `/storage/videos/<slug>/processed/720p.mp4`) in a logged-out browser. You should get a 404.

If Cloudflare cached a video's files while it was still public, purge those URLs after making it private.

Private videos that were offloaded to Wasabi/B2/S3 always get short-lived pre-signed URLs, but the objects themselves are only protected if the bucket is private.

---

## 9. PHP-FPM Tuning

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
; Process management — use dynamic for video sites
pm = dynamic
pm.max_children = 30
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10
pm.max_requests = 500

; Timeouts for video uploads
request_terminate_timeout = 600
```

Edit `/etc/php/8.2/fpm/php.ini`:

```ini
; Upload limits
upload_max_filesize = 5G
post_max_size = 5G
max_execution_time = 600
max_input_time = 600
memory_limit = 512M

; OPcache (critical for performance)
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0    ; set to 1 during active development
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 10. Supervisor (Background Services)

```bash
# Copy supervisor config
sudo cp /var/www/hubtube/deployment/supervisor/hubtube.conf /etc/supervisor/conf.d/

# Load and start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hubtube:*

# Verify all running
sudo supervisorctl status hubtube:*
```

Expected output:
```
hubtube:horizon    RUNNING   pid 12345, uptime 0:00:05
hubtube:reverb     RUNNING   pid 12346, uptime 0:00:05
hubtube:scraper    RUNNING   pid 12347, uptime 0:00:05
```

---

## 11. Environment Configuration

```bash
cd /var/www/hubtube
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo -u www-data nano .env
```

### Critical `.env` changes for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_PASSWORD=YOUR_STRONG_PASSWORD_HERE

# Session — set domain for cookies
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true

# Reverb — bind to localhost (Nginx proxies externally)
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=https

# Vite — client connects via Cloudflare
VITE_REVERB_HOST="yourdomain.com"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

# Search (if using Meilisearch)
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your_meilisearch_master_key

# Mail — configure in Admin Panel instead
MAIL_MAILER=log
```

### Important: Reverb WebSocket through Cloudflare

The client connects to `wss://yourdomain.com/app/{key}` (port 443 via Cloudflare). Nginx proxies `/app/` to Reverb on `127.0.0.1:8080`. This means:

- `REVERB_HOST` = `127.0.0.1` (where Reverb listens)
- `VITE_REVERB_HOST` = `yourdomain.com` (what the browser connects to)
- `VITE_REVERB_PORT` = `443` (Cloudflare's HTTPS port)
- `VITE_REVERB_SCHEME` = `https`

---

## 12. Final Deployment Steps

```bash
cd /var/www/hubtube

# Cache config for performance
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Set correct permissions one final time
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Restart everything
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
sudo supervisorctl restart hubtube:*
```

### First-time Admin Setup

1. Visit `https://yourdomain.com/admin`
2. Login with `admin@hubtube.com` / `password`
3. **Change the admin password immediately**
4. Configure in Admin Panel:
   - **Storage & CDN** → Enable Wasabi, enter credentials, enable cloud offloading
   - **Site Settings** → Site name, upload limits, FFmpeg paths
   - **Integrations** → Email/SMTP, Bunny Stream (if migrating), Scraper URL
   - **Payment Gateways** → Stripe/PayPal/CCBill keys
   - **Live Streaming** → Agora credentials
   - **Theme Settings** → Colors, branding

---

## 13. Cloudflare-Specific Settings

### Trusted Proxies (Laravel) — Already Configured ✓

HubTube already trusts Cloudflare proxy IPs in `bootstrap/app.php`. This ensures Laravel correctly detects real visitor IPs (`$request->ip()`), HTTPS scheme, and generates proper URLs. No action needed.

### Cloudflare Page Rules (optional)

| Rule | Setting |
|------|---------|
| `*yourdomain.com/admin/*` | Security Level: High, Cache Level: Bypass |
| `*yourdomain.com/horizon/*` | Security Level: High, Cache Level: Bypass |
| `*yourdomain.com/build/*` | Cache Level: Cache Everything, Edge TTL: 1 month |

### Cloudflare WAF (recommended)

- Enable **Managed Rules** (OWASP Core Ruleset)
- Create a custom rule to block non-GET requests to `/admin` from non-whitelisted IPs
- Rate limit `/api/*` endpoints

---

## 14. Video Upload Considerations

### Cloudflare Upload Limits

| Plan | Max Upload Size |
|------|----------------|
| Free | 100 MB |
| Pro | 500 MB |
| Business | 500 MB |
| Enterprise | Custom (up to 5 GB) |

### Solutions for Large Video Uploads

**Option 1: Upload subdomain (DNS-only, bypasses Cloudflare)**
- Set `upload.yourdomain.com` to gray-cloud (DNS only) in Cloudflare
- Use Let's Encrypt for SSL on this subdomain
- Uncomment the upload server block in `hubtube.conf`
- Point your upload form to `https://upload.yourdomain.com/api/videos`

**Option 2: Chunked uploads**
- Implement chunked upload in the frontend (split file into <100MB chunks)
- Reassemble on the server — works within Cloudflare free plan limits

**Option 3: Cloudflare Pro plan**
- $20/month, 500MB upload limit — sufficient for most video uploads

### Wasabi + Cloudflare Flow

```
User uploads video → Cloudflare → Nginx → Laravel (local disk)
                                              ↓
                                    ProcessVideoJob (FFmpeg)
                                              ↓
                                    Wasabi S3 (cloud offload)
                                              ↓
                            Pre-signed URLs served to frontend
                     (Wasabi serves files directly, NOT through Cloudflare)
```

**Important**: Wasabi pre-signed URLs bypass Cloudflare entirely. The video/thumbnail/preview files are served directly from `https://bucket.s3.region.wasabisys.com/...?X-Amz-...`. This is good because:
- No bandwidth counted against Cloudflare
- No upload size limits for serving
- Wasabi has no egress fees

### How videos are encoded

1. `ProcessVideoJob` probes the upload, then makes thumbnails, the hover preview and the seek-bar sprite sheet.
2. It plans one rendition per active profile in **Admin → System → Encoding Profiles** that is below the upload's height. Renditions are tracked in `video_encodings`.
3. Videos longer than the chunking threshold (**Site Settings → Videos**, default 5 minutes) are cut into chunks. Each chunk is encoded by its own queue job, and the chunks are joined afterwards.
4. The **lowest** rendition goes first, on the `video-priority` queue. The video goes live as soon as it's ready, and the remaining renditions then encode in parallel.
5. When everything has finished, the watermarked original (if a watermark is set) replaces the upload, and the files are offloaded to cloud storage if that's enabled.

Progress bars are shown on the admin video page, in the videos table and in the bulk upload status list.

**Queue workers.** `config/horizon.php` runs the `video-priority` and `video-processing` queues on the same supervisor, with `video-priority` taking precedence. Its `maxProcesses` is how many chunks can encode at once. Each worker runs ffmpeg with the **FFmpeg Threads** setting, so keep `maxProcesses × threads` at or below your CPU core count. If you run plain `queue:work` instead of Horizon, use `--queue=video-priority,video-processing`.

**Existing videos** keep working as they are. To give them a newly enabled profile, use **Encode missing renditions** (row or bulk action in Admin → Videos). Renditions already on disk are kept, the video stays live while this runs, and its seek previews are rebuilt as a single sprite sheet.

---

## 15. Maintenance & Updates

### Deploying Updates

```bash
cd /var/www/hubtube

# Pull latest code
sudo -u www-data git pull origin master

# Install dependencies
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci
sudo -u www-data npm run build

# Run migrations
sudo -u www-data php artisan migrate --force

# Clear the cached UI translation catalogues.
# resources/js/i18n/*.json is cached with rememberForever, so any release that
# adds or changes a translation key will render the raw dot-path (e.g.
# "common.verified") to users until this runs. config:cache does NOT cover it.
sudo -u www-data php artisan translations:clear-cache

# Clear and rebuild caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Create the permissions and the moderator/editor/uploader/trusted_uploader
# roles (idempotent; keeps any edits made on the Roles screen)
sudo -u www-data php artisan hubtube:sync-roles

# Media Library index. Only needed the first time (and after restoring a
# backup); the scheduler keeps it current after that. On a large library run
# this under screen/tmux — it is one long process.
sudo -u www-data php artisan media:index --full

# Queue a first batch of Media Library thumbnails. Optional: the page queues
# whatever folder you open, and the scheduler trickles through the rest hourly.
sudo -u www-data php artisan media:thumbnails --limit=500

# Restart workers (picks up new code)
sudo supervisorctl restart hubtube:*
sudo systemctl reload php8.2-fpm
```

### Roles, bans and embedding

- **Roles** (Admin → System → Roles) narrow what an administrator can reach. `php artisan hubtube:sync-roles` seeds `moderator`, `editor`, `uploader` and `trusted_uploader`; assign them per user in Admin → Users. An administrator with **no** roles keeps the access they had before roles existed, so nobody is locked out by deploying this. Super-admins always pass every check.
- **Bans** (Admin → Users → Ban / Suspend) stop an account signing in and end its open sessions, in the panel as well as the site. Suspensions lift by themselves.
- **Registration controls** live in Admin → Settings → Site → Users: the registration switch is now enforced, and there are blocklists for email domains and IPs plus a bundled list of disposable email providers (`resources/data/disposable-email-domains.txt`).
- **Embedding** is Admin → Settings → Site → Videos → "Allow Embedding on Other Sites". It serves `/embed/{slug}` for iframes, answers oEmbed at `/api/oembed`, and puts an embed code in the share dialog. Only that route may be framed cross-origin; everything else stays same-origin. Private, draft and unapproved videos are never embeddable.
- **Drafts** are private to their uploader and stay out of every listing. Anything queued for scheduled publishing is a draft until its time arrives, which closes the old gap where a scheduled video was already openable by URL.

### Comments, playlists and the Creator Studio

Nothing here needs configuring to work, but three things are worth knowing after this release.

- **Comment moderation** (Admin → Settings → Site → Moderation) gained a **Blocked Words** list and a **Maximum Links Per Comment** limit (default 2). A comment that trips either is held for approval rather than refused, so the author is not handed the word list; it stays visible to them and to nobody else until a moderator clears it in Admin → Comments. The existing **Enable Comments** switch is now actually read — turning it off hides the comment section and refuses new comments, which it previously did not.
- **`videos.comments_count` is now maintained by the model**, so approving or deleting a comment from the admin panel moves the counter too. Counts that drifted while that was handled per-controller will correct themselves the next time a comment on that video is added, approved or removed. To recount everything at once, `Comment::where('is_approved', true)->selectRaw('video_id, COUNT(*) c')->groupBy('video_id')` compared against `videos.comments_count` will show the difference.
- **Watch Later** is created for each account the first time it is needed (opening `/playlists`, or the Watch Later button on a video), so no backfill is required. It cannot be deleted or renamed and starts private.
- **Creator Studio** is at `/studio/videos` for every signed-in account — a filterable list of the creator's own videos with bulk privacy, category, tag and delete actions, plus per-video analytics at `/studio/videos/{id}/analytics`. Analytics read `video_views` and `watch_history`, which are already being written; the daily figures only reach as far back as `HUBTUBE_VIEW_LOG_RETENTION_DAYS` (90 by default), and watch time covers signed-in viewers only.

This release adds two migrations: `edited_at` on `comments` (with an index for paginated replies) and a `video_id` index on `watch_history` for the analytics aggregates. Both are covered by the `php artisan migrate --force` above.

### Media Library

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

#### The explorer layout

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

### One stored copy per rendition

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

### Compressing videos

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

### Log Rotation

Add to `/etc/logrotate.d/hubtube`:

```
/var/www/hubtube/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
}
```

### Monitoring

- **Horizon Dashboard**: `https://yourdomain.com/horizon` — queue health, failed jobs
- **Nginx logs**: `/var/log/nginx/hubtube-*.log`
- **Laravel logs**: `/var/www/hubtube/storage/logs/laravel.log`
- **Supervisor logs**: `/var/www/hubtube/storage/logs/*-supervisor.log`

### Backups

```bash
# Database backup
mysqldump -u hubtube -p hubtube | gzip > /backups/hubtube-db-$(date +%Y%m%d).sql.gz

# Application backup (config + uploads if not using Wasabi)
tar -czf /backups/hubtube-app-$(date +%Y%m%d).tar.gz \
    /var/www/hubtube/.env \
    /var/www/hubtube/storage/app/public/
```

---

## 16. Troubleshooting

### Common Issues

**502 Bad Gateway**
```bash
# Check PHP-FPM is running
sudo systemctl status php8.2-fpm
# Check socket exists
ls -la /run/php/php8.2-fpm.sock
# Check Nginx error log
sudo tail -f /var/log/nginx/hubtube-error.log
```

**WebSocket not connecting**
- Verify Cloudflare Network → WebSockets is **On**
- Check `VITE_REVERB_HOST` = your domain (not localhost)
- Check `VITE_REVERB_PORT` = 443
- Check Reverb is running: `sudo supervisorctl status hubtube:reverb`

**Video processing stuck**
```bash
# Check Horizon status
sudo supervisorctl status hubtube:horizon
# Check failed jobs
sudo -u www-data php artisan horizon:failed
# Retry failed jobs
sudo -u www-data php artisan horizon:forget-failed
```

**Wasabi upload failing**
- Verify credentials in Admin → Storage & CDN
- Test connection from admin panel
- Check Laravel log: `tail -f storage/logs/laravel.log | grep -i wasabi`

**Cloudflare 524 timeout on upload**
- Cloudflare has a 100-second timeout for proxied requests
- For long uploads, use the DNS-only upload subdomain
- Or implement chunked uploads

**Permission errors**
```bash
sudo chown -R www-data:www-data /var/www/hubtube/storage
sudo chmod -R 775 /var/www/hubtube/storage /var/www/hubtube/bootstrap/cache
```
