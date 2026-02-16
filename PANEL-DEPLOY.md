# HubTube — aaPanel Deployment Guide

A complete, step-by-step guide to deploying HubTube on a fresh VPS using **aaPanel** (free edition). This guide assumes you're starting from a blank Ubuntu 22.04/24.04 server and walks through every click and command.

---

## Table of Contents

1. [Server Requirements](#1-server-requirements)
2. [Install aaPanel](#2-install-aapanel)
3. [Install Software via aaPanel App Store](#3-install-software-via-aapanel-app-store)
4. [Configure PHP](#4-configure-php)
5. [Install CLI Tools via SSH](#5-install-cli-tools-via-ssh)
6. [Create MySQL Database](#6-create-mysql-database)
7. [Clone & Build HubTube](#7-clone--build-hubtube)
8. [Create Website in aaPanel](#8-create-website-in-aapanel)
9. [Configure Nginx](#9-configure-nginx)
10. [Fix open_basedir](#10-fix-open_basedir)
11. [Fix Permissions](#11-fix-permissions)
12. [Run the Web Installer](#12-run-the-web-installer)
13. [SSL Certificate](#13-ssl-certificate)
14. [Background Services (Supervisor)](#14-background-services-supervisor)
15. [Laravel Scheduler (Cron)](#15-laravel-scheduler-cron)
16. [Configure .env for Production](#16-configure-env-for-production)
17. [Post-Install Optimization](#17-post-install-optimization)
18. [Admin Panel Overview](#18-admin-panel-overview)
19. [Updating HubTube](#19-updating-hubtube)
20. [Troubleshooting](#20-troubleshooting)
21. [Cloudflare CDN Setup](#21-cloudflare-cdn-setup)

---

## 1. Server Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| **OS** | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| **CPU** | 2 cores | 4+ cores |
| **RAM** | 2 GB | 4+ GB |
| **Disk** | 40 GB SSD | 100+ GB SSD (video storage) |
| **Bandwidth** | 1 TB/mo | Unmetered |

**Recommended VPS providers:** Hetzner, Contabo, OVH, Vultr, DigitalOcean, Linode.

> **Tip:** Video processing (FFmpeg) is CPU-intensive. More cores = faster transcoding. If you plan to use cloud storage (Wasabi/S3/B2), disk space is less critical.

---

## 2. Install aaPanel

SSH into your fresh server as root and run:

```bash
# Ubuntu / Debian
wget -O install.sh https://www.aapanel.com/script/install_7.0_en.sh && bash install.sh aapanel
```

After installation, the script prints:

```
aaPanel Internet Address: http://YOUR_IP:7800/RANDOM_PATH
username: xxxxxxxx
password: xxxxxxxx
```

**Save these credentials.** Open the URL in your browser and log in.

> **Security:** Change the default port and credentials in aaPanel → Settings → Panel Settings.

---

## 3. Install Software via aaPanel App Store

After first login, aaPanel shows a recommended software stack. Choose **LNMP** (Linux + Nginx + MySQL + PHP):

| Software | Version to Install | Notes |
|----------|-------------------|-------|
| **Nginx** | Latest (1.24+) | Select from the popup |
| **MySQL** | 8.0+ or MariaDB 10.11+ | Either works |
| **PHP** | **8.4** (or 8.2 minimum) | Must be 8.2+ |
| **phpMyAdmin** | Latest | Optional but useful |

Wait for all installations to complete (check **App Store → Installed** for progress).

### Install Redis

1. Go to **App Store** → search **Redis**
2. Click **Install** → confirm
3. Wait for installation to complete

### Install PHP Extensions

1. Go to **App Store** → **Installed** → click **PHP 8.4** → **Settings**
2. Click the **Extensions** tab
3. Install these extensions (click Install for each):

| Extension | Required? | Purpose |
|-----------|-----------|---------|
| **redis** | ✅ Yes | Cache, sessions, queues |
| **fileinfo** | ✅ Yes | File MIME detection |
| **bcmath** | ✅ Yes | Wallet/payment math |
| **intl** | ✅ Yes | Internationalization |
| **opcache** | ✅ Yes | PHP performance |
| **exif** | ✅ Yes | Image metadata |
| **imagick** | Recommended | Image processing |

---

## 4. Configure PHP

### Remove Disabled Functions

aaPanel disables many PHP functions by default. HubTube needs them for video processing, queue workers, and Composer.

1. **App Store** → **Installed** → **PHP 8.4** → **Settings**
2. Click **Disabled Functions** tab
3. **Remove** (click the × next to) each of these functions:

```
putenv
symlink
proc_open
proc_get_status
proc_close
exec
shell_exec
pcntl_signal
pcntl_alarm
pcntl_async_signals
```

> **Why?** `proc_open` is needed by Composer and FFmpeg. `exec`/`shell_exec` are needed by video processing. `pcntl_*` functions are needed by Horizon queue workers. `symlink` is needed by `php artisan storage:link`.

### Increase PHP Limits

1. Still in PHP 8.4 Settings → **Configuration** tab
2. Find and change these values:

| Setting | Value | Why |
|---------|-------|-----|
| `upload_max_filesize` | `5G` | Large video uploads |
| `post_max_size` | `5G` | Must be ≥ upload_max_filesize |
| `memory_limit` | `512M` | Video processing needs memory |
| `max_execution_time` | `600` | Long-running uploads/processing |
| `max_input_time` | `600` | Large file upload time |

3. Click **Save** at the bottom

---

## 5. Install CLI Tools via SSH

SSH into your server (or use aaPanel's built-in Terminal):

### Node.js 20 LTS

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
node --version   # Should show v20.x
npm --version    # Should show 10.x
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version   # Should show 2.8+
```

### FFmpeg

```bash
sudo apt-get install -y ffmpeg
ffmpeg -version   # Should show 6.x+
```

> **Alternative:** For the latest FFmpeg with all codecs, the `prerequisites.sh` script installs a static build from johnvansickle.com.

### Supervisor (for background workers)

```bash
sudo apt-get install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

---

## 6. Create MySQL Database

### Option A: Via aaPanel UI

1. Go to **Databases** → **Add Database**
2. **Database Name:** `hubtube`
3. **Username:** `hubtube`
4. **Password:** Generate a strong password (click the dice icon) — **save this password**
5. **Access:** `Local Server`
6. Click **Submit**

### Option B: Via SSH

```bash
sudo mysql
```

```sql
CREATE DATABASE hubtube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hubtube'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON hubtube.* TO 'hubtube'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 7. Clone & Build HubTube

```bash
cd /www/wwwroot
sudo git clone https://github.com/wyatts97/Hubtube.git hubtube
cd hubtube
```

### Run the Setup Script

The included `setup.sh` auto-detects aaPanel, fixes permissions, detects Redis passwords, and configures everything:

```bash
chmod +x setup.sh
sudo bash setup.sh
```

The script will:
- Create required directories
- Copy `.env.example` → `.env` and generate `APP_KEY`
- Auto-detect Redis password from aaPanel's config
- Install Composer dependencies
- Build frontend assets (Vite + Tailwind)
- Publish Filament/Livewire assets
- Create the storage symlink
- Show tailored next steps for aaPanel

### Or Do It Manually

```bash
# Copy environment file
cp .env.example .env

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install JS dependencies and build frontend
npm ci
npm run build

# Generate application key
php artisan key:generate

# Create storage symlink
php artisan storage:link

# Publish admin panel assets
php artisan filament:assets
php artisan icons:cache

# Mark as installed (skip web installer's file check)
# Only do this if you want to use the web installer
```

---

## 8. Create Website in aaPanel

1. Go to **Website** → **Add Site**
2. Fill in:

| Field | Value |
|-------|-------|
| **Domain** | `yourdomain.com` (add `www.yourdomain.com` too) |
| **Root Directory** | `/www/wwwroot/hubtube/public` |
| **PHP Version** | PHP-84 |
| **Database** | Don't create (already done in Step 6) |

> **Critical:** The root directory MUST point to the `/public` subfolder, not the project root.

3. Click **Submit**

---

## 9. Configure Nginx

1. Go to **Website** → click your site name → **Conf** tab
2. **Replace the entire config** with:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;

    root /www/wwwroot/hubtube/public;
    index index.php;

    # ── Upload Limits ──
    client_max_body_size 5G;
    client_body_timeout 600s;
    client_body_buffer_size 128k;

    # ── Security Headers ──
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    charset utf-8;

    # ── Laravel Routing ──
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # ── PHP-FPM ──
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/tmp/php-cgi-84.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 600;
        fastcgi_send_timeout 600;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # ── Static Files ──
    # IMPORTANT: Must include try_files fallback so Filament/Livewire
    # virtual asset routes (e.g. /livewire/livewire.js) reach PHP
    location ~* \.(jpg|jpeg|png|gif|ico|svg|webp|bmp|woff2|woff|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    location ~* \.(css|js)$ {
        expires 12h;
        add_header Cache-Control "public";
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    # ── Video/Media Files (with Range support for seeking) ──
    location ~* \.(mp4|webm|m3u8|ts|vtt|m4s)$ {
        expires 7d;
        add_header Cache-Control "public";
        add_header Accept-Ranges bytes;
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    # ── Vite Build Assets (hashed filenames — cache forever) ──
    location /build/ {
        expires max;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    # ── Storage Symlink ──
    location /storage/ {
        expires 7d;
        add_header Cache-Control "public";
        add_header Accept-Ranges bytes;
        access_log off;
        try_files $uri =404;
    }

    # ── WebSocket Proxy (Laravel Reverb) ──
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }

    # ── Gzip ──
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types text/plain text/css text/javascript application/javascript
               application/json application/xml image/svg+xml font/woff2;

    # ── Block Hidden Files ──
    location ~ /\.(?!well-known) {
        deny all;
    }

    # ── Logging ──
    access_log /www/wwwlogs/hubtube.log;
    error_log /www/wwwlogs/hubtube.error.log;
}
```

3. Click **Save**

> **Check your PHP socket path:** Run `ls /tmp/php-cgi-*.sock` via SSH. If your socket is named differently (e.g. `php-cgi-82.sock`), update the `fastcgi_pass` line accordingly.

---

## 10. Fix open_basedir

aaPanel restricts PHP to only read files within the site's directory. Laravel needs access to `/tmp` for sessions and the full project directory.

### Option A: Disable via aaPanel UI

1. **Website** → click your site → **Site Directory**
2. **Uncheck** "open_basedir" checkbox
3. Click **Save**

### Option B: Fix via SSH

```bash
# Remove the immutable flag aaPanel sets on .user.ini
sudo chattr -i /www/wwwroot/hubtube/public/.user.ini

# Edit the file
sudo nano /www/wwwroot/hubtube/public/.user.ini
```

Set the content to:
```ini
open_basedir=/www/wwwroot/hubtube/:/tmp/:/proc/
```

```bash
# Re-apply immutable flag (aaPanel expects this)
sudo chattr +i /www/wwwroot/hubtube/public/.user.ini
```

---

## 11. Fix Permissions

```bash
cd /www/wwwroot/hubtube

# Set ownership to aaPanel's web user
sudo chown -R www:www .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache
sudo chmod 664 .env

# Ensure storage directories exist
mkdir -p storage/app/public/videos
mkdir -p storage/app/public/thumbnails
mkdir -p storage/app/public/watermarks
mkdir -p storage/app/chunks
mkdir -p storage/app/temp
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
sudo chown -R www:www storage
```

---

## 12. Run the Web Installer

1. Open your browser and go to: `http://yourdomain.com/install`
   (or `http://YOUR_SERVER_IP` if DNS isn't pointed yet)

2. The installer walks through:

| Step | What It Does |
|------|-------------|
| **Requirements** | Checks PHP version, extensions, directory permissions, Redis, MySQL |
| **Database** | Enter the MySQL credentials from Step 6 |
| **Application** | Site name, URL, timezone, email config |
| **Admin Account** | Create your admin login (email + password) |
| **Finalize** | Runs migrations, seeds categories/gifts/settings, creates storage link |

3. After completion, you'll be redirected to the homepage.

> **If the installer page just reloads with no error:** This is usually a CSRF/session issue. Clear sessions and restart PHP-FPM:
> ```bash
> sudo rm -rf /www/wwwroot/hubtube/storage/framework/sessions/*
> sudo chown -R www:www /www/wwwroot/hubtube/storage
> sudo systemctl restart php-fpm-84
> ```

---

## 13. SSL Certificate

### Via aaPanel (Recommended)

1. **Website** → click your site → **SSL** tab
2. Click **Let's Encrypt**
3. Check your domain(s) and click **Apply**
4. Enable **Force HTTPS** toggle

### Update .env

```bash
sudo nano /www/wwwroot/hubtube/.env
```

Change:
```env
APP_URL=https://yourdomain.com
```

Clear config cache:
```bash
cd /www/wwwroot/hubtube
php artisan config:cache
```

---

## 14. Background Services (Supervisor)

HubTube needs two background processes running permanently:

- **Horizon** — Processes video transcoding, cloud uploads, email sending, and all queued jobs
- **Reverb** — WebSocket server for live streaming chat and real-time notifications

### Create Horizon Worker

```bash
sudo nano /etc/supervisor/conf.d/hubtube-horizon.conf
```

```ini
[program:hubtube-horizon]
process_name=%(program_name)s
command=/www/server/php/84/bin/php /www/wwwroot/hubtube/artisan horizon
autostart=true
autorestart=true
user=www
redirect_stderr=true
stdout_logfile=/www/wwwroot/hubtube/storage/logs/horizon.log
stopwaitsecs=3600
```

### Create Reverb Worker

```bash
sudo nano /etc/supervisor/conf.d/hubtube-reverb.conf
```

```ini
[program:hubtube-reverb]
process_name=%(program_name)s
command=/www/server/php/84/bin/php /www/wwwroot/hubtube/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www
redirect_stderr=true
stdout_logfile=/www/wwwroot/hubtube/storage/logs/reverb.log
stopwaitsecs=3600
```

### Start Workers

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

You should see:
```
hubtube-horizon    RUNNING   pid 12345, uptime 0:00:05
hubtube-reverb     RUNNING   pid 12346, uptime 0:00:05
```

> **Check PHP path:** Run `which php` or `ls /www/server/php/*/bin/php` to find the correct path. Adjust the `command=` lines if different.

### Useful Supervisor Commands

```bash
sudo supervisorctl restart hubtube-horizon    # Restart after code changes
sudo supervisorctl stop hubtube-horizon       # Stop worker
sudo supervisorctl tail -f hubtube-horizon    # View live logs
sudo supervisorctl status                     # Check all workers
```

---

## 15. Laravel Scheduler (Cron)

The scheduler runs cleanup tasks, prunes old data, and takes Horizon snapshots.

### Add Cron Job via aaPanel

1. Go to **Cron** → **Add Cron Job**
2. **Type:** Shell Script
3. **Name:** `HubTube Scheduler`
4. **Execution Cycle:** Every 1 minute
5. **Script Content:**
```bash
cd /www/wwwroot/hubtube && /www/server/php/84/bin/php artisan schedule:run >> /dev/null 2>&1
```
6. Click **Submit**

### Or Add via SSH

```bash
sudo crontab -e -u www
```

Add this line:
```
* * * * * cd /www/wwwroot/hubtube && /www/server/php/84/bin/php artisan schedule:run >> /dev/null 2>&1
```

### What the Scheduler Runs

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `horizon:snapshot` | Every 5 min | Dashboard metrics for Horizon |
| `queue:prune-batches` | Daily | Clean old batch records |
| `sanctum:prune-expired` | Daily | Remove expired API tokens |
| `videos:prune-deleted` | Daily | Permanently delete soft-deleted videos after 30 days |
| `storage:cleanup` | Daily | Remove temp files from video processing |
| `uploads:cleanup-chunks` | Daily | Remove abandoned chunk uploads older than 24h |
| `tweets:older-video` | Hourly | Auto-tweet older videos (configurable interval in admin) |

---

## 16. Configure .env for Production

Edit your `.env` file with production values:

```bash
sudo nano /www/wwwroot/hubtube/.env
```

**Critical settings to change:**

```env
APP_NAME="Your Site Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_DATABASE=hubtube
DB_USERNAME=hubtube
DB_PASSWORD=your_secure_password

SESSION_SECURE_COOKIE=true

# If aaPanel's Redis has a password:
REDIS_PASSWORD=your_redis_password

# WebSocket config (update for production domain)
REVERB_HOST=yourdomain.com
REVERB_SCHEME=https
VITE_REVERB_HOST=yourdomain.com
VITE_REVERB_SCHEME=https
```

> **Find Redis password:** `grep requirepass /www/server/redis/redis.conf`
> If it shows a password, add it to `REDIS_PASSWORD` in `.env`.

After editing, rebuild and cache:

```bash
cd /www/wwwroot/hubtube
npm run build                    # Rebuild with production VITE_* vars
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache
sudo supervisorctl restart all   # Restart workers with new config
```

---

## 17. Post-Install Optimization

### Cache Everything

```bash
cd /www/wwwroot/hubtube
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache
```

### Enable OPcache (if not already)

Check in **App Store** → **PHP 8.4** → **Extensions** → ensure **opcache** is installed.

### Configure Meilisearch (Optional — Better Search)

By default, search uses the `database` driver (`LIKE %query%`). For production, Meilisearch provides instant, typo-tolerant full-text search with zero configuration.

```bash
# Install Meilisearch binary
curl -L https://install.meilisearch.com | sh
sudo mv ./meilisearch /usr/local/bin/
sudo chmod +x /usr/local/bin/meilisearch

# Create data directory
sudo mkdir -p /var/lib/meilisearch
sudo chown www:www /var/lib/meilisearch

# Generate a master key (save this!)
MASTER_KEY=$(openssl rand -hex 16)
echo "Your Meilisearch master key: $MASTER_KEY"

# Create systemd service
sudo nano /etc/systemd/system/meilisearch.service
```

```ini
[Unit]
Description=Meilisearch
After=network.target

[Service]
User=www
ExecStart=/usr/local/bin/meilisearch --db-path /var/lib/meilisearch/data --env production --master-key YOUR_MASTER_KEY
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable meilisearch
sudo systemctl start meilisearch

# Verify it's running
curl -s http://127.0.0.1:7700/health   # Should return {"status":"available"}
```

Update `.env`:
```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=YOUR_MASTER_KEY
```

Sync index settings and import existing videos:
```bash
cd /www/wwwroot/hubtube
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Video"
```

> **Index settings** are defined in `config/scout.php` → `meilisearch.index-settings.videos`. The `videos` index is configured with filterable attributes (`user_id`, `category_id`), sortable attributes (`created_at`, `views_count`, `likes_count`), and searchable attributes (`title`, `description`, `tags`). Only public, approved, processed videos are indexed.

---

## 18. Admin Panel Overview

Access the admin panel at `https://yourdomain.com/admin`.

### Settings Pages (18 total)

| Page | Purpose |
|------|--------|
| **Dashboard** | Stats overview (users, videos, views, revenue), trending videos, recent uploads, system status bar |
| **Site Settings** | Site name, logo, registration toggle, upload limits, video quality presets, watermark config, FFmpeg |
| **Theme Settings** | Colors, dark/light mode, CSS variables, custom CSS |
| **Storage & CDN** | Cloud offloading (Wasabi/S3/B2), CDN URL, FFmpeg paths |
| **Integrations** | Bunny Stream API, SMTP/email config with test button |
| **Social Networks** | Social login (Google/Twitter/Reddit), Twitter auto-tweet config with test tweet |
| **Payment Settings** | Wallet, withdrawal limits, payment gateway config |
| **Live Stream Settings** | Agora.io credentials, stream quality, gift settings |
| **Ad Settings** | Video ads (pre/mid/post-roll — MP4/VAST/VPAID/HTML), banner ads, ad targeting |
| **SEO Settings** | Meta tags, JSON-LD schema, video sitemap, robots.txt, OG tags, hreflang |
| **Language Settings** | Multi-language, auto-translation, translation overrides, regenerate button |
| **PWA Settings** | App name, icons, push notification VAPID keys |
| **WordPress Importer** | Import from WP SQL dump |
| **WP User Importer** | Import users from WP with password migration (HMAC-SHA384 + phpass) |
| **Archive Importer** | Import from local WP archive directory with seekability fix |
| **Bunny Migrator** | Download Bunny Stream videos to local/cloud storage |
| **Activity Log** | Admin action audit trail (Spatie activity log) |
| **Failed Jobs** | View and retry failed queue jobs |

### Resources (14 total)

Videos, Categories, Channels, Users, Comments, Live Streams, Gifts, Wallet Transactions, Withdrawal Requests, Sponsored Cards, Menu Items, Pages, Contact Messages, Activity Log

### Key Admin Actions

- **Email:** Configure SMTP in Integrations → click "Send Test Email" to verify
- **Storage:** Enable cloud offloading in Storage & CDN → enter Wasabi/S3 credentials → click "Test Connection"
- **Video Processing:** Configure FFmpeg path, quality presets, and watermarks in Site Settings
- **Social Login:** Enable Google/Twitter/Reddit OAuth in Social Networks → enter client ID/secret
- **Auto-Tweet:** Configure Twitter API keys in Social Networks → enable auto-tweet → click "Send Test Tweet"
- **Video Ads:** Configure pre/mid/post-roll ads in Ad Settings → add ad creatives (MP4, VAST, HTML)
- **Sponsored Cards:** Create in-feed ad cards under Appearance → Sponsored Cards
- **Languages:** Enable auto-translation in Language Settings → add languages → click "Regenerate"
- **Sensitive credentials** (SMTP password, API keys, cloud storage secrets, social login secrets) are **encrypted at rest** in the database

---

## 19. Updating HubTube

When new code is available:

```bash
cd /www/wwwroot/hubtube

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Run new migrations
php artisan migrate --force

# Publish updated assets
php artisan filament:assets
php artisan icons:cache

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart workers
sudo supervisorctl restart hubtube-horizon
sudo supervisorctl restart hubtube-reverb

# Restart PHP-FPM (clears OPcache)
sudo systemctl restart php-fpm-84
```

---

## 20. Troubleshooting

### Blank Admin Panel / Missing CSS / livewire.js 404

**Most common cause:** Nginx static file caching blocks intercept `.js`/`.css` requests before they reach PHP. Filament and Livewire serve assets via Laravel routes, not physical files.

**Fix:** Ensure your static file location blocks include `try_files $uri /index.php?$query_string;` (already included in the Nginx config above).

If still broken:
```bash
cd /www/wwwroot/hubtube
php artisan filament:assets
php artisan icons:cache
sudo chown -R www:www public/vendor/
sudo systemctl restart php-fpm-84
```

### `open_basedir restriction in effect`

```bash
sudo chattr -i /www/wwwroot/hubtube/public/.user.ini
sudo nano /www/wwwroot/hubtube/public/.user.ini
# Set: open_basedir=/www/wwwroot/hubtube/:/tmp/:/proc/
sudo chattr +i /www/wwwroot/hubtube/public/.user.ini
```

### Redis `NOAUTH Authentication required`

aaPanel's Redis often has a password set. Find it:
```bash
grep requirepass /www/server/redis/redis.conf
```

Add to `.env`:
```env
REDIS_PASSWORD=the_password_you_found
```

Then: `php artisan config:cache`

### 502 Bad Gateway

PHP-FPM isn't running or the socket path is wrong:
```bash
# Check if PHP-FPM is running
sudo systemctl status php-fpm-84

# If not running
sudo systemctl start php-fpm-84

# Verify socket exists
ls /tmp/php-cgi-*.sock
```

### Videos Not Processing

1. Check Horizon is running: `sudo supervisorctl status hubtube-horizon`
2. Check FFmpeg is installed: `ffmpeg -version`
3. Check FFmpeg path in Admin → Storage & CDN → FFmpeg tab
4. Check failed jobs: Admin → Failed Jobs, or `php artisan queue:failed`
5. Check logs: `tail -f storage/logs/laravel.log`

### Installer Page Reloads with No Error

CSRF token mismatch — sessions aren't working:
```bash
sudo rm -rf /www/wwwroot/hubtube/storage/framework/sessions/*
sudo chown -R www:www /www/wwwroot/hubtube/storage
sudo systemctl restart php-fpm-84
```

### `file_put_contents(.env): Permission denied`

```bash
sudo chown www:www /www/wwwroot/hubtube/.env
sudo chmod 664 /www/wwwroot/hubtube/.env
```

### WebSocket Connection Failed

1. Ensure Reverb is running: `sudo supervisorctl status hubtube-reverb`
2. Ensure the Nginx WebSocket proxy block is present (the `/app/` location in the config above)
3. Check `.env` has correct Reverb host/scheme values
4. If behind Cloudflare: enable WebSocket support in Cloudflare dashboard → Network → WebSockets

### Large File Uploads Fail (413 / timeout)

1. Check Nginx: `client_max_body_size 5G;` in your site config
2. Check PHP: `upload_max_filesize = 5G` and `post_max_size = 5G`
3. Check PHP: `max_execution_time = 600`
4. If behind Cloudflare: free plan limits uploads to 100MB. Use Cloudflare Pro ($20/mo) for 500MB, or bypass Cloudflare for upload routes.

---

## 21. Cloudflare CDN Setup

Cloudflare sits in front of your server as a reverse proxy, providing DDoS protection, global CDN caching, and SSL. This section covers connecting Cloudflare to HubTube.

### Add Your Domain to Cloudflare

1. Sign up at [dash.cloudflare.com](https://dash.cloudflare.com) (free plan works)
2. Click **Add a Site** → enter your domain (e.g. `yourdomain.com`)
3. Select the **Free** plan (or Pro if you need >100MB uploads through CF)
4. Cloudflare scans your existing DNS records — review and confirm them
5. Update your domain's **nameservers** at your registrar to the two Cloudflare nameservers shown
6. Wait for propagation (usually 5–30 minutes, can take up to 24h)

### DNS Records

Set up these records in Cloudflare DNS:

| Type | Name | Content | Proxy |
|------|------|---------|-------|
| **A** | `@` | `YOUR_SERVER_IP` | Proxied (orange cloud) |
| **A** | `www` | `YOUR_SERVER_IP` | Proxied (orange cloud) |

> **Proxied vs DNS Only:** Orange cloud (Proxied) = traffic goes through Cloudflare CDN. Grey cloud (DNS Only) = traffic goes direct to your server. Always use Proxied for the main site.

### SSL/TLS Settings

1. Go to **SSL/TLS** → **Overview**
2. Set encryption mode to **Full (strict)**
   - This requires a valid SSL cert on your origin server (Let's Encrypt from Step 13)
   - **Never** use "Flexible" — it causes redirect loops with Laravel's HTTPS enforcement
3. Go to **SSL/TLS** → **Edge Certificates**
   - Enable **Always Use HTTPS** → On
   - Set **Minimum TLS Version** → TLS 1.2
   - Enable **Automatic HTTPS Rewrites** → On

### Caching Rules

Cloudflare caches static assets by default. For optimal HubTube performance:

1. Go to **Caching** → **Configuration**
   - Set **Browser Cache TTL** → Respect Existing Headers (Nginx already sets proper cache headers)
2. Go to **Rules** → **Page Rules** (or **Cache Rules** on newer UI)
   - Create a rule to bypass cache for the admin panel:

| Setting | Value |
|---------|-------|
| **URL** | `yourdomain.com/admin/*` |
| **Cache Level** | Bypass |

   - Create a rule to bypass cache for API/auth routes:

| Setting | Value |
|---------|-------|
| **URL** | `yourdomain.com/api/*` |
| **Cache Level** | Bypass |

### Upload Size Limits

**Important:** Cloudflare free plan limits upload request bodies to **100MB**. This affects video uploads.

| Plan | Max Upload Size |
|------|----------------|
| Free | 100 MB |
| Pro ($20/mo) | 500 MB |
| Business ($200/mo) | 500 MB |
| Enterprise | 5 GB |

**Workarounds for large video uploads on the free plan:**

- **Option A (Recommended):** HubTube uses chunked uploads by default. Each chunk is under 100MB, so uploads work through Cloudflare regardless of total file size. No action needed if chunk size in admin is ≤ 95MB.
- **Option B:** Create a DNS-only (grey cloud) subdomain for uploads:
  1. Add an A record: `upload.yourdomain.com` → `YOUR_SERVER_IP` → **DNS Only** (grey cloud)
  2. Set `CHUNK_UPLOAD_URL=https://upload.yourdomain.com` in `.env` (if supported)
  3. Get a separate SSL cert for the upload subdomain via aaPanel

### WebSocket Support

HubTube uses WebSockets (Laravel Reverb) for live streaming chat and real-time notifications.

1. Go to **Network**
   - Enable **WebSockets** → On
2. Cloudflare proxies WebSocket connections automatically when enabled
3. No changes needed to the Nginx config — the `/app/` proxy block handles it

> **Note:** Cloudflare has a 100-second idle timeout for WebSocket connections. Reverb sends periodic pings to keep connections alive, so this shouldn't be an issue.

### Security Settings

1. Go to **Security** → **Settings**
   - Set **Security Level** → Medium
   - Enable **Browser Integrity Check** → On
   - Enable **Bot Fight Mode** → On (free)
2. Go to **Security** → **WAF** (Web Application Firewall)
   - The managed ruleset is enabled by default on all plans
   - If you get false positives on video uploads or admin actions, create a WAF exception:
     - **Skip WAF** for `yourdomain.com/admin/*`
     - **Skip WAF** for `yourdomain.com/api/videos/upload*`

### Speed Optimizations

1. Go to **Speed** → **Optimization**
   - Enable **Auto Minify** → JavaScript, CSS, HTML (all three)
   - Enable **Brotli** → On
   - Enable **Early Hints** → On
   - Enable **Rocket Loader** → **Off** (can break Inertia/Vue SPA — leave disabled)

> **Warning:** Do NOT enable Rocket Loader. It defers JavaScript loading in a way that breaks Vue/Inertia single-page apps.

### Verify It's Working

After DNS propagation:

```bash
# Check that Cloudflare is proxying your site
curl -sI https://yourdomain.com | grep -i "cf-ray\|server"
# Should show: server: cloudflare and a cf-ray header

# Check SSL
curl -sI https://yourdomain.com | grep -i "strict-transport"
```

In Cloudflare dashboard → **Analytics** you should see traffic flowing through.

### Update .env

No `.env` changes are required for basic Cloudflare setup. Laravel automatically reads the real client IP from Cloudflare's `CF-Connecting-IP` header via the `TrustProxies` middleware.

If you're using rate limiting or IP-based features, ensure your `TrustProxies` middleware trusts Cloudflare IPs:

```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = '*'; // Trust all proxies (Cloudflare IPs rotate)
```

### Cloudflare + aaPanel SSL

When using Cloudflare with **Full (strict)** SSL mode, you need a valid cert on your origin:

- **Option A:** Keep using Let's Encrypt (already set up in Step 13) — auto-renews every 90 days
- **Option B:** Use a Cloudflare Origin Certificate (15-year validity):
  1. Cloudflare → **SSL/TLS** → **Origin Server** → **Create Certificate**
  2. Copy the certificate and private key
  3. In aaPanel → **Website** → your site → **SSL** → **Other Certificate**
  4. Paste the cert and key → Save
  5. This cert is only valid for traffic through Cloudflare (not direct access)

---

## Quick Reference

| Task | Command |
|------|---------|
| View logs | `tail -f /www/wwwroot/hubtube/storage/logs/laravel.log` |
| Restart Horizon | `sudo supervisorctl restart hubtube-horizon` |
| Restart Reverb | `sudo supervisorctl restart hubtube-reverb` |
| Restart PHP-FPM | `sudo systemctl restart php-fpm-84` |
| Clear all caches | `php artisan optimize:clear` |
| Rebuild caches | `php artisan config:cache && php artisan route:cache && php artisan view:cache` |
| Rebuild frontend | `npm run build` |
| Check queue status | `php artisan horizon:status` |
| Run migrations | `php artisan migrate --force` |
| Check worker status | `sudo supervisorctl status` |
