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
| **PHP** | 8.4 | 8.4 |
| **Node.js** | 20 | 22 LTS |

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

### PHP 8.4 with required extensions

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-common php8.4-mysql \
    php8.4-xml php8.4-curl php8.4-gd php8.4-mbstring php8.4-zip \
    php8.4-bcmath php8.4-intl php8.4-readline php8.4-redis \
    php8.4-imagick php8.4-soap
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

# Seed default data (categories, settings, pages, plans) and the first admin.
# In production the admin password is random and printed ONCE — copy it now.
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

Edit `/etc/php/8.4/fpm/pool.d/www.conf`:

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

Edit `/etc/php/8.4/fpm/php.ini`:

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
sudo systemctl restart php8.4-fpm
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
sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
sudo supervisorctl restart hubtube:*
```

### First-time Admin Setup

1. Visit `https://yourdomain.com/admin`
2. Log in as `admin@hubtube.com` with the password `db:seed` printed in step 5
3. **Change the admin email and password immediately**
4. Configure in Admin Panel:
   - **Storage & CDN** → Enable Wasabi, enter credentials, enable cloud offloading
   - **Site Settings** → Site name, upload limits, FFmpeg paths
   - **Integrations** → Email/SMTP, Bunny Stream (if migrating)
   - **Payment Gateways** → Stripe/PayPal/CCBill keys
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

# Pull latest code and dependencies (the live site keeps running)
sudo -u www-data git pull origin master
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci

# Build. Old hashed files are kept (emptyOutDir is off), so visitors
# mid-session never request a chunk that has just been deleted.
sudo -u www-data npm run build

# Migrations run with the site in maintenance mode, briefly.
sudo -u www-data php artisan down --retry=15
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan up

# Clear the cached UI translation catalogues.
# resources/js/i18n/*.json is cached with rememberForever, so any release that
# adds or changes a translation key will render the raw dot-path (e.g.
# "common.verified") to users until this runs. config:cache does NOT cover it.
sudo -u www-data php artisan translations:clear-cache

# Rebuild caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Create the permissions and the moderator/editor/uploader/trusted_uploader
# roles (idempotent; keeps any edits made on the Roles screen)
sudo -u www-data php artisan hubtube:sync-roles

# Restart workers (picks up new code)
sudo -u www-data php artisan horizon:terminate
sudo supervisorctl restart hubtube:reverb
sudo systemctl reload php8.4-fpm

# Remove build files from releases older than a week
sudo -u www-data find public/build/assets -type f -mtime +7 -delete
```

**First install only** (and after restoring a backup) — build the Media
Library index. The scheduler keeps it current after that; on a large library
run it under screen/tmux:

```bash
sudo -u www-data php artisan media:index --full
sudo -u www-data php artisan media:thumbnails --limit=500
```

What changed in past releases, and anything a release needs beyond the steps
above, is in [RELEASE-NOTES.md](./RELEASE-NOTES.md).

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

**There are no media backups unless you set them up.** The built-in backup
(Admin → Tools → Backups, or `php artisan backup:run`, nightly at 01:00)
covers the database and code only — video files are excluded because of
their size.

- **Keep a copy off the server.** Add a second disk in `.env`, e.g. the
  Wasabi disk: `BACKUP_DISKS=local,wasabi`. A backup that lives only on the
  VPS is lost with the VPS.
- **Encrypt the archives.** They include `.env`. Set
  `BACKUP_ARCHIVE_PASSWORD` to a long random value, and keep a copy of it
  somewhere other than the server.
- **Back up media separately**, with rclone to any S3 bucket. Configure a
  remote once with `rclone config`, then add a nightly cron job:

```bash
rclone sync /var/www/hubtube/storage/app/public wasabi:hubtube-media --transfers 4 --fast-list
```

`sync` mirrors deletions; use `copy` instead if you want files deleted on the
server to stay in the bucket.

Test a restore now and then — download one archive, and play one video back
from the bucket.

---

## 16. Troubleshooting

### Common Issues

**502 Bad Gateway**
```bash
# Check PHP-FPM is running
sudo systemctl status php8.4-fpm
# Check socket exists
ls -la /run/php/php8.4-fpm.sock
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
