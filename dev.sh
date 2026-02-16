#!/bin/bash

# =============================================================================
# HubTube — Development Server Setup for Ubuntu 22.04 / 24.04
# =============================================================================
# Last updated: 2026-02-15
#
# Tech Stack:
#   - Laravel 11 + PHP 8.2+ + Filament 3 admin panel
#   - Vue 3 + Inertia.js + Vite 6 + Tailwind CSS v4
#   - Redis (sessions, cache, queues, broadcasting)
#   - MySQL 8+ / MariaDB 10.11+
#   - Laravel Reverb (WebSockets for live chat + real-time notifications)
#   - Laravel Horizon (Redis queue dashboard + workers)
#   - Laravel Scout (search — database driver for dev, Meilisearch for prod)
#   - FFmpeg (video transcoding, HLS, thumbnails, watermarks, sprites)
#
# App Features:
#   - 18 Filament admin pages, 14 CRUD resources
#   - 41 Vue pages, 15 Vue components
#   - 33 Eloquent models, 27 controllers, 16 services
#   - 45 database migrations, 7 artisan commands, 7 scheduled jobs
#   - Multi-language auto-translation (stichoza/google-translate-php)
#   - SEO (JSON-LD, OG tags, video sitemap, hreflang, translated slugs)
#   - Video processing (multi-res transcoding, HLS, watermarks, scrubber sprites)
#   - Cloud storage offloading (Wasabi, S3, B2) with CDN support
#   - Social login (Google, Twitter/X, Reddit) via Laravel Socialite
#   - Auto-tweet service (new + scheduled older videos via Twitter API v2)
#   - Video ads (pre/mid/post-roll — MP4, VAST, VPAID, HTML)
#   - Sponsored in-feed ad cards with targeting
#   - Wallet system with deposits, withdrawals, gifting
#   - Live streaming via Agora.io with real-time chat
#   - PWA with push notifications
#   - Content Security Policy with nonce-based scripts
#   - All optional features configured via Admin Panel (DB-backed Setting model)
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status()  { echo -e "${GREEN}[INFO]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error()   { echo -e "${RED}[ERROR]${NC} $1"; }
print_step()    { echo -e "${BLUE}[STEP]${NC} $1"; }

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  HubTube Development Server Setup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ─────────────────────────────────────────────────────────────────────────────
# Step 1: Check dependencies
# ─────────────────────────────────────────────────────────────────────────────
print_step "Checking dependencies..."

# PHP 8.2+
if ! command -v php &>/dev/null; then
    print_error "PHP is not installed. Install PHP 8.2+ first."
    exit 1
fi
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
print_status "PHP: $PHP_VERSION"
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]); then
    print_error "PHP 8.2+ is required. Current: $PHP_VERSION"
    exit 1
fi

# phpredis extension (required — sessions, cache, queues all use Redis)
if ! php -m 2>/dev/null | grep -qi "^redis$"; then
    print_error "phpredis extension is not installed."
    print_error "Install: sudo apt install php${PHP_VERSION}-redis"
    exit 1
fi
print_status "phpredis: installed"

# Composer
if ! command -v composer &>/dev/null; then
    print_error "Composer is not installed."
    print_error "Install: curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer"
    exit 1
fi
print_status "Composer: $(composer --version 2>/dev/null | head -1)"

# Node.js 18+
if ! command -v node &>/dev/null; then
    print_error "Node.js is not installed. Install Node.js 20 LTS."
    exit 1
fi
NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
print_status "Node.js: $(node -v)"
if [ "$NODE_VERSION" -lt 18 ]; then
    print_error "Node.js 18+ is required."
    exit 1
fi

# MySQL / MariaDB
if command -v mysql &>/dev/null; then
    print_status "MySQL: $(mysql --version 2>/dev/null | head -1)"
else
    print_warning "MySQL/MariaDB not found in PATH"
fi

# Redis
if command -v redis-cli &>/dev/null; then
    if redis-cli ping &>/dev/null 2>&1; then
        print_status "Redis: running"
    else
        print_warning "Redis installed but not running. Start: sudo systemctl start redis-server"
    fi
else
    print_warning "Redis not found (required for sessions, cache, queues)"
fi

# FFmpeg (required for video processing)
if command -v ffmpeg &>/dev/null; then
    print_status "FFmpeg: $(ffmpeg -version 2>&1 | head -n1 | cut -d' ' -f3)"
else
    print_warning "FFmpeg not installed (required for video processing, thumbnails, HLS)"
fi

# Meilisearch (optional — falls back to database LIKE search)
if curl -sf http://127.0.0.1:7700/health &>/dev/null; then
    print_status "Meilisearch: running on port 7700"
else
    print_warning "Meilisearch not running — search uses database driver (LIKE queries)"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 2: Environment file
# ─────────────────────────────────────────────────────────────────────────────
print_step "Checking environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    php artisan key:generate --force --no-interaction
    print_status "Created .env — edit DB_PASSWORD and REDIS_PASSWORD before continuing"
    print_warning "Press Enter to continue or Ctrl+C to edit .env first..."
    read -r
else
    print_status ".env exists"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 3: Install dependencies
# ─────────────────────────────────────────────────────────────────────────────
print_step "Installing PHP dependencies..."
composer install --no-interaction --prefer-dist

print_step "Installing Node dependencies..."
npm install

# ─────────────────────────────────────────────────────────────────────────────
# Step 4: Build frontend (Vite 6 + Vue 3 + Tailwind CSS v4)
# ─────────────────────────────────────────────────────────────────────────────
print_step "Building frontend assets..."
npm run build

# ─────────────────────────────────────────────────────────────────────────────
# Step 5: Database
# ─────────────────────────────────────────────────────────────────────────────
print_step "Running migrations (45 migration files)..."
php artisan migrate --force

print_step "Checking database seeding..."
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1)
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    print_status "Seeding database (first run)..."
    php artisan db:seed --force
else
    print_status "Database already seeded ($USER_COUNT users) — skipping"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 6: Storage & assets
# ─────────────────────────────────────────────────────────────────────────────
print_step "Setting up storage and assets..."

# Storage link
if [ ! -L "public/storage" ]; then
    php artisan storage:link
    print_status "Storage symlink created"
else
    print_status "Storage symlink exists"
fi

# Mark as installed (skip web installer on dev)
if [ ! -f "storage/installed" ]; then
    date > storage/installed
    print_status "Marked as installed (skipping web installer)"
fi

# Filament admin panel assets
php artisan filament:assets 2>/dev/null || true
php artisan icons:cache 2>/dev/null || true

# ─────────────────────────────────────────────────────────────────────────────
# Step 7: Clear caches
# ─────────────────────────────────────────────────────────────────────────────
print_step "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear

# ─────────────────────────────────────────────────────────────────────────────
# Step 8: Meilisearch index (if available)
# ─────────────────────────────────────────────────────────────────────────────
if curl -sf http://127.0.0.1:7700/health &>/dev/null; then
    print_step "Syncing Meilisearch index settings..."
    php artisan scout:sync-index-settings 2>/dev/null || true
    print_status "Import videos with: php artisan scout:import 'App\\Models\\Video'"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 9: Start services
# ─────────────────────────────────────────────────────────────────────────────
print_step "Starting development services..."

pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "php artisan reverb:start" 2>/dev/null || true
pkill -f "php artisan horizon" 2>/dev/null || true
sleep 1

SERVER_IP=$(hostname -I | awk '{print $1}')
REVERB_PORT=$(grep -E "^REVERB_PORT=" .env 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "8080")
REVERB_PORT=${REVERB_PORT:-8080}

php artisan serve --host=$SERVER_IP --port=8000 &
SERVER_PID=$!

php artisan reverb:start &
REVERB_PID=$!

php artisan horizon &
HORIZON_PID=$!

sleep 3

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  HubTube Development Server is running!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  URLs:"
echo "    App:        http://$SERVER_IP:8000"
echo "    Admin:      http://$SERVER_IP:8000/admin"
echo "    Horizon:    http://$SERVER_IP:8000/horizon"
echo "    WebSocket:  ws://$SERVER_IP:$REVERB_PORT"
echo ""
echo "  Default Login:"
echo "    Admin:      admin@hubtube.com / password"
echo "    Demo:       demo@hubtube.com / password"
echo ""
echo "  Services:"
echo "    Laravel Server   PID: $SERVER_PID"
echo "    Reverb WS        PID: $REVERB_PID  (port $REVERB_PORT)"
echo "    Horizon Queue    PID: $HORIZON_PID"
echo ""
echo "  Admin Panel (18 pages, 14 resources):"
echo "    Site Settings      — Upload limits, watermark, moderation, FFmpeg"
echo "    Theme Settings     — Colors, dark/light mode, CSS variables"
echo "    Storage & CDN      — Wasabi/S3/B2, CDN URL, FFmpeg paths"
echo "    Integrations       — Bunny Stream, SMTP/email with test button"
echo "    Social Networks    — Social login (Google/X/Reddit), auto-tweet"
echo "    Payment Settings   — Wallet, withdrawals, payment gateways"
echo "    Live Streaming     — Agora.io credentials, gifts, stream quality"
echo "    Ad Settings        — Video ads (pre/mid/post-roll), banner ads"
echo "    SEO Settings       — Meta tags, JSON-LD, sitemap, robots.txt"
echo "    Language Settings  — Auto-translation, overrides, regenerate"
echo "    PWA Settings       — Push notifications, offline support"
echo "    WP Importer        — Import from WordPress SQL dump"
echo "    Archive Importer   — Import from local WP archive directory"
echo "    Bunny Migrator     — Download Bunny Stream videos to local"
echo "    Activity Log       — Admin action audit trail"
echo "    Failed Jobs        — View and retry failed queue jobs"
echo ""
echo "  Scheduled Jobs (via cron — run 'php artisan schedule:run'):"
echo "    horizon:snapshot          — Every 5 min"
echo "    queue:prune-batches       — Daily"
echo "    sanctum:prune-expired     — Daily"
echo "    videos:prune-deleted      — Daily (soft-deleted >30 days)"
echo "    storage:cleanup           — Daily (temp processing files)"
echo "    uploads:cleanup-chunks    — Daily (abandoned chunks >24h)"
echo "    tweets:older-video        — Hourly (auto-tweet older videos)"
echo ""
echo "  Useful Commands:"
echo "    View logs:        tail -f storage/logs/laravel.log"
echo "    Rebuild frontend: npm run build"
echo "    Re-seed:          php artisan db:seed --force"
echo "    Import search:    php artisan scout:import 'App\\Models\\Video'"
echo "    Stop all:         pkill -f 'php artisan'"
echo ""
echo "  Press Ctrl+C to stop all services"

cleanup() {
    echo ""
    print_status "Stopping all services..."
    kill $SERVER_PID 2>/dev/null || true
    kill $REVERB_PID 2>/dev/null || true
    kill $HORIZON_PID 2>/dev/null || true
    sleep 1
    print_status "All services stopped"
    exit 0
}

trap cleanup INT TERM
wait
