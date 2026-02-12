#!/bin/bash

# HubTube Development Server Setup for Ubuntu 22.04 / 24.04
# This script sets up and runs the HubTube development server
#
# Features supported:
#   - Laravel 11 + Filament 3 admin panel (15 settings pages, 11 resources)
#   - Vite 6 + Vue 3 + Tailwind CSS v4 frontend build
#   - Laravel Reverb WebSockets (port 8080)
#   - Laravel Horizon queue worker (Redis)
#   - phpredis extension (required)
#   - Meilisearch (optional, falls back to database driver)
#   - Auto-translation via stichoza/google-translate-php
#   - SEO system (JSON-LD, OG tags, video sitemap, hreflang)
#   - Video processing (FFmpeg multi-res transcoding, HLS, watermarks)
#   - Cloud storage offloading (Wasabi, S3, B2)
#   - PWA with push notifications
#   - Video & banner ad system
#   - Encrypted credential storage (SMTP, API keys)
#   - Content Security Policy headers
#   - Scheduled cleanup (temp files, abandoned chunks)
#   - All settings managed via Admin Panel (DB-backed)

set -e

echo "🚀 Starting HubTube Development Server Setup..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if running on Ubuntu
if ! grep -q "Ubuntu" /etc/os-release; then
    print_error "This script is designed for Ubuntu 22.04"
    exit 1
fi

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')
print_status "Server IP: $SERVER_IP"

# ─────────────────────────────────────────────────────────────────────────────
# Step 1: Check dependencies
# ─────────────────────────────────────────────────────────────────────────────
print_step "Checking dependencies..."

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP 8.2+ first."
    exit 1
fi

PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
print_status "PHP version: $PHP_VERSION"

if [[ $(echo "$PHP_VERSION < 8.2" | bc -l) -eq 1 ]]; then
    print_error "PHP 8.2+ is required. Current version: $PHP_VERSION"
    exit 1
fi

# Check phpredis extension (required — app uses REDIS_CLIENT=phpredis)
if ! php -m 2>/dev/null | grep -qi "^redis$"; then
    print_error "phpredis extension is not installed."
    print_error "Install it: sudo apt install php${PHP_VERSION}-redis"
    exit 1
fi
print_status "phpredis extension: installed"

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed"
    exit 1
fi

# Check Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed"
    exit 1
fi

NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
print_status "Node.js version: $(node -v)"

if [[ $NODE_VERSION -lt 18 ]]; then
    print_error "Node.js 18+ is required"
    exit 1
fi

# Check MariaDB/MySQL
if ! command -v mysql &> /dev/null; then
    print_warning "MySQL/MariaDB is not installed or not in PATH"
fi

# Check Redis
if ! command -v redis-cli &> /dev/null; then
    print_warning "Redis is not installed (required for cache, queue, sessions)"
else
    if redis-cli ping &>/dev/null; then
        print_status "Redis: running"
    else
        print_warning "Redis is installed but not running. Start it: sudo systemctl start redis-server"
    fi
fi

# Check FFmpeg
if ! command -v ffmpeg &> /dev/null; then
    print_warning "FFmpeg is not installed (required for video processing)"
else
    print_status "FFmpeg: $(ffmpeg -version 2>&1 | head -n1 | cut -d' ' -f3)"
fi

# Check Meilisearch (optional)
if command -v meilisearch &> /dev/null || curl -s http://127.0.0.1:7700/health &>/dev/null; then
    print_status "Meilisearch: available"
else
    print_warning "Meilisearch not found — search will use database driver (slower but functional)"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 2: Environment file
# ─────────────────────────────────────────────────────────────────────────────
print_step "Checking environment configuration..."
if [ ! -f ".env" ]; then
    print_status "Creating .env file from .env.example"
    cp .env.example .env
    php artisan key:generate
    print_warning "Please edit .env file with your database credentials"
    echo "Press Enter to continue or Ctrl+C to stop..."
    read -r
else
    print_status ".env file exists"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 3: Install PHP dependencies (always run to catch new packages)
# ─────────────────────────────────────────────────────────────────────────────
print_step "Installing PHP dependencies..."
composer install --no-interaction --prefer-dist

# ─────────────────────────────────────────────────────────────────────────────
# Step 4: Install Node dependencies (always run to catch new packages)
# ─────────────────────────────────────────────────────────────────────────────
print_step "Installing Node dependencies..."
npm install

# ─────────────────────────────────────────────────────────────────────────────
# Step 5: Build frontend assets (Vite 6 + Tailwind CSS v4)
# ─────────────────────────────────────────────────────────────────────────────
print_step "Building frontend assets..."
npm run build

# ─────────────────────────────────────────────────────────────────────────────
# Step 6: Database migrations
# ─────────────────────────────────────────────────────────────────────────────
print_step "Running database migrations..."
php artisan migrate --force

# ─────────────────────────────────────────────────────────────────────────────
# Step 7: Seed database (only if users table is empty — safe for re-runs)
# ─────────────────────────────────────────────────────────────────────────────
print_step "Checking database seeding..."
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1)
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    print_status "Seeding database (first run)..."
    php artisan db:seed --force
else
    print_status "Database already seeded ($USER_COUNT users found) — skipping"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 8: Storage link + installation marker
# ─────────────────────────────────────────────────────────────────────────────
print_step "Creating storage link..."
if [ ! -L "public/storage" ]; then
    php artisan storage:link
else
    print_status "Storage link already exists"
fi

# Mark as installed (skip web installer on dev server)
if [ ! -f "storage/installed" ]; then
    print_step "Marking app as installed (skipping web installer)..."
    date > storage/installed
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 9: Publish Filament assets
# ─────────────────────────────────────────────────────────────────────────────
print_step "Publishing Filament assets..."
php artisan filament:assets 2>/dev/null || true

# ─────────────────────────────────────────────────────────────────────────────
# Step 10: Generate translation files (if translation feature is enabled)
# ─────────────────────────────────────────────────────────────────────────────
if php artisan list 2>/dev/null | grep -q "translations:generate"; then
    print_step "Generating translation files..."
    php artisan translations:generate 2>/dev/null || print_warning "Translation generation skipped (configure in Admin → Languages)"
else
    print_status "Translation command not available — skipping"
fi

# ─────────────────────────────────────────────────────────────────────────────
# Step 11: Clear caches
# ─────────────────────────────────────────────────────────────────────────────
print_step "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear

# ─────────────────────────────────────────────────────────────────────────────
# Step 12: Start services
# ─────────────────────────────────────────────────────────────────────────────
print_step "Starting development services..."

# Kill any existing processes
pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "php artisan reverb:start" 2>/dev/null || true
pkill -f "php artisan horizon" 2>/dev/null || true
sleep 1

# Read Reverb port from .env (default 8080)
REVERB_PORT=$(grep -E "^REVERB_PORT=" .env 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "8080")
REVERB_PORT=${REVERB_PORT:-8080}

# Start Laravel development server
print_status "Starting Laravel server on http://$SERVER_IP:8000"
php artisan serve --host=$SERVER_IP --port=8000 &
SERVER_PID=$!

# Start Reverb (WebSocket server)
print_status "Starting WebSocket server (Reverb) on port $REVERB_PORT"
php artisan reverb:start &
REVERB_PID=$!

# Start Horizon (queue worker)
print_status "Starting queue worker (Horizon)"
php artisan horizon &
HORIZON_PID=$!

# Wait a moment for services to start
sleep 3

# Display access information
echo ""
echo "============================================================"
echo "  🎉 HubTube Development Server is running!"
echo "============================================================"
echo ""
echo "📱 Access URLs:"
echo "   Main App:     http://$SERVER_IP:8000"
echo "   Admin Panel:  http://$SERVER_IP:8000/admin"
echo "   WebSocket:    ws://$SERVER_IP:$REVERB_PORT"
echo ""
echo "👤 Default Login:"
echo "   Admin:        admin@hubtube.com / password"
echo "   Demo User:    demo@hubtube.com / password"
echo ""
echo "🔧 Services Running:"
echo "   Laravel Server  (PID: $SERVER_PID)"
echo "   Reverb WS       (PID: $REVERB_PID, port $REVERB_PORT)"
echo "   Horizon Queue   (PID: $HORIZON_PID)"
echo ""
echo "📋 Admin Panel Tools:"
echo "   WP Import        — Import from WordPress SQL dump"
echo "   Archive Import   — Import from local WP archive directory"
echo "   Bunny Migrator   — Download Bunny Stream videos to local"
echo "   Language Settings — Configure auto-translation + i18n"
echo "   SEO Settings     — Meta tags, JSON-LD schema, sitemap, hreflang"
echo "   Ad Settings      — Video ads (pre/mid/post-roll) + banner ads"
echo "   Storage & CDN    — Cloud offloading (Wasabi/S3/B2), CDN config"
echo "   Integrations     — SMTP config with test email, Bunny Stream"
echo "   Theme Settings   — Colors, dark/light mode, CSS variables"
echo "   PWA Settings     — Push notifications, offline support"
echo ""
echo "📋 Useful Commands:"
echo "   View logs:       tail -f storage/logs/laravel.log"
echo "   Queue status:    php artisan horizon"
echo "   Rebuild assets:  npm run build"
echo "   Re-seed:         php artisan db:seed --force"
echo "   Cleanup temp:    php artisan storage:cleanup"
echo "   Cleanup chunks:  php artisan uploads:cleanup-chunks"
echo "   Stop all:        pkill -f 'php artisan'"
echo ""
echo "Press Ctrl+C to stop all services"

# Function to cleanup on exit
cleanup() {
    echo ""
    print_status "Stopping all services..."
    kill $SERVER_PID 2>/dev/null || true
    kill $REVERB_PID 2>/dev/null || true
    kill $HORIZON_PID 2>/dev/null || true
    # Give processes a moment to exit gracefully
    sleep 1
    print_status "All services stopped"
    exit 0
}

# Set trap to cleanup on Ctrl+C and TERM
trap cleanup INT TERM

# Wait for user to stop
wait
