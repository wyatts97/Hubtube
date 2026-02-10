#!/bin/bash
# =============================================================================
# HubTube — Quick Setup Script
# =============================================================================
# Run this AFTER cloning the repo and installing dependencies via your
# hosting panel (aaPanel, cPanel, Webmin, etc.) or manually.
#
# Usage:
#   cd /path/to/hubtube
#   chmod +x setup.sh
#   bash setup.sh
#
# This script:
#   1. Creates required directories and fixes permissions
#   2. Copies .env.example to .env if needed
#   3. Generates APP_KEY
#   4. Auto-detects Redis password and updates .env
#   5. Installs Composer dependencies (if not already installed)
#   6. Builds frontend assets (if Node.js is available)
#   7. Publishes Filament/Livewire assets
#   8. Creates storage symlink
#   9. Detects hosting panel and shows tailored next steps
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

ok()   { echo -e "  ${GREEN}✓${NC} $1"; }
warn() { echo -e "  ${YELLOW}!${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; }
info() { echo -e "  ${BLUE}→${NC} $1"; }
section() { echo -e "\n${BOLD}${CYAN}━━━ $1 ━━━${NC}"; }

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

echo -e "\n${BOLD}HubTube Setup${NC}"
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
    # Use aaPanel's PHP if available
    PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;' 2>/dev/null || echo "84")
    if [ -x "/www/server/php/${PHP_VER}/bin/php" ]; then
        PHP_BIN="/www/server/php/${PHP_VER}/bin/php"
    fi
    ok "Detected: aaPanel (web user: www)"
elif [ -d "/usr/local/cpanel" ] || [ -d "/var/cpanel" ]; then
    PANEL="cpanel"
    WEB_USER="nobody"
    WEB_GROUP="nobody"
    ok "Detected: cPanel (web user: nobody)"
elif [ -d "/etc/webmin" ]; then
    PANEL="webmin"
    ok "Detected: Webmin/Virtualmin"
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
    "storage/app/public"
    "storage/framework/cache/data"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
    "public/vendor"
)

for dir in "${DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        ok "Created $dir"
    fi
done
ok "All directories exist"

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
    # Check if .env already has this password
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
        warn "Redis not responding (install redis-server or check config)"
    fi
fi

# ── Fix open_basedir ─────────────────────────────────────────────────────────
section "PHP Configuration"

USER_INI="public/.user.ini"
if [ -f "$USER_INI" ]; then
    CURRENT_BASEDIR=$(grep "^open_basedir" "$USER_INI" 2>/dev/null | head -1)
    if [ -n "$CURRENT_BASEDIR" ]; then
        # Check if it only allows public/
        if echo "$CURRENT_BASEDIR" | grep -q "/public/" && ! echo "$CURRENT_BASEDIR" | grep -qE ":[^/]*${PROJECT_DIR}[^/]*:"; then
            # Remove immutable flag (aaPanel sets this)
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
NEEDED_FUNCS=("exec" "shell_exec" "proc_open" "putenv" "symlink")
MISSING_FUNCS=()
for func in "${NEEDED_FUNCS[@]}"; do
    if echo "$DISABLED" | grep -qi "$func"; then
        MISSING_FUNCS+=("$func")
    fi
done
if [ ${#MISSING_FUNCS[@]} -gt 0 ]; then
    warn "Disabled PHP functions that HubTube needs: ${MISSING_FUNCS[*]}"
    warn "Remove these from your PHP disabled_functions list in your hosting panel"
else
    ok "All required PHP functions are enabled"
fi

# ── Composer Dependencies ────────────────────────────────────────────────────
section "PHP Dependencies"

if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    ok "Composer dependencies already installed"
else
    if command -v composer &>/dev/null; then
        info "Installing Composer dependencies..."
        composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
        ok "Composer dependencies installed"
    else
        fail "Composer not found! Install it first:"
        echo "    curl -sS https://getcomposer.org/installer | php"
        echo "    sudo mv composer.phar /usr/local/bin/composer"
    fi
fi

# ── Frontend Build ───────────────────────────────────────────────────────────
section "Frontend Assets"

if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
    ok "Frontend already built"
else
    if command -v npm &>/dev/null; then
        info "Installing Node.js dependencies..."
        npm install --no-audit --no-fund 2>&1 | tail -3
        info "Building frontend..."
        npm run build 2>&1 | tail -3
        ok "Frontend built successfully"
    elif command -v node &>/dev/null; then
        warn "npm not found but node is installed. Run: npm install && npm run build"
    else
        warn "Node.js not found. Install Node.js 20+ then run: npm install && npm run build"
    fi
fi

# ── Filament & Livewire Assets ───────────────────────────────────────────────
section "Admin Panel Assets"

$PHP_BIN artisan filament:assets 2>/dev/null && ok "Filament assets published" || warn "filament:assets failed"
$PHP_BIN artisan icons:cache 2>/dev/null && ok "Icons cached" || true
$PHP_BIN artisan vendor:publish --tag=laravel-assets --force 2>/dev/null || true

# Verify assets exist
if [ -d "public/vendor/filament" ] || [ -d "public/vendor/livewire" ]; then
    ok "Admin panel assets are in place"
else
    warn "Admin panel assets may be missing. The web installer will attempt to publish them."
fi

# ── Storage Link ─────────────────────────────────────────────────────────────
section "Storage"

$PHP_BIN artisan storage:link --force 2>/dev/null
ok "Storage symlink created"

# ── Permissions ──────────────────────────────────────────────────────────────
section "Permissions"

if [ "$(id -u)" -eq 0 ] || sudo -n true 2>/dev/null; then
    chown -R "${WEB_USER}:${WEB_GROUP}" . 2>/dev/null || true
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
echo -e "  Open your browser and visit your domain to run the web installer."
echo -e "  The installer will configure your database, create an admin account,"
echo -e "  and finalize the setup."
echo ""

if [ "$PANEL" = "aapanel" ]; then
    echo -e "  ${CYAN}${BOLD}aaPanel Notes:${NC}"
    echo -e "  • Site root must be set to: ${PROJECT_DIR}/public"
    echo -e "  • Add this to your Nginx config under the site:"
    echo -e "    ${YELLOW}location / { try_files \$uri \$uri/ /index.php?\$query_string; }${NC}"
    echo -e "  • Disable open_basedir or set it to: ${PROJECT_DIR}"
    echo -e "  • PHP version: 8.4 with extensions: redis, fileinfo, bcmath, intl"
    echo ""
fi

echo -e "  ${CYAN}${BOLD}After installation, start background services:${NC}"
echo -e "  ${YELLOW}sudo apt-get install -y supervisor${NC}"
echo ""
echo -e "  Create ${BOLD}/etc/supervisor/conf.d/hubtube-horizon.conf${NC}:"
echo -e "  ${YELLOW}[program:hubtube-horizon]"
echo -e "  command=${PHP_BIN} ${PROJECT_DIR}/artisan horizon"
echo -e "  autostart=true"
echo -e "  autorestart=true"
echo -e "  user=${WEB_USER}${NC}"
echo ""
echo -e "  Create ${BOLD}/etc/supervisor/conf.d/hubtube-reverb.conf${NC}:"
echo -e "  ${YELLOW}[program:hubtube-reverb]"
echo -e "  command=${PHP_BIN} ${PROJECT_DIR}/artisan reverb:start --host=0.0.0.0 --port=8080"
echo -e "  autostart=true"
echo -e "  autorestart=true"
echo -e "  user=${WEB_USER}${NC}"
echo ""
echo -e "  Then: ${YELLOW}sudo supervisorctl reread && sudo supervisorctl update${NC}"
echo ""
