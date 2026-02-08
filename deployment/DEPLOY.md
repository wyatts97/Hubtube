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

# Clear and rebuild caches
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache

# Restart workers (picks up new code)
sudo supervisorctl restart hubtube:*
sudo systemctl reload php8.2-fpm
```

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
