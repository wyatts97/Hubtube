# HubTube — Video Sharing & Streaming CMS

A self-hosted, feature-rich video-sharing platform built with Laravel, Vue 3, and Inertia.js. Includes video upload/processing, live streaming, monetization, and a full admin panel.

## Quick Start

### Option A: Web Installer (Recommended)
```bash
git clone https://github.com/wyatts97/Hubtube.git hubtube
cd hubtube
composer install
npm install && npm run build
cp .env.example .env
php artisan serve
```
Then visit **http://localhost:8000/install** — the wizard walks through requirements, database, app config, and admin account creation.

### Option B: Manual Setup
```bash
git clone https://github.com/wyatts97/Hubtube.git hubtube
cd hubtube
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env with your database credentials
php artisan migrate
php artisan db:seed
npm run build
php artisan storage:link
```

### Option C: Dev Script (Linux/WSL)
```bash
./dev.sh
```
Handles everything: dependency install, build, migrations, seeding, and starts Laravel serve + Reverb + Horizon + Scraper.

### Start Services
```bash
php artisan serve              # Web server
php artisan horizon            # Queue worker (video processing)
php artisan reverb:start       # WebSocket server (real-time features)
```

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

## Server Setup & Troubleshooting

### ⚠️ Important: Do NOT use default `apt` packages

Ubuntu's default repositories ship **outdated** versions of PHP, Node.js, and Composer that are **incompatible** with HubTube. You must install from the correct sources:

| Dependency | Minimum Version | Ubuntu `apt` Default | What You Need |
|-----------|----------------|---------------------|---------------|
| **PHP** | 8.2+ (8.4 recommended) | 8.1.2 ❌ | Ondrej PPA |
| **Node.js** | 18+ (20 LTS recommended) | 12.x ❌ | NodeSource or binary |
| **Composer** | 2.7+ | 2.2.x ❌ | Official installer |

---

### Installing PHP 8.4 (Ubuntu 20.04+ / Debian 11+)

```bash
# Add the Ondrej PHP PPA (the standard PHP repo for Ubuntu)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.4 with all required extensions
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring \
  php8.4-curl php8.4-gd php8.4-xml php8.4-bcmath php8.4-redis php8.4-zip \
  php8.4-intl php8.4-common

# Set PHP 8.4 as the default CLI version
sudo update-alternatives --set php /usr/bin/php8.4

# Verify
php --version    # Should show 8.4.x
```

> **Note:** `openssl`, `fileinfo`, `tokenizer`, and `ctype` are bundled in `php8.4-common` — they don't have separate packages.

If you're using **Nginx + php-fpm**, update your site config:
```nginx
# Change: fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
# To:
fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
```
Then: `sudo systemctl restart php8.4-fpm nginx`

For **Apache**: `sudo a2dismod php8.1 && sudo a2enmod php8.4 && sudo systemctl restart apache2`

---

### Installing Node.js 20 LTS

**Option A: NodeSource PPA**
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

**Option B: Direct binary (more reliable if apt repos have issues)**
```bash
sudo apt remove -y nodejs  # Remove old version first
wget https://nodejs.org/dist/v20.18.2/node-v20.18.2-linux-x64.tar.xz
sudo tar -xJf node-v20.18.2-linux-x64.tar.xz -C /usr/local --strip-components=1
rm node-v20.18.2-linux-x64.tar.xz
```

```bash
# Verify
node --version   # Should show v20.x.x
npm --version    # Should show 10.x.x
```

---

### Installing Composer 2.x

Do **not** use `sudo apt install composer` — it installs an outdated version with deprecation errors on PHP 8.4.

```bash
sudo apt remove -y composer  # Remove apt version if installed
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Verify
composer --version   # Should show 2.8.x or 2.9.x
```

---

### Common Errors & Fixes

#### `SyntaxError: Unexpected reserved word` when running `npm run build`
**Cause:** Node.js is too old (< 18). Vite requires top-level `await` support.
```bash
node --version  # If < 18, upgrade Node.js (see above)
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
composer install
```

#### `composer.lock` out of sync / package not in lock file
**Cause:** `composer.json` was updated but `composer.lock` wasn't regenerated.
```bash
composer update --no-dev --optimize-autoloader
# Then commit the updated composer.lock to your repo
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
# Then retry your apt command
```

#### `E_STRICT is deprecated` warnings from Composer
**Cause:** System-installed Composer (via `apt`) is too old for PHP 8.4. Reinstall Composer from the official installer (see above).

---

### Production Deployment Checklist

```bash
# On your Linux server:
cd ~/hubtube
git pull origin main

# 1. PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Frontend build
npm install
npm run build

# 3. Database
cp .env.example .env   # First deploy only — edit with your settings
php artisan key:generate  # First deploy only
php artisan migrate --force
php artisan db:seed --force  # First deploy only

# 4. Storage & cache
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Start services
php artisan serve              # Or configure Nginx/Apache
php artisan horizon            # Queue worker (required for video processing)
php artisan reverb:start       # WebSocket server (required for live features)
```

## License

Proprietary — All rights reserved.

## Support

- [GUIDE.MD](./GUIDE.MD) — Detailed architecture and development guide
- Admin panel documentation at `/admin`
- Create an issue in the repository
