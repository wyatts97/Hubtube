# HubTube — Video Sharing & Streaming CMS

A self-hosted, feature-rich video-sharing platform built with Laravel, Vue 3, and Inertia.js. Includes video upload/processing, live streaming, monetization, and a full admin panel.

## Quick Start (Local Development)

```bash
git clone https://github.com/wyatts97/Hubtube.git hubtube
cd hubtube
composer install
npm install && npm run build
cp .env.example .env
php artisan serve
```
Then visit **http://localhost:8000/install** — the wizard walks through requirements, database, app config, and admin account creation.

### Dev Script (Linux/WSL)
```bash
./dev.sh
```
Handles everything: dependency install, build, migrations, seeding, and starts Laravel serve + Reverb + Horizon + Scraper.

---

## Production Server Setup (Step-by-Step)

This guide walks through deploying HubTube on a fresh Ubuntu server. No prior server experience needed.

### Step 1: Install System Dependencies

```bash
# Update package list
sudo apt update

# ── PHP 8.4 ──
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
  php8.4-curl php8.4-gd php8.4-xml php8.4-bcmath php8.4-redis php8.4-zip \
  php8.4-intl php8.4-common
sudo update-alternatives --set php /usr/bin/php8.4

# ── Node.js 20 ──
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# ── Composer ──
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# ── MySQL ──
sudo apt install -y mysql-server
sudo systemctl enable mysql

# ── Redis ──
sudo apt install -y redis-server
sudo systemctl enable redis-server

# ── Nginx ──
sudo apt install -y nginx

# ── FFmpeg (for video processing) ──
sudo apt install -y ffmpeg

# Verify everything
php --version      # 8.4.x
node --version     # v20.x
composer --version # 2.8+
mysql --version    # 8.x
redis-cli ping     # PONG
nginx -v           # 1.x
ffmpeg -version    # 6.x+
```

### Step 2: Clone and Build HubTube

```bash
cd ~
git clone https://github.com/wyatts97/Hubtube.git hubtube
cd hubtube

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install JS dependencies and build frontend
npm install
npm run build

# Create .env file
cp .env.example .env
```

### Step 3: Create MySQL Database

```bash
sudo mysql
```

Inside the MySQL prompt:
```sql
CREATE DATABASE hubtube CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hubtube'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON hubtube.* TO 'hubtube'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Important:** Use `mysql_native_password` — PHP's PDO driver works best with it. Replace `YOUR_SECURE_PASSWORD` with a real password.

### Step 4: Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/hubtube
```

Paste this config (replace `yourdomain.com` and the path if different):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;

    root /home/YOUR_USERNAME/hubtube/public;
    index index.php;

    client_max_body_size 2G;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/hubtube /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t        # Test config — should say "ok"
sudo systemctl enable nginx
sudo systemctl restart nginx
```

### Step 5: Fix Permissions

```bash
# Make sure the web server can read/write storage
sudo chown -R $USER:www-data ~/hubtube
sudo chmod -R 775 ~/hubtube/storage ~/hubtube/bootstrap/cache
sudo chmod 755 ~
sudo chmod 664 ~/hubtube/.env

# Create required storage directories
mkdir -p ~/hubtube/storage/app/public
mkdir -p ~/hubtube/storage/framework/{cache,sessions,views}
mkdir -p ~/hubtube/storage/logs
sudo chown -R $USER:www-data ~/hubtube/storage
```

### Step 6: Run the Web Installer

Open your browser and go to:
- **Local network:** `http://<server-local-ip>` (find it with `ip addr show | grep "inet 192"`)
- **Public:** `http://yourdomain.com` (if DNS is pointed to your server)

The installer will walk you through:
1. **Requirements check** — verifies PHP, extensions, directories, Redis, MySQL
2. **Database config** — enter the MySQL credentials from Step 3
3. **Application settings** — site name, URL, timezone, email config
4. **Admin account** — create your admin login
5. **Finalize** — runs migrations, seeds data, creates storage link

### Step 7: Start Background Services

```bash
# Queue worker (REQUIRED for video processing)
php artisan horizon &

# WebSocket server (REQUIRED for live streaming features)
php artisan reverb:start &

# Or use systemd services for production (see below)
```

### Step 8: Create Systemd Services (Production)

Create a Horizon service so it runs on boot:

```bash
sudo nano /etc/systemd/system/hubtube-horizon.service
```

```ini
[Unit]
Description=HubTube Horizon Queue Worker
After=redis.service mysql.service

[Service]
User=YOUR_USERNAME
Group=www-data
WorkingDirectory=/home/YOUR_USERNAME/hubtube
ExecStart=/usr/bin/php artisan horizon
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable hubtube-horizon
sudo systemctl start hubtube-horizon
```

Repeat for Reverb if you use live streaming features.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 11+ (PHP 8.2+), Filament 3 Admin |
| **Frontend** | Vue 3 (Composition API), Inertia.js, Tailwind CSS |
| **Database** | MySQL 8+ / MariaDB 10.6+ |
| **Queue** | Laravel Horizon + Redis |
| **Real-time** | Laravel Reverb (WebSockets) |
| **Search** | Laravel Scout (database driver or Meilisearch) |
| **Video** | FFmpeg (transcode, HLS, thumbnails), HLS.js + Plyr |
| **Live Streaming** | Agora.io (RTC + RTM) |
| **Storage** | Local, Wasabi S3, Backblaze B2 (configurable via admin) |
| **Build** | Vite |

## Requirements

- **PHP** 8.2+ with extensions: pdo_mysql, mbstring, openssl, curl, fileinfo, gd, xml, bcmath
- **Composer** 2.x
- **Node.js** 18+
- **MySQL 8+** or **MariaDB 10.6+**
- **Redis** (for queues, cache, sessions)
- **FFmpeg** (for video processing — optional but recommended)

## Features

### Video Platform
- Upload, transcode to multiple qualities (240p–1080p), HLS adaptive streaming
- Auto-generated thumbnails, animated WebP previews, scrubber sprite sheets
- Shorts (vertical video) with TikTok-style swipe viewer
- Video watermarking (configurable position, opacity, scale)
- Embedded video support (Bunny Stream, external iframes)
- Categories, tags, hashtags, playlists, watch history
- Full-text search with category/tag filters

### Live Streaming
- Agora.io-powered interactive live streams
- Real-time chat via Agora RTM
- Virtual gift system with animations and wallet integration
- Viewer count tracking, stream moderation

### Monetization
- Wallet system with deposit/withdrawal
- Virtual gifts during live streams (platform cut configurable)
- Paid videos (purchase + rental with expiry)
- Video ad system: pre-roll, mid-roll, post-roll (MP4, VAST, VPAID, HTML)
- Ad targeting by category and user role, weighted random selection
- Click-through URLs on video ads
- Banner ads (above/below player, sidebar, video grid)

### Admin Panel (`/admin`)
- **Dashboard**: Stats overview, system status bar (Redis, Horizon, disk usage)
- **Content**: Videos, embedded videos, categories, comments, reports
- **Users**: User management, channels, gifts
- **Settings**: Site settings, theme customization, storage & CDN, payments, live streaming, integrations, PWA, ads
- **Tools**: WordPress importer, Bunny Stream migrator, video embedder, menu builder

### User Features
- Registration, login, email verification, password reset
- User profiles with channels, avatars, banners
- Subscribe to channels with notification preferences
- Playlists, watch history, likes/dislikes, nested comments
- Push notifications (Web Push API)
- Creator dashboard with upload stats

### Modern Web
- PWA: service worker, offline support, installable
- Dark/light theme with full CSS variable customization via admin
- Responsive mobile-first design
- Age verification gate (configurable text, styling, behavior)
- Custom navigation menu builder
- i18n framework with multi-language support

## Environment Configuration

Only infrastructure settings go in `.env`. All feature settings (storage, payments, streaming, integrations) are managed via the Admin Panel.

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hubtube
DB_USERNAME=hubtube
DB_PASSWORD=password

# Session / Cache / Queue
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis

# WebSockets
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=hubtube
REVERB_APP_KEY=hubtube-key
REVERB_APP_SECRET=hubtube-secret

# Search (optional)
SCOUT_DRIVER=database          # or 'meilisearch'
```

## Cloud Storage

Storage is configured entirely via **Admin → Settings → Storage & CDN**. Supported providers:
- **Local** (default)
- **Wasabi** (S3-compatible, no egress fees)
- **Backblaze B2** (S3-compatible)
- **AWS S3**

Videos are always processed locally first (FFmpeg needs filesystem access), then automatically offloaded to cloud storage by `ProcessVideoJob`. Each video tracks its `storage_disk` for correct URL resolution.

The `StorageManager` service handles all disk operations, URL generation (including pre-signed URLs for private buckets), and CDN URL overrides.

## Email Configuration

HubTube sends transactional emails for signup verification, password resets, subscription notifications, payment receipts, and withdrawal confirmations. Configure email during installation (Step 3) or manually in `.env`.

### Option A: Maddy Mail Server (Self-Hosted, Recommended)

A setup script is included in `maddy-mail-setup/` that installs [Maddy](https://maddy.email) — a lightweight, single-binary mail server with built-in DKIM, SPF, and DMARC support. No Docker required.

```bash
# On your production server
sudo bash maddy-mail-setup/setup-maddy.sh yourdomain.com
```

The script handles installation, TLS certificates, DKIM key generation, account creation, and prints the DNS records and `.env` values you need. See [`maddy-mail-setup/README.md`](./maddy-mail-setup/README.md) for full documentation.

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=<generated-by-script>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### Option B: External SMTP Provider

Point Laravel at any SMTP server (Gmail, Mailgun, Amazon SES, Postmark, Resend, etc.):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

> **Note for adult sites:** Many hosted email providers (Mailgun, SendGrid, Postmark) prohibit adult content in their TOS. Self-hosted options like Maddy or Postfix avoid this restriction entirely.

### Option C: Log Driver (Development / Skip for Now)

Emails are written to `storage/logs/laravel.log` instead of being sent. Useful during development or if you want to configure email later.

```env
MAIL_MAILER=log
```

## Project Structure

```
app/
├── Console/Commands/       # Artisan commands (storage migration, Bunny download)
├── Events/                 # VideoUploaded, VideoProcessed, GiftSent, etc.
├── Filament/               # Admin panel (10 settings pages, 8 resources, widgets)
├── Http/
│   ├── Controllers/        # 20+ controllers (video, auth, channel, wallet, etc.)
│   ├── Middleware/          # Age verification, admin check, install guard, Inertia
│   └── Requests/           # Form request validation
├── Jobs/                   # ProcessVideoJob (FFmpeg + cloud offload)
├── Models/                 # 26 Eloquent models
├── Policies/               # Video, comment, playlist, live stream authorization
├── Services/               # VideoService, StorageManager, WalletService, AgoraService, etc.
resources/
├── js/
│   ├── Components/         # 15 Vue components (VideoPlayer, VideoAdPlayer, ShortsViewer, etc.)
│   ├── Composables/        # 8 composables (useFetch, useToast, useTheme, etc.)
│   ├── Layouts/            # AppLayout with responsive sidebar
│   └── Pages/              # 30+ Inertia pages
├── css/                    # Tailwind CSS with custom utilities
└── views/
    ├── install/            # Web installer (6 step views)
    └── filament/           # Admin panel Blade views
database/
├── migrations/             # 34 migrations
└── seeders/                # Categories, gifts, settings, demo users
scraper/                    # Node.js content scraping microservice
```

## Key Models

| Model | Purpose |
|-------|---------|
| `User` | Auth, wallet, admin/pro flags, channel |
| `Video` | Uploads + embedded, multi-quality, cloud storage tracking |
| `Channel` | User profiles with subscriber counts |
| `LiveStream` | Agora-powered streams with viewer tracking |
| `VideoAd` | Ad creatives (MP4/VAST/VPAID/HTML) with targeting |
| `Setting` | Key-value store for all admin-configurable settings |
| `WalletTransaction` | Financial ledger with balance tracking |
| `GiftTransaction` | Live stream gift records |

## Video Processing Pipeline

1. User uploads video → stored locally in `storage/app/public/videos/{slug}/`
2. `ProcessVideoJob` dispatched via Horizon:
   - FFprobe extracts metadata (duration, resolution)
   - Generates thumbnails (configurable count)
   - Generates animated WebP preview
   - Generates scrubber sprite sheet + VTT
   - Transcodes to enabled resolutions (240p–1080p)
   - Generates HLS playlists (master + per-quality)
   - Applies watermark if enabled
3. If cloud offloading enabled → uploads all files to Wasabi/S3/B2
4. Video marked as `processed`, auto-published

## Development

```bash
npm run dev          # Vite dev server with HMR
npm run build        # Production build
php artisan test     # Run test suite
```

## Default Credentials

After running seeders (`php artisan db:seed`):
- **Admin**: `admin@hubtube.com` / `password`
- **Demo User**: `demo@hubtube.com` / `password`

The web installer at `/install` lets you create a custom admin account instead.

## Troubleshooting

### ⚠️ Important: Do NOT use default `apt` packages

Ubuntu's default repositories ship **outdated** versions of PHP, Node.js, and Composer that are **incompatible** with HubTube. See [Step 1](#step-1-install-system-dependencies) above for correct installation.

| Dependency | Minimum Version | Ubuntu `apt` Default | What You Need |
|-----------|----------------|---------------------|---------------|
| **PHP** | 8.2+ (8.4 recommended) | 8.1.2 ❌ | Ondrej PPA |
| **Node.js** | 18+ (20 LTS recommended) | 12.x ❌ | NodeSource or binary |
| **Composer** | 2.7+ | 2.2.x ❌ | Official installer |

---

### Web Installer Issues

#### Installer page just reloads with no error (silent fail)
**Cause:** CSRF token mismatch — the session driver (Redis/database) isn't working yet during installation. The installer auto-forces file sessions, but if you're running an older version of HubTube, update `bootstrap/app.php`.
```bash
# Fix: clear stale sessions and restart PHP
sudo rm -rf ~/hubtube/storage/framework/sessions/*
sudo chown -R $USER:www-data ~/hubtube/storage
sudo systemctl restart php8.4-fpm
```

#### `file_put_contents(.env): Permission denied`
**Cause:** The `.env` file isn't writable by the web server (`www-data`).
```bash
sudo chown $USER:www-data ~/hubtube/.env
sudo chmod 664 ~/hubtube/.env
```

#### `storage/app and public aren't writeable`
**Cause:** Storage directories are missing or have wrong ownership.
```bash
mkdir -p ~/hubtube/storage/app/public
mkdir -p ~/hubtube/storage/framework/{cache,sessions,views}
mkdir -p ~/hubtube/storage/logs
sudo chown -R $USER:www-data ~/hubtube/storage ~/hubtube/public
sudo chmod -R 775 ~/hubtube/storage ~/hubtube/bootstrap/cache
```

#### Database step won't connect (no error shown)
**Cause:** MySQL user uses `caching_sha2_password` auth plugin, which PHP's PDO may not support.
```bash
# Fix: switch to mysql_native_password
sudo mysql -e "ALTER USER 'hubtube'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YOUR_PASSWORD';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

#### `RedisException: Connection refused` on installer page
**Cause:** Redis server isn't installed or running.
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
redis-cli ping  # Should say PONG
```

#### MySQL password with special characters (`!`, `$`, etc.) fails in bash
**Cause:** Bash interprets `!` as history expansion and `$` as variable substitution.
```bash
# Use the interactive MySQL prompt instead of one-liners:
sudo mysql
# Then paste SQL commands directly (special characters are safe inside MySQL)
```

---

### Nginx Issues

#### `bind() to 0.0.0.0:80 failed (Address already in use)`
**Cause:** Another web server (Apache, Nextcloud snap, etc.) is using port 80.
```bash
# Find what's on port 80
sudo ss -tlnp | grep :80

# Common fixes:
sudo systemctl stop apache2       # Stop Apache
sudo snap disable nextcloud        # Disable Nextcloud snap
sudo fuser -k 80/tcp              # Force kill anything on port 80
sudo systemctl restart nginx
```

#### `502 Bad Gateway`
**Cause:** PHP-FPM isn't running or the socket path is wrong.
```bash
# Check PHP-FPM status
sudo systemctl status php8.4-fpm

# If not running:
sudo systemctl start php8.4-fpm
sudo systemctl enable php8.4-fpm

# Verify socket exists:
ls /var/run/php/php8.4-fpm.sock
```

---

### Build & Dependency Errors

#### `SyntaxError: Unexpected reserved word` when running `npm run build`
**Cause:** Node.js is too old (< 18). Vite requires top-level `await` support.
```bash
node --version  # If < 18, upgrade Node.js (see Step 1)
```

#### `Could not resolve "../../vendor/tightenco/ziggy"`
**Cause:** Composer dependencies aren't installed. Ziggy is a PHP package that provides a JS module.
```bash
composer install
npm run build
```

#### `Please provide a valid cache path`
**Cause:** Laravel's storage directories don't exist.
```bash
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p bootstrap/cache
```

#### `composer.lock` out of sync / package not in lock file
**Cause:** `composer.json` was updated but `composer.lock` wasn't regenerated.
```bash
composer update --no-dev --optimize-autoloader
```

#### `-bash: /bin/node: No such file or directory` (but `npm` works)
**Cause:** Bash cached the old path to `node`. Clear the hash table:
```bash
hash -r
node --version
```
This also happens with `composer` after reinstalling it.

#### `ext-pcntl is missing` / `ext-redis is missing`
**Cause:** You're running Composer on **Windows**. Extensions like `pcntl` are Linux-only. Always run `composer install/update` on your **Linux server**, not on Windows.

#### `Waiting for cache lock: Could not get lock /var/lib/dpkg/lock-frontend`
**Cause:** Another `apt` process is running (usually `unattended-upgrades`).
```bash
sudo systemctl stop unattended-upgrades
sudo kill -9 <PID_FROM_ERROR>
sudo rm -f /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock
sudo dpkg --configure -a
```

#### `E_STRICT is deprecated` warnings from Composer
**Cause:** System-installed Composer (via `apt`) is too old for PHP 8.4. Reinstall from the official installer:
```bash
sudo apt remove -y composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

### Redeployment Checklist

After pulling new code:

```bash
cd ~/hubtube
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.4-fpm
sudo systemctl restart hubtube-horizon
```

## License

Proprietary — All rights reserved.

## Support

- [GUIDE.MD](./GUIDE.MD) — Detailed architecture and development guide
- Admin panel documentation at `/admin`
- Create an issue in the repository
