#!/usr/bin/env bash
# =============================================================================
# HubTube — Server Prerequisites Installer
# =============================================================================
# Checks for and installs all server-side dependencies required to run HubTube.
# Supported OS: Ubuntu 20.04 / 22.04 / 24.04, Debian 11 / 12
#
# Usage:
#   chmod +x prerequisites.sh
#   sudo ./prerequisites.sh
#
# What this script installs (if not already present):
#   - Nginx
#   - PHP 8.4 + required extensions (redis, fileinfo, bcmath, intl, etc.)
#   - Composer (latest)
#   - MariaDB 10.11+
#   - Redis 7+ (required for cache, sessions, queues, Horizon)
#   - Node.js 20 LTS + npm (required for Vite frontend build)
#   - FFmpeg (static build for video transcoding, HLS, watermarks)
#   - Supervisor (for Horizon queue worker + Reverb WebSocket server)
#   - Certbot (Let's Encrypt SSL)
#   - Unzip, curl, git (utilities)
#
# After running this script, see:
#   - PANEL-DEPLOY.md  — Full aaPanel deployment guide
#   - README.md        — General setup and configuration
#   - nginx.example.conf — Production Nginx config template
# =============================================================================

set -euo pipefail

# ── Colors ───────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# ── Helpers ──────────────────────────────────────────────────────────────────
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
ok()      { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail()    { echo -e "${RED}[FAIL]${NC}  $*"; }
section() { echo -e "\n${BOLD}━━━ $* ━━━${NC}"; }

command_exists() { command -v "$1" &>/dev/null; }

version_gte() {
    # Returns 0 if $1 >= $2 (semantic version comparison)
    printf '%s\n%s' "$2" "$1" | sort -V -C
}

# ── Root check ───────────────────────────────────────────────────────────────
if [[ $EUID -ne 0 ]]; then
    fail "This script must be run as root (use sudo)."
    exit 1
fi

# ── OS Detection ─────────────────────────────────────────────────────────────
section "Detecting operating system"

if [[ ! -f /etc/os-release ]]; then
    fail "Cannot detect OS. /etc/os-release not found."
    exit 1
fi

source /etc/os-release

if [[ "$ID" != "ubuntu" && "$ID" != "debian" ]]; then
    fail "Unsupported OS: $ID. This script supports Ubuntu and Debian only."
    exit 1
fi

ok "Detected: $PRETTY_NAME"

# ── Required versions ───────────────────────────────────────────────────────
REQUIRED_PHP="8.4"
REQUIRED_NODE="20"
REQUIRED_MARIADB="10.11"

# ── Update package lists ────────────────────────────────────────────────────
section "Updating package lists"
apt-get update -qq
ok "Package lists updated."

# ── Core utilities ───────────────────────────────────────────────────────────
section "Core utilities (curl, git, unzip, software-properties-common)"

UTILS=(curl git unzip software-properties-common gnupg2 ca-certificates lsb-release apt-transport-https)
MISSING_UTILS=()

for pkg in "${UTILS[@]}"; do
    if ! dpkg -s "$pkg" &>/dev/null; then
        MISSING_UTILS+=("$pkg")
    fi
done

if [[ ${#MISSING_UTILS[@]} -gt 0 ]]; then
    info "Installing: ${MISSING_UTILS[*]}"
    apt-get install -y -qq "${MISSING_UTILS[@]}"
    ok "Core utilities installed."
else
    ok "All core utilities already present."
fi

# ── Nginx ────────────────────────────────────────────────────────────────────
section "Nginx"

if command_exists nginx; then
    NGINX_VER=$(nginx -v 2>&1 | grep -oP '[\d.]+')
    ok "Nginx already installed (v${NGINX_VER})."
else
    info "Installing Nginx..."
    apt-get install -y -qq nginx
    systemctl enable nginx
    systemctl start nginx
    ok "Nginx installed and started."
fi

# ── PHP 8.4 ──────────────────────────────────────────────────────────────────
section "PHP ${REQUIRED_PHP}"

PHP_EXTENSIONS=(
    "php${REQUIRED_PHP}-fpm"
    "php${REQUIRED_PHP}-cli"
    "php${REQUIRED_PHP}-common"
    "php${REQUIRED_PHP}-mysql"
    "php${REQUIRED_PHP}-pgsql"
    "php${REQUIRED_PHP}-sqlite3"
    "php${REQUIRED_PHP}-redis"
    "php${REQUIRED_PHP}-curl"
    "php${REQUIRED_PHP}-gd"
    "php${REQUIRED_PHP}-imagick"
    "php${REQUIRED_PHP}-mbstring"
    "php${REQUIRED_PHP}-xml"
    "php${REQUIRED_PHP}-zip"
    "php${REQUIRED_PHP}-bcmath"
    "php${REQUIRED_PHP}-intl"
    "php${REQUIRED_PHP}-readline"
    "php${REQUIRED_PHP}-tokenizer"
    "php${REQUIRED_PHP}-fileinfo"
    "php${REQUIRED_PHP}-opcache"
)

install_php() {
    info "Adding Ondřej Surý PPA for PHP ${REQUIRED_PHP}..."
    if [[ "$ID" == "ubuntu" ]]; then
        add-apt-repository -y ppa:ondrej/php
    else
        # Debian — use sury.org repo
        curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
        dpkg -i /tmp/debsuryorg-archive-keyring.deb
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
            > /etc/apt/sources.list.d/sury-php.list
    fi
    apt-get update -qq
    info "Installing PHP ${REQUIRED_PHP} and extensions..."
    apt-get install -y -qq "${PHP_EXTENSIONS[@]}"
    ok "PHP ${REQUIRED_PHP} installed with all extensions."
}

if command_exists php; then
    CURRENT_PHP=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    if version_gte "$CURRENT_PHP" "$REQUIRED_PHP"; then
        ok "PHP ${CURRENT_PHP} already installed."
        # Still ensure all extensions are present
        MISSING_EXT=()
        for ext in "${PHP_EXTENSIONS[@]}"; do
            if ! dpkg -s "$ext" &>/dev/null; then
                MISSING_EXT+=("$ext")
            fi
        done
        if [[ ${#MISSING_EXT[@]} -gt 0 ]]; then
            info "Installing missing PHP extensions: ${MISSING_EXT[*]}"
            # Ensure PPA is added first
            if [[ "$ID" == "ubuntu" ]]; then
                add-apt-repository -y ppa:ondrej/php 2>/dev/null || true
            fi
            apt-get update -qq
            apt-get install -y -qq "${MISSING_EXT[@]}"
            ok "Missing extensions installed."
        else
            ok "All PHP extensions present."
        fi
    else
        warn "PHP ${CURRENT_PHP} found but ${REQUIRED_PHP} required."
        install_php
    fi
else
    install_php
fi

# Enable and start PHP-FPM
systemctl enable "php${REQUIRED_PHP}-fpm" 2>/dev/null || true
systemctl start "php${REQUIRED_PHP}-fpm" 2>/dev/null || true

# ── Composer ─────────────────────────────────────────────────────────────────
section "Composer"

# Debug: Check Composer detection
info "Checking for Composer..."
if [[ -x "/usr/local/bin/composer" ]]; then
    info "Found Composer at /usr/local/bin/composer"
    /usr/local/bin/composer --version --no-interaction 2>/dev/null && ok "Composer is executable"
elif command_exists composer; then
    info "Found Composer in PATH"
    composer --version --no-interaction 2>/dev/null && ok "Composer is executable"
else
    warn "Composer not found in PATH or /usr/local/bin"
    info "Current PATH: $PATH"
    info "Trying to add /usr/local/bin to PATH..."
    export PATH="/usr/local/bin:$PATH"
fi

if command_exists composer; then
    # Bypass Composer's root user warning with --no-interaction flag
    COMPOSER_VER=$(composer --version --no-interaction 2>/dev/null | grep -oP '[\d.]+' | head -1)
    ok "Composer already installed (v${COMPOSER_VER})."
else
    info "Installing Composer..."
    
    # Set timeout for downloads (30 seconds)
    TIMEOUT=30
    
    # Method 1: Direct download with curl (most reliable)
    info "Downloading Composer via curl (timeout: ${TIMEOUT}s)..."
    if timeout $TIMEOUT curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php; then
        info "Verifying Composer installer..."
        EXPECTED_CHECKSUM="$(timeout 10 curl -fsSL https://composer.github.io/installer.sig 2>/dev/null)"
        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
        
        if [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]]; then
            info "Installing Composer..."
            php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
            rm -f /tmp/composer-setup.php
            if command_exists composer; then
                ok "Composer installed successfully."
            else
                warn "Installation appeared to succeed but composer not found, trying alternative..."
            fi
        else
            warn "Checksum mismatch, trying alternative method..."
            rm -f /tmp/composer-setup.php
        fi
    else
        warn "Curl download failed or timed out, trying wget..."
    fi
    
    # Method 2: Wget fallback
    if ! command_exists composer; then
        info "Downloading Composer via wget (timeout: ${TIMEOUT}s)..."
        if timeout $TIMEOUT wget -q --timeout=$TIMEOUT https://getcomposer.org/installer -O /tmp/composer-setup.php; then
            info "Verifying Composer installer..."
            EXPECTED_CHECKSUM="$(timeout 10 curl -fsSL https://composer.github.io/installer.sig 2>/dev/null || echo '')"
            ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
            
            if [[ -n "$EXPECTED_CHECKSUM" && "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]]; then
                info "Installing Composer..."
                php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
                rm -f /tmp/composer-setup.php
                if command_exists composer; then
                    ok "Composer installed via wget."
                else
                    warn "Installation appeared to succeed but composer not found."
                fi
            else
                warn "Checksum verification failed, trying without verification..."
                # Install without checksum verification (last resort)
                php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
                rm -f /tmp/composer-setup.php
                if command_exists composer; then
                    ok "Composer installed (verification skipped)."
                else
                    warn "Wget method failed, trying PHP copy method..."
                fi
            fi
        else
            warn "Wget failed or timed out, trying PHP copy method..."
        fi
    fi
    
    # Method 3: PHP copy (original method) with timeout
    if ! command_exists composer; then
        info "Trying PHP copy method (timeout: ${TIMEOUT}s)..."
        cd /tmp
        if timeout $TIMEOUT php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 2>/dev/null; then
            info "Installing via PHP copy method..."
            php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
            rm -f composer-setup.php
            if command_exists composer; then
                ok "Composer installed via PHP copy."
            else
                fail "All Composer installation methods failed!"
                info "Try manual installation:"
                info "  cd /tmp && curl -sS https://getcomposer.org/installer | php"
                info "  sudo mv composer.phar /usr/local/bin/composer"
                exit 1
            fi
        else
            fail "PHP copy method timed out or failed!"
            info "Network issues detected. Try:"
            info "  1. Check internet connection"
            info "  2. Test: curl -I https://getcomposer.org/installer"
            info "  3. Manual install: cd /tmp && curl -sS https://getcomposer.org/installer | php"
            exit 1
        fi
    fi
fi

# ── MariaDB ──────────────────────────────────────────────────────────────────
section "MariaDB"

if command_exists mariadb || command_exists mysql; then
    if command_exists mariadb; then
        DB_VER=$(mariadb --version 2>/dev/null | grep -oP '[\d.]+' | head -1)
    else
        DB_VER=$(mysql --version 2>/dev/null | grep -oP '[\d.]+' | head -1)
    fi
    ok "MariaDB/MySQL already installed (v${DB_VER})."
else
    info "Installing MariaDB..."
    apt-get install -y -qq mariadb-server mariadb-client
    systemctl enable mariadb
    systemctl start mariadb
    ok "MariaDB installed and started."
    warn "Run 'sudo mysql_secure_installation' to secure your database."
fi

# ── Redis ────────────────────────────────────────────────────────────────────
section "Redis"

if command_exists redis-server; then
    REDIS_VER=$(redis-server --version | grep -oP '[\d.]+' | head -1)
    ok "Redis already installed (v${REDIS_VER})."
else
    info "Installing Redis..."
    apt-get install -y -qq redis-server
    systemctl enable redis-server
    systemctl start redis-server
    ok "Redis installed and started."
fi

# Verify phpredis extension is loaded
if php -m 2>/dev/null | grep -qi redis; then
    ok "PHP redis extension (phpredis) loaded."
else
    warn "PHP redis extension not loaded. Attempting install..."
    apt-get install -y -qq "php${REQUIRED_PHP}-redis"
    systemctl restart "php${REQUIRED_PHP}-fpm" 2>/dev/null || true
    if php -m 2>/dev/null | grep -qi redis; then
        ok "phpredis extension installed and loaded."
    else
        fail "Could not load phpredis. Check php.ini configuration."
    fi
fi

# ── Node.js 20 LTS + npm ────────────────────────────────────────────────────
section "Node.js ${REQUIRED_NODE} LTS + npm"

install_node() {
    info "Installing Node.js ${REQUIRED_NODE}.x via NodeSource..."
    # Remove any old nodesource list
    rm -f /etc/apt/sources.list.d/nodesource.list 2>/dev/null || true
    rm -f /etc/apt/keyrings/nodesource.gpg 2>/dev/null || true

    mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg

    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${REQUIRED_NODE}.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list

    apt-get update -qq
    apt-get install -y -qq nodejs
    ok "Node.js $(node -v) and npm $(npm -v) installed."
}

if command_exists node; then
    NODE_VER=$(node -v | tr -d 'v' | cut -d. -f1)
    if [[ "$NODE_VER" -ge "$REQUIRED_NODE" ]]; then
        ok "Node.js $(node -v) already installed."
        if command_exists npm; then
            ok "npm $(npm -v) available."
        else
            warn "npm not found. Reinstalling Node.js..."
            install_node
        fi
    else
        warn "Node.js v${NODE_VER} found but v${REQUIRED_NODE}+ required."
        install_node
    fi
else
    install_node
fi

# ── FFmpeg ───────────────────────────────────────────────────────────────────
section "FFmpeg (Static)"

FFMPEG_STATIC_URL="https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz"
FFMPEG_BIN="/usr/local/bin/ffmpeg"
FFPROBE_BIN="/usr/local/bin/ffprobe"

install_static_ffmpeg() {
    info "Downloading static FFmpeg..."
    tmp_dir=$(mktemp -d)
    curl -sSL "$FFMPEG_STATIC_URL" -o "$tmp_dir/ffmpeg-static.tar.xz"
    tar -xf "$tmp_dir/ffmpeg-static.tar.xz" -C "$tmp_dir"
    static_dir=$(find "$tmp_dir" -maxdepth 1 -type d -name "ffmpeg-*-static" | head -1)

    if [[ -z "$static_dir" ]]; then
        fail "Failed to extract static FFmpeg archive."
        rm -rf "$tmp_dir"
        return 1
    fi

    install -m 0755 "$static_dir/ffmpeg" "$FFMPEG_BIN"
    install -m 0755 "$static_dir/ffprobe" "$FFPROBE_BIN"
    rm -rf "$tmp_dir"

    ok "Static FFmpeg installed to $FFMPEG_BIN"
}

if [[ -x "$FFMPEG_BIN" ]]; then
    FFMPEG_VER=$($FFMPEG_BIN -version 2>/dev/null | head -1 | grep -oP '[\d.]+' | head -1)
    ok "Static FFmpeg already installed (v${FFMPEG_VER})."
else
    install_static_ffmpeg || {
        warn "Static FFmpeg install failed; falling back to apt package."
        apt-get install -y -qq ffmpeg
    }
fi

# Ensure CLI calls to `ffmpeg` work (PATH typically includes /usr/local/bin)
if [[ ! -x "/usr/bin/ffmpeg" && -x "$FFMPEG_BIN" ]]; then
    ln -sf "$FFMPEG_BIN" /usr/bin/ffmpeg
fi

# ── Supervisor ───────────────────────────────────────────────────────────────
section "Supervisor (for Horizon / queue workers)"

if command_exists supervisord; then
    ok "Supervisor already installed."
else
    info "Installing Supervisor..."
    apt-get install -y -qq supervisor
    systemctl enable supervisor
    systemctl start supervisor
    ok "Supervisor installed and started."
fi

# ── Certbot (Let's Encrypt) ─────────────────────────────────────────────────
section "Certbot (Let's Encrypt SSL)"

if command_exists certbot; then
    ok "Certbot already installed."
else
    info "Installing Certbot with Nginx plugin..."
    apt-get install -y -qq certbot python3-certbot-nginx
    ok "Certbot installed."
fi

# ── PHP Configuration Tuning ────────────────────────────────────────────────
section "PHP configuration tuning"

PHP_INI="/etc/php/${REQUIRED_PHP}/fpm/php.ini"
if [[ -f "$PHP_INI" ]]; then
    # Increase limits for video uploads
    declare -A PHP_SETTINGS=(
        ["upload_max_filesize"]="2G"
        ["post_max_size"]="2G"
        ["memory_limit"]="512M"
        ["max_execution_time"]="600"
        ["max_input_time"]="600"
        ["max_file_uploads"]="20"
    )

    CHANGED=false
    for key in "${!PHP_SETTINGS[@]}"; do
        val="${PHP_SETTINGS[$key]}"
        if grep -q "^${key}" "$PHP_INI"; then
            sed -i "s/^${key}.*/${key} = ${val}/" "$PHP_INI"
        elif grep -q "^;${key}" "$PHP_INI"; then
            sed -i "s/^;${key}.*/${key} = ${val}/" "$PHP_INI"
        else
            echo "${key} = ${val}" >> "$PHP_INI"
        fi
        CHANGED=true
    done

    if $CHANGED; then
        systemctl restart "php${REQUIRED_PHP}-fpm"
        ok "PHP-FPM configured: upload_max=2G, memory=512M, max_execution=600s."
    fi
else
    warn "PHP-FPM php.ini not found at ${PHP_INI}. Skipping tuning."
fi

# Also apply to CLI php.ini
PHP_CLI_INI="/etc/php/${REQUIRED_PHP}/cli/php.ini"
if [[ -f "$PHP_CLI_INI" ]]; then
    sed -i "s/^memory_limit.*/memory_limit = 512M/" "$PHP_CLI_INI" 2>/dev/null || true
    sed -i "s/^max_execution_time.*/max_execution_time = 0/" "$PHP_CLI_INI" 2>/dev/null || true
fi

# ── Summary ──────────────────────────────────────────────────────────────────
section "Installation Summary"

echo ""
printf "  %-24s %s\n" "Component" "Version / Status"
printf "  %-24s %s\n" "────────────────────────" "──────────────────────"

check_ver() {
    local name="$1" cmd="$2"
    if command_exists "$cmd"; then
        local ver
        case "$cmd" in
            php)       ver=$(php -v | head -1 | grep -oP '[\d.]+' | head -1) ;;
            composer)  ver=$(composer --version 2>/dev/null | grep -oP '[\d.]+' | head -1) ;;
            node)      ver=$(node -v | tr -d 'v') ;;
            npm)       ver=$(npm -v) ;;
            nginx)     ver=$(nginx -v 2>&1 | grep -oP '[\d.]+') ;;
            mariadb)   ver=$(mariadb --version 2>/dev/null | grep -oP '[\d.]+' | head -1) ;;
            mysql)     ver=$(mysql --version 2>/dev/null | grep -oP '[\d.]+' | head -1) ;;
            redis-server) ver=$(redis-server --version | grep -oP '[\d.]+' | head -1) ;;
            ffmpeg)    ver=$(ffmpeg -version 2>/dev/null | head -1 | grep -oP '[\d.]+' | head -1) ;;
            supervisord) ver="installed" ;;
            certbot)   ver=$(certbot --version 2>/dev/null | grep -oP '[\d.]+' | head -1) ;;
        esac
        printf "  ${GREEN}✓${NC} %-22s %s\n" "$name" "${ver:-installed}"
    else
        printf "  ${RED}✗${NC} %-22s %s\n" "$name" "NOT FOUND"
    fi
}

check_ver "Nginx"          nginx
check_ver "PHP"            php
check_ver "Composer"       composer
check_ver "Node.js"        node
check_ver "npm"            npm
if command_exists mariadb; then
    check_ver "MariaDB"    mariadb
else
    check_ver "MySQL"      mysql
fi
check_ver "Redis"          redis-server
check_ver "FFmpeg"         ffmpeg
check_ver "Supervisor"     supervisord
check_ver "Certbot"        certbot

# PHP extensions check
echo ""
info "PHP Extensions:"
REQUIRED_MODS=(redis curl gd mbstring xml zip bcmath intl pdo_mysql opcache fileinfo imagick)
ALL_MODS_OK=true
for mod in "${REQUIRED_MODS[@]}"; do
    if php -m 2>/dev/null | grep -qi "^${mod}$"; then
        printf "  ${GREEN}✓${NC} %s\n" "$mod"
    else
        printf "  ${RED}✗${NC} %s ${RED}(missing)${NC}\n" "$mod"
        ALL_MODS_OK=false
    fi
done

echo ""
echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if $ALL_MODS_OK; then
    echo -e "${GREEN}${BOLD}All prerequisites are installed and ready!${NC}"
else
    echo -e "${YELLOW}${BOLD}Some PHP extensions may be missing. Check above.${NC}"
fi

echo ""
echo -e "${CYAN}Next steps:${NC}"
echo "  1. Clone HubTube:       git clone <repo-url> /var/www/hubtube"
echo "  2. Run setup script:    cd /var/www/hubtube && bash setup.sh"
echo "     OR do it manually:"
echo "  3. Copy env file:       cp .env.example .env"
echo "  4. Install PHP deps:    composer install --no-dev --optimize-autoloader"
echo "  5. Install JS deps:     npm ci && npm run build"
echo "  6. Generate app key:    php artisan key:generate"
echo "  7. Create database:     See README.md Step 3"
echo "  8. Configure Nginx:     cp nginx.example.conf /etc/nginx/sites-available/hubtube"
echo "  9. Fix permissions:     chown -R www-data:www-data /var/www/hubtube"
echo "  10. Run web installer:  Visit http://yourdomain.com/install"
echo "  11. Get SSL cert:       sudo certbot --nginx -d yourdomain.com"
echo "  12. Setup Supervisor:   Configure Horizon + Reverb workers (see below)"
echo "  13. Setup cron:         * * * * * cd /var/www/hubtube && php artisan schedule:run"
echo "  14. Secure MariaDB:     sudo mysql_secure_installation"
echo ""
echo -e "${CYAN}For aaPanel users, see PANEL-DEPLOY.md for a complete walkthrough.${NC}"
echo ""
echo -e "${CYAN}Supervisor config for Horizon (/etc/supervisor/conf.d/hubtube-horizon.conf):${NC}"
cat <<'SUPERVISOR'
  [program:hubtube-horizon]
  process_name=%(program_name)s
  command=php /var/www/hubtube/artisan horizon
  autostart=true
  autorestart=true
  user=www-data
  redirect_stderr=true
  stdout_logfile=/var/www/hubtube/storage/logs/horizon.log
  stopwaitsecs=3600
SUPERVISOR
echo ""
echo -e "${CYAN}Supervisor config for Reverb (/etc/supervisor/conf.d/hubtube-reverb.conf):${NC}"
cat <<'REVERB'
  [program:hubtube-reverb]
  process_name=%(program_name)s
  command=php /var/www/hubtube/artisan reverb:start --host=0.0.0.0 --port=8080
  autostart=true
  autorestart=true
  user=www-data
  redirect_stderr=true
  stdout_logfile=/var/www/hubtube/storage/logs/reverb.log
  stopwaitsecs=3600
REVERB
echo ""
ok "Prerequisites script complete."
