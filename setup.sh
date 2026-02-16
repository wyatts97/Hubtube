#!/bin/bash
# =============================================================================
# HubTube — Production Setup Script
# =============================================================================
# Last updated: 2026-02-15
#
# Run this AFTER cloning the repo on your production server.
# Works with aaPanel, cPanel, Webmin, Plesk, or bare metal.
#
# Usage:
#   cd /path/to/hubtube
#   chmod +x setup.sh
#   sudo bash setup.sh
#
# This script:
#   1. Detects hosting panel and web user
#   2. Creates required directories
#   3. Copies .env.example → .env and generates APP_KEY
#   4. Auto-detects Redis password and updates .env
#   5. Fixes open_basedir restrictions (aaPanel)
#   6. Checks for disabled PHP functions
#   7. Installs Composer dependencies (--no-dev)
#   8. Builds frontend assets (Vite 6 + Vue 3 + Tailwind CSS v4)
#   9. Publishes Filament/Livewire admin panel assets
#  10. Creates storage symlink
#  11. Sets file permissions
#  12. Shows tailored next steps for your hosting panel
#
# Requirements:
#   - PHP 8.2+ with extensions: redis, fileinfo, bcmath, intl, exif
#   - MySQL 8+ or MariaDB 10.11+
#   - Redis server
#   - Node.js 20+ and npm
#   - Composer 2.x
#   - FFmpeg (for video processing — can be installed later)
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

ok()      { echo -e "  ${GREEN}✓${NC} $1"; }
warn()    { echo -e "  ${YELLOW}!${NC} $1"; }
fail()    { echo -e "  ${RED}✗${NC} $1"; }
info()    { echo -e "  ${BLUE}→${NC} $1"; }
section() { echo -e "\n${BOLD}${CYAN}━━━ $1 ━━━${NC}"; }

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

echo -e "\n${BOLD}HubTube Production Setup${NC}"
echo -e "Project: ${PROJECT_DIR}\n"

# ── Detect Environment ───────────────────────────────────────────────────────
section "Environment Detection"

PANEL="none"
WEB_USER="www-data"
WEB_GROUP="www-data"
PHP_BIN="php"

if [ -d "/www/server/panel" ]; then
    PANEL="aapanel"
    WEB_USER="www"
    WEB_GROUP="www"
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;' 2>/dev/null || echo "84")
    if [ -x "/www/server/php/${PHP_VER}/bin/php" ]; then
        PHP_BIN="/www/server/php/${PHP_VER}/bin/php"
    fi
    ok "Detected: aaPanel (web user: www, PHP: $PHP_BIN)"
elif [ -d "/usr/local/cpanel" ] || [ -d "/var/cpanel" ]; then
    PANEL="cpanel"
    WEB_USER="nobody"
    WEB_GROUP="nobody"
    ok "Detected: cPanel (web user: nobody)"
elif [ -d "/etc/webmin" ]; then
    PANEL="webmin"
    ok "Detected: Webmin/Virtualmin (web user: www-data)"
elif [ -d "/usr/local/psa" ] || [ -d "/opt/psa" ]; then
    PANEL="plesk"
    ok "Detected: Plesk"
else
    ok "Detected: Bare metal / manual setup (web user: www-data)"
fi

info "PHP: $($PHP_BIN --version 2>/dev/null | head -1 || echo 'not found')"

# ── Create Directories ───────────────────────────────────────────────────────
section "Directory Structure"

DIRS=(
    "storage/app/public/videos"
    "storage/app/public/thumbnails"
    "storage/app/public/watermarks"
    "storage/app/public/sponsored"
    "storage/app/chunks"
    "storage/app/temp"
    "storage/framework/cache/data"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
    "public/vendor"
)

for dir in "${DIRS[@]}"; do
    mkdir -p "$dir" 2>/dev/null || true
done
ok "All directories created"

# ── Environment File ─────────────────────────────────────────────────────────
section "Environment Configuration"

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        ok "Created .env from .env.example"
    else
        fail ".env.example not found!"
        exit 1
    fi
else
    ok ".env already exists"
fi

# Generate APP_KEY if empty
if grep -q "^APP_KEY=$" .env 2>/dev/null; then
    $PHP_BIN artisan key:generate --force --no-interaction 2>/dev/null
    ok "Generated APP_KEY"
else
    ok "APP_KEY already set"
fi

# ── Auto-detect Redis Password ───────────────────────────────────────────────
section "Redis Configuration"

REDIS_CONFIGS=(
    "/www/server/redis/redis.conf"
    "/etc/redis/redis.conf"
    "/etc/redis.conf"
    "/usr/local/etc/redis/redis.conf"
)

REDIS_PASS=""
for conf in "${REDIS_CONFIGS[@]}"; do
    if [ -r "$conf" ]; then
        REDIS_PASS=$(grep -E '^\s*requirepass\s+' "$conf" 2>/dev/null | awk '{print $2}' | head -1)
        if [ -n "$REDIS_PASS" ]; then
            info "Found Redis password in $conf"
            break
        fi
    fi
done

if [ -n "$REDIS_PASS" ]; then
    CURRENT_PASS=$(grep "^REDIS_PASSWORD=" .env 2>/dev/null | cut -d= -f2 | tr -d '"')
    if [ "$CURRENT_PASS" = "null" ] || [ -z "$CURRENT_PASS" ] || [ "$CURRENT_PASS" != "$REDIS_PASS" ]; then
        sed -i "s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=${REDIS_PASS}/" .env
        ok "Updated REDIS_PASSWORD in .env"
    else
        ok "REDIS_PASSWORD already correct"
    fi
else
    ok "No Redis password detected (using default)"
fi

# Test Redis connection
if command -v redis-cli &>/dev/null; then
    if [ -n "$REDIS_PASS" ]; then
        PONG=$(redis-cli -a "$REDIS_PASS" ping 2>/dev/null)
    else
        PONG=$(redis-cli ping 2>/dev/null)
    fi
    if [ "$PONG" = "PONG" ]; then
        ok "Redis connection: OK"
    else
        warn "Redis not responding — install redis-server or check config"
    fi
fi

# ── Fix open_basedir ─────────────────────────────────────────────────────────
section "PHP Configuration"

USER_INI="public/.user.ini"
if [ -f "$USER_INI" ]; then
    CURRENT_BASEDIR=$(grep "^open_basedir" "$USER_INI" 2>/dev/null | head -1)
    if [ -n "$CURRENT_BASEDIR" ]; then
        if echo "$CURRENT_BASEDIR" | grep -q "/public/" && ! echo "$CURRENT_BASEDIR" | grep -qE ":[^/]*${PROJECT_DIR}[^/]*:"; then
            chattr -i "$USER_INI" 2>/dev/null || true
            sed -i "s|^open_basedir=.*|open_basedir=${PROJECT_DIR}:/tmp/:/proc/|" "$USER_INI"
            ok "Fixed open_basedir to include full project directory"
        else
            ok "open_basedir already includes project root"
        fi
    fi
else
    ok "No .user.ini restriction found"
fi

# Check disabled functions
DISABLED=$(php -i 2>/dev/null | grep "disable_functions" | head -1)
NEEDED_FUNCS=("exec" "shell_exec" "proc_open" "proc_get_status" "proc_close" "putenv" "symlink" "pcntl_signal" "pcntl_alarm" "pcntl_async_signals")
MISSING_FUNCS=()
for func in "${NEEDED_FUNCS[@]}"; do
    if echo "$DISABLED" | grep -qi "$func"; then
        MISSING_FUNCS+=("$func")
    fi
done
if [ ${#MISSING_FUNCS[@]} -gt 0 ]; then
    warn "Disabled PHP functions that HubTube needs: ${MISSING_FUNCS[*]}"
    warn "Remove these from your PHP disabled_functions list in your hosting panel"
    warn "proc_open: Composer, FFmpeg | exec/shell_exec: video processing | pcntl_*: Horizon workers | symlink: storage:link"
else
    ok "All required PHP functions are enabled"
fi

# Check required PHP extensions
REQUIRED_EXTS=("redis" "fileinfo" "bcmath" "intl" "exif")
MISSING_EXTS=()
for ext in "${REQUIRED_EXTS[@]}"; do
    if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
        MISSING_EXTS+=("$ext")
    fi
done
if [ ${#MISSING_EXTS[@]} -gt 0 ]; then
    warn "Missing PHP extensions: ${MISSING_EXTS[*]}"
else
    ok "All required PHP extensions installed"
fi

# ── Composer Dependencies ────────────────────────────────────────────────────
section "PHP Dependencies"

if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    ok "Composer dependencies already installed"
    info "Run 'composer install --no-dev --optimize-autoloader' to update"
else
    if command -v composer &>/dev/null; then
        info "Installing Composer dependencies (production mode)..."
        composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
        ok "Composer dependencies installed"
    else
        fail "Composer not found! Install it:"
        echo "    curl -sS https://getcomposer.org/installer | php"
        echo "    sudo mv composer.phar /usr/local/bin/composer"
    fi
fi

# ── Frontend Build ───────────────────────────────────────────────────────────
section "Frontend Assets (Vite 6 + Vue 3 + Tailwind CSS v4)"

if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
    ok "Frontend already built"
    info "Run 'npm ci && npm run build' to rebuild"
else
    if command -v npm &>/dev/null; then
        info "Installing Node.js dependencies..."
        npm ci --no-audit --no-fund 2>&1 | tail -3
        info "Building frontend..."
        npm run build 2>&1 | tail -5
        ok "Frontend built successfully"
    else
        fail "npm not found. Install Node.js 20+ then run: npm ci && npm run build"
    fi
fi

# ── Admin Panel Assets ───────────────────────────────────────────────────────
section "Admin Panel Assets (Filament 3)"

$PHP_BIN artisan filament:assets 2>/dev/null && ok "Filament assets published" || warn "filament:assets failed"
$PHP_BIN artisan icons:cache 2>/dev/null && ok "Icons cached" || true
$PHP_BIN artisan vendor:publish --tag=laravel-assets --force 2>/dev/null || true

if [ -d "public/vendor/filament" ] || [ -d "public/vendor/livewire" ]; then
    ok "Admin panel assets in place"
else
    warn "Admin panel assets may be missing — the web installer will attempt to publish them"
fi

# ── Storage Link ─────────────────────────────────────────────────────────────
section "Storage"

$PHP_BIN artisan storage:link --force 2>/dev/null
ok "Storage symlink created (public/storage → storage/app/public)"

# ── Permissions ──────────────────────────────────────────────────────────────
section "Permissions"

if [ "$(id -u)" -eq 0 ] || sudo -n true 2>/dev/null; then
    chown -R "${WEB_USER}:${WEB_GROUP}" . 2>/dev/null || true
    chmod -R 755 . 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    chmod 664 .env 2>/dev/null || true
    ok "Permissions set (owner: ${WEB_USER}:${WEB_GROUP})"
else
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true
    chmod 664 .env 2>/dev/null || true
    warn "Run with sudo to set proper ownership: sudo chown -R ${WEB_USER}:${WEB_GROUP} ."
fi

# ── Clear Caches ─────────────────────────────────────────────────────────────
section "Cache"

$PHP_BIN artisan config:clear 2>/dev/null || true
$PHP_BIN artisan cache:clear 2>/dev/null || true
$PHP_BIN artisan view:clear 2>/dev/null || true
$PHP_BIN artisan route:clear 2>/dev/null || true
ok "All caches cleared"

# ── Summary ──────────────────────────────────────────────────────────────────
section "Setup Complete"

echo ""
echo -e "  ${GREEN}${BOLD}HubTube is ready for installation!${NC}"
echo ""
echo -e "  ${BOLD}Next steps:${NC}"
echo -e "  1. Edit ${BOLD}.env${NC} with your database credentials (DB_PASSWORD)"
echo -e "  2. Point your web server root to: ${BOLD}${PROJECT_DIR}/public${NC}"
echo -e "  3. Open your browser and visit your domain to run the web installer"
echo -e "     The installer will: run migrations, seed data, create admin account"
echo ""

if [ "$PANEL" = "aapanel" ]; then
    echo -e "  ${CYAN}${BOLD}aaPanel-Specific Steps:${NC}"
    echo -e "  • Website → Add Site → Root Directory: ${BOLD}${PROJECT_DIR}/public${NC}"
    echo -e "  • Replace Nginx config with the one from PANEL-DEPLOY.md"
    echo -e "  • Disable open_basedir or set to: ${PROJECT_DIR}:/tmp/:/proc/"
    echo -e "  • PHP 8.4 extensions: redis, fileinfo, bcmath, intl, exif, opcache"
    echo -e "  • Remove disabled functions: exec, shell_exec, proc_open, putenv, symlink, pcntl_*"
    echo ""
fi

echo -e "  ${CYAN}${BOLD}Background Services (required after installation):${NC}"
echo -e "  Install Supervisor: ${YELLOW}sudo apt-get install -y supervisor${NC}"
echo ""
echo -e "  ${BOLD}/etc/supervisor/conf.d/hubtube-horizon.conf:${NC}"
echo -e "  ${YELLOW}[program:hubtube-horizon]"
echo -e "  process_name=%(program_name)s"
echo -e "  command=${PHP_BIN} ${PROJECT_DIR}/artisan horizon"
echo -e "  autostart=true"
echo -e "  autorestart=true"
echo -e "  user=${WEB_USER}"
echo -e "  redirect_stderr=true"
echo -e "  stdout_logfile=${PROJECT_DIR}/storage/logs/horizon.log"
echo -e "  stopwaitsecs=3600${NC}"
echo ""
echo -e "  ${BOLD}/etc/supervisor/conf.d/hubtube-reverb.conf:${NC}"
echo -e "  ${YELLOW}[program:hubtube-reverb]"
echo -e "  process_name=%(program_name)s"
echo -e "  command=${PHP_BIN} ${PROJECT_DIR}/artisan reverb:start --host=0.0.0.0 --port=8080"
echo -e "  autostart=true"
echo -e "  autorestart=true"
echo -e "  user=${WEB_USER}"
echo -e "  redirect_stderr=true"
echo -e "  stdout_logfile=${PROJECT_DIR}/storage/logs/reverb.log"
echo -e "  stopwaitsecs=3600${NC}"
echo ""
echo -e "  Then: ${YELLOW}sudo supervisorctl reread && sudo supervisorctl update${NC}"
echo ""
echo -e "  ${CYAN}${BOLD}Cron Job (required):${NC}"
echo -e "  ${YELLOW}* * * * * cd ${PROJECT_DIR} && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1${NC}"
echo ""
echo -e "  ${CYAN}${BOLD}Optional — Meilisearch (faster search):${NC}"
echo -e "  Install: ${YELLOW}curl -L https://install.meilisearch.com | sh${NC}"
echo -e "  Set in .env: ${YELLOW}SCOUT_DRIVER=meilisearch${NC} and ${YELLOW}MEILISEARCH_KEY=your_master_key${NC}"
echo -e "  Then: ${YELLOW}php artisan scout:sync-index-settings && php artisan scout:import 'App\\Models\\Video'${NC}"
echo ""
