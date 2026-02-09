#!/usr/bin/env bash
#
# Maddy Mail Server Setup Script for HubTube
# -------------------------------------------
# This script installs and configures Maddy as a local SMTP server
# for sending transactional emails (signup verification, payment receipts,
# subscription notifications, withdrawal confirmations, etc.)
#
# Requirements:
#   - Ubuntu 20.04+ / Debian 11+ (amd64 or arm64)
#   - Root or sudo access
#   - A domain name with DNS access (for DKIM/SPF/DMARC)
#
# Usage:
#   sudo bash setup-maddy.sh yourdomain.com [mail.yourdomain.com]
#
# Arguments:
#   $1 - Primary domain (e.g., yourdomain.com)
#   $2 - Mail hostname (optional, defaults to mail.$1)
#

set -euo pipefail

# ── Colors ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; }
info() { echo -e "${CYAN}[i]${NC} $1"; }

# ── Validate arguments ──
if [ $# -lt 1 ]; then
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  Maddy Mail Server Setup for HubTube"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "Usage: sudo bash setup-maddy.sh <domain> [mail-hostname]"
    echo ""
    echo "Examples:"
    echo "  sudo bash setup-maddy.sh example.com"
    echo "  sudo bash setup-maddy.sh example.com mail.example.com"
    echo ""
    echo "Before running this script, you need:"
    echo "  1. A domain name (e.g., example.com)"
    echo "  2. An A record for mail.example.com pointing to this server's IP"
    echo "  3. Ports 25, 80, and 587 accessible from the internet"
    echo "     (port 80 only needed temporarily for TLS certificate)"
    echo ""
    echo "To find your server's public IP: curl -4 ifconfig.me"
    echo ""
    exit 1
fi

if [ "$EUID" -ne 0 ]; then
    err "This script must be run as root (use sudo)."
    exit 1
fi

DOMAIN="$1"
MAIL_HOST="${2:-mail.$DOMAIN}"
MADDY_VERSION="0.8.2"

# Detect architecture
ARCH=$(uname -m)
case "$ARCH" in
    x86_64)  MADDY_ARCH="x86_64" ;;
    aarch64) MADDY_ARCH="aarch64" ;;
    *)
        err "Unsupported architecture: $ARCH (need x86_64 or aarch64)"
        exit 1
        ;;
esac
MADDY_URL="https://github.com/foxcpp/maddy/releases/download/v${MADDY_VERSION}/maddy-${MADDY_VERSION}-${MADDY_ARCH}-linux-musl.tar.zst"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Maddy Mail Server Setup for HubTube"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
info "Domain:        $DOMAIN"
info "Mail hostname: $MAIL_HOST"
info "Architecture:  $ARCH"
echo ""

# ── Pre-flight checks ──
log "Running pre-flight checks..."

# Check if port 25 is blocked by ISP
info "Testing outbound port 25 (SMTP)..."
if timeout 5 bash -c 'echo QUIT | nc -w3 gmail-smtp-in.l.google.com 25' &>/dev/null; then
    log "Port 25 is open — your ISP does not block outbound SMTP."
else
    echo ""
    err "Port 25 appears BLOCKED by your ISP or firewall."
    err "Maddy cannot deliver emails without outbound port 25."
    echo ""
    warn "Common causes:"
    warn "  - Residential ISPs (Comcast, AT&T, etc.) block port 25"
    warn "  - Cloud providers (AWS, GCP) block it by default (request unblock)"
    warn "  - Your server firewall is blocking outbound connections"
    echo ""
    warn "Solutions:"
    warn "  1. Use a VPS provider that allows port 25 (DigitalOcean, Hetzner, OVH)"
    warn "  2. Use an SMTP relay service instead of self-hosting"
    warn "  3. Contact your ISP/provider to unblock port 25"
    echo ""
    read -p "Continue anyway? (y/N): " CONTINUE_BLOCKED
    if [[ ! "$CONTINUE_BLOCKED" =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Check DNS for mail hostname
info "Checking DNS for $MAIL_HOST..."
RESOLVED_IP=$(dig +short "$MAIL_HOST" 2>/dev/null | head -1)
SERVER_IP=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || echo "unknown")

if [ -z "$RESOLVED_IP" ]; then
    echo ""
    warn "DNS record for $MAIL_HOST does not exist yet."
    warn "You need to add an A record at your DNS provider:"
    echo ""
    echo -e "  ${CYAN}$MAIL_HOST  A  $SERVER_IP${NC}"
    echo ""
    warn "Add this record now, then wait 1-5 minutes for propagation."
    warn "You can verify with: dig +short $MAIL_HOST"
    echo ""
    warn "The TLS certificate step will be SKIPPED if DNS isn't ready."
    warn "You can get the certificate later by re-running this script."
    echo ""
    read -p "Continue without DNS? (y/N): " CONTINUE_DNS
    if [[ ! "$CONTINUE_DNS" =~ ^[Yy]$ ]]; then
        exit 1
    fi
    DNS_READY=false
elif [ "$RESOLVED_IP" != "$SERVER_IP" ]; then
    warn "$MAIL_HOST resolves to $RESOLVED_IP but this server is $SERVER_IP"
    warn "Make sure the A record points to this server's IP."
    DNS_READY=true
else
    log "$MAIL_HOST resolves to $RESOLVED_IP (matches this server)."
    DNS_READY=true
fi

# ── Step 1: Install dependencies ──
log "Installing dependencies..."
apt-get update -qq
apt-get install -y -qq wget tar zstd certbot dnsutils netcat-openbsd openssl > /dev/null 2>&1

# ── Step 2: Create maddy user ──
if ! id -u maddy &>/dev/null; then
    log "Creating maddy system user..."
    useradd -r -s /usr/sbin/nologin -d /var/lib/maddy maddy
else
    log "Maddy user already exists."
fi

# ── Step 3: Download and install Maddy ──
if command -v maddy &>/dev/null; then
    INSTALLED_VER=$(maddy version 2>/dev/null | head -1 || echo "unknown")
    warn "Maddy already installed: $INSTALLED_VER"
    warn "Reinstalling to version $MADDY_VERSION..."
fi

log "Downloading Maddy v${MADDY_VERSION} ($MADDY_ARCH)..."
cd /tmp
rm -rf /tmp/maddy*

wget -q --show-progress "$MADDY_URL" -O maddy.tar.zst || {
    err "Failed to download Maddy from:"
    err "  $MADDY_URL"
    err ""
    err "Check https://github.com/foxcpp/maddy/releases for available versions."
    exit 1
}

# Extract archive (zstd → tar → files)
log "Extracting archive..."
zstd -d -f maddy.tar.zst -o maddy.tar 2>/dev/null
tar -xf maddy.tar

# Find and install the binary (archive structure varies by version)
MADDY_BIN=$(find /tmp -maxdepth 3 -name 'maddy' -type f 2>/dev/null | grep -v '\.tar' | head -1)

if [ -z "$MADDY_BIN" ]; then
    err "Could not find maddy binary in extracted archive."
    err "Archive contents:"
    find /tmp/maddy-* -type f 2>/dev/null || echo "  (no files found)"
    err ""
    err "Please report this issue with your OS and architecture ($ARCH)."
    exit 1
fi

cp -f "$MADDY_BIN" /usr/local/bin/maddy
chmod +x /usr/local/bin/maddy

# In v0.8+, maddyctl is merged into the maddy binary.
ln -sf /usr/local/bin/maddy /usr/local/bin/maddyctl

rm -rf /tmp/maddy*

log "Maddy v${MADDY_VERSION} installed to /usr/local/bin/maddy"

# ── Step 4: Create directories ──
mkdir -p /etc/maddy
mkdir -p /var/lib/maddy
mkdir -p /var/log/maddy
mkdir -p /run/maddy
chown -R maddy:maddy /var/lib/maddy /var/log/maddy /run/maddy

# ── Step 5: Obtain TLS certificate ──
CERT_OK=false
if [ -d "/etc/letsencrypt/live/$MAIL_HOST" ]; then
    log "TLS certificate already exists for $MAIL_HOST."
    CERT_OK=true
elif [ "$DNS_READY" = false ]; then
    warn "Skipping TLS certificate — DNS not ready for $MAIL_HOST."
    warn "After adding the DNS A record, get the cert with ONE of these methods:"
    echo ""
    echo -e "  ${CYAN}Method 1 — Standalone (requires port 80 free):${NC}"
    echo "    sudo certbot certonly --standalone -d $MAIL_HOST"
    echo ""
    echo -e "  ${CYAN}Method 2 — DNS challenge (no port 80 needed):${NC}"
    echo "    sudo certbot certonly --manual --preferred-challenges dns -d $MAIL_HOST"
    echo ""
    warn "Then re-run this script to complete setup."
else
    log "Obtaining TLS certificate for $MAIL_HOST..."

    # Check if port 80 is in use
    PORT80_PID=$(ss -tlnp 2>/dev/null | grep ':80 ' | grep -oP 'pid=\K[0-9]+' | head -1 || true)
    PORT80_PROC=""

    if [ -n "$PORT80_PID" ]; then
        PORT80_PROC=$(ps -p "$PORT80_PID" -o comm= 2>/dev/null || echo "unknown")
        echo ""
        warn "Port 80 is in use by: $PORT80_PROC (PID $PORT80_PID)"
        warn "Certbot needs port 80 free for the HTTP challenge."
        echo ""
        echo "Options:"
        echo "  1) Stop $PORT80_PROC temporarily (will restart after)"
        echo "  2) Use DNS challenge instead (no port 80 needed)"
        echo "  3) Skip certificate for now"
        echo ""
        read -p "Choose [1/2/3]: " CERT_CHOICE

        case "$CERT_CHOICE" in
            1)
                info "Stopping $PORT80_PROC..."
                kill "$PORT80_PID" 2>/dev/null || true
                sleep 2
                # Try to stop common services
                systemctl stop apache2 2>/dev/null || true
                systemctl stop nginx 2>/dev/null || true
                systemctl stop httpd 2>/dev/null || true
                snap stop nextcloud 2>/dev/null || true

                # Double-check port 80 is free
                if ss -tlnp 2>/dev/null | grep -q ':80 '; then
                    warn "Port 80 still in use. Trying force kill..."
                    fuser -k 80/tcp 2>/dev/null || true
                    sleep 2
                fi

                certbot certonly --standalone -d "$MAIL_HOST" --non-interactive --agree-tos --register-unsafely-without-email && CERT_OK=true || {
                    warn "Certbot standalone failed. Try DNS challenge instead."
                }

                # Restart services
                systemctl start nginx 2>/dev/null || true
                systemctl start apache2 2>/dev/null || true
                snap start nextcloud 2>/dev/null || true
                ;;
            2)
                echo ""
                info "Running DNS challenge. Certbot will ask you to add a TXT record."
                info "DO NOT press Enter until the TXT record is added and propagated!"
                info "Verify with: dig +short TXT _acme-challenge.$MAIL_HOST"
                echo ""
                certbot certonly --manual --preferred-challenges dns -d "$MAIL_HOST" --agree-tos --register-unsafely-without-email && CERT_OK=true || {
                    warn "Certbot DNS challenge failed."
                }
                ;;
            *)
                warn "Skipping TLS certificate."
                ;;
        esac
    else
        # Port 80 is free, try standalone
        certbot certonly --standalone -d "$MAIL_HOST" --non-interactive --agree-tos --register-unsafely-without-email && CERT_OK=true || {
            warn "Certbot failed. Common causes:"
            warn "  - DNS for $MAIL_HOST doesn't point to this server"
            warn "  - Port 80 is blocked by firewall/router"
            warn "  - You're behind NAT without port forwarding"
            echo ""
            warn "You can get the cert later with DNS challenge:"
            warn "  sudo certbot certonly --manual --preferred-challenges dns -d $MAIL_HOST"
        }
    fi
fi

if [ "$CERT_OK" = false ]; then
    warn ""
    warn "⚠️  No TLS certificate available. Maddy will NOT start without one."
    warn "After obtaining a certificate, re-run this script or start Maddy manually:"
    warn "  sudo systemctl start maddy"
fi

# ── Step 6: Generate configuration ──
log "Writing /etc/maddy/maddy.conf..."
cat > /etc/maddy/maddy.conf << MADDYCONF
# Maddy Mail Server Configuration for HubTube
# Generated by setup-maddy.sh on $(date -u +"%Y-%m-%d %H:%M:%S UTC")

$(hostname) = $MAIL_HOST
primary_domain = $DOMAIN

# TLS certificates (Let's Encrypt)
tls file /etc/letsencrypt/live/$MAIL_HOST/fullchain.pem /etc/letsencrypt/live/$MAIL_HOST/privkey.pem

# Logging
log syslog

# ── Local storage ──
storage.imapsql local_mailboxes {
    driver sqlite3
    dsn /var/lib/maddy/imapsql.db
}

# ── Authentication ──
auth.pass_table local_authdb {
    table sql_table {
        driver sqlite3
        dsn /var/lib/maddy/credentials.db
        table_name passwords
    }
}

# ── Outbound delivery (sending emails) ──
target.remote outbound_delivery {
    limits {
        all rate 20 1s    # Max 20 emails/second globally
        destination rate 5 1s  # Max 5/sec per destination domain
    }

    mx_auth {
        dane
        mtasts {
            cache fs
            fs_dir /var/lib/maddy/mtasts_cache
        }
        local_policy {
            min_tls_level encrypted
            min_mx_level none
        }
    }
}

# ── DKIM signing ──
modify.dkim local_dkim {
    debug no
    domains {primary_domain}
    selector hubtube
    key_path /var/lib/maddy/dkim_keys/{domain}_{selector}.dns_key
    header_canon relaxed
    body_canon relaxed
    sig_expiry 120h  # 5 days
    require_sender_match envelope
    allow_body_subset yes
}

# ── Submission (port 587 — Laravel connects here) ──
smtp tcp://0.0.0.0:587 {
    debug no
    io_debug no

    # Require TLS for authentication
    starttls

    # Require authentication
    auth &local_authdb

    source {primary_domain} {
        check {
            authorize_sender {
                prepare_email &local_authdb
                auth_normalize auto
            }
        }

        modify {
            replace_addr &local_authdb
            use &local_dkim
        }

        deliver_to &outbound_delivery
    }

    default_source {
        reject 501 5.1.8 "Sender domain not allowed"
    }
}

# ── MX (port 25 — receiving bounce notifications) ──
smtp tcp://0.0.0.0:25 {
    debug no
    io_debug no

    source {
        default_destination {
            reject 550 5.1.1 "This server does not accept incoming mail"
        }
    }

    destination {primary_domain} {
        deliver_to &local_mailboxes
    }

    default_destination {
        reject 550 5.1.1 "User not found"
    }
}
MADDYCONF

log "Configuration written."

# ── Step 7: Generate DKIM keys ──
log "Generating DKIM keys..."
mkdir -p /var/lib/maddy/dkim_keys
if [ ! -f "/var/lib/maddy/dkim_keys/${DOMAIN}_hubtube.dns_key" ]; then
    # maddy will auto-generate keys on first run, but we can trigger it
    # by starting briefly or using openssl
    openssl genrsa -out "/var/lib/maddy/dkim_keys/${DOMAIN}_hubtube.key" 2048 2>/dev/null
    openssl rsa -in "/var/lib/maddy/dkim_keys/${DOMAIN}_hubtube.key" \
        -pubout -out "/var/lib/maddy/dkim_keys/${DOMAIN}_hubtube.dns_key" 2>/dev/null
    chown -R maddy:maddy /var/lib/maddy/dkim_keys
    log "DKIM keys generated."
else
    warn "DKIM keys already exist, skipping."
fi

# Extract the public key for DNS
DKIM_PUBKEY=$(grep -v "PUBLIC KEY" "/var/lib/maddy/dkim_keys/${DOMAIN}_hubtube.dns_key" | tr -d '\n')

# ── Step 8: Create systemd service ──
log "Creating systemd service..."
cat > /etc/systemd/system/maddy.service << 'SYSTEMD'
[Unit]
Description=Maddy Mail Server
Documentation=man:maddy(1)
Documentation=man:maddy.conf(5)
Documentation=https://maddy.email
After=network.target

[Service]
Type=notify
NotifyAccess=main
User=maddy
Group=maddy

# Capabilities for binding to privileged ports (25, 587)
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE

ExecStart=/usr/local/bin/maddy -config /etc/maddy/maddy.conf
ExecReload=/bin/kill -USR1 $MAINPID

Restart=on-failure
RestartSec=5s

# Security hardening
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/maddy /var/log/maddy /run/maddy
PrivateTmp=true
NoNewPrivileges=true

# Allow reading TLS certs
ReadOnlyPaths=/etc/letsencrypt

[Install]
WantedBy=multi-user.target
SYSTEMD

systemctl daemon-reload

# ── Step 9: Create HubTube mail account ──
log "Creating HubTube mail account..."
MAIL_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | head -c 32)

# Create the account (v0.8+ uses 'maddy creds' instead of 'maddyctl')
maddy creds create --config /etc/maddy/maddy.conf "noreply@${DOMAIN}" <<< "$MAIL_PASSWORD
$MAIL_PASSWORD" 2>/dev/null || {
    warn "Could not create credentials now (Maddy may need to run first)."
    warn "After starting Maddy, run:"
    warn "  maddy creds create noreply@${DOMAIN}"
}

# ── Step 10: Start Maddy ──
log "Starting Maddy..."
systemctl enable maddy
systemctl start maddy || {
    warn "Maddy failed to start. Check: journalctl -u maddy -n 50"
    warn "Common issues: port 25/587 already in use, TLS cert missing."
}

# ── Step 11: Save credentials ──
CREDS_FILE="/etc/maddy/.hubtube-credentials"
cat > "$CREDS_FILE" << CREDS
# HubTube Mail Credentials
# Generated: $(date -u +"%Y-%m-%d %H:%M:%S UTC")
# ⚠️  Keep this file secure — delete after configuring HubTube

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=noreply@${DOMAIN}
MAIL_PASSWORD=${MAIL_PASSWORD}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@${DOMAIN}
MAIL_FROM_NAME="\${APP_NAME}"
CREDS
chmod 600 "$CREDS_FILE"

# ── Done! Print summary ──
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ Maddy Mail Server Installed!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${CYAN}Laravel .env settings:${NC}"
echo "  MAIL_MAILER=smtp"
echo "  MAIL_HOST=127.0.0.1"
echo "  MAIL_PORT=587"
echo "  MAIL_USERNAME=noreply@${DOMAIN}"
echo "  MAIL_PASSWORD=${MAIL_PASSWORD}"
echo "  MAIL_ENCRYPTION=tls"
echo "  MAIL_FROM_ADDRESS=noreply@${DOMAIN}"
echo ""
echo -e "${CYAN}Credentials saved to:${NC} $CREDS_FILE"
echo ""
echo -e "${YELLOW}━━━ REQUIRED DNS RECORDS ━━━${NC}"
echo ""
echo -e "${CYAN}1. MX Record:${NC}"
echo "   ${DOMAIN}  MX  10  ${MAIL_HOST}"
echo ""
echo -e "${CYAN}2. SPF Record (TXT):${NC}"
echo "   ${DOMAIN}  TXT  \"v=spf1 mx a:${MAIL_HOST} ~all\""
echo ""
echo -e "${CYAN}3. DKIM Record (TXT):${NC}"
echo "   hubtube._domainkey.${DOMAIN}  TXT  \"v=DKIM1; k=rsa; p=${DKIM_PUBKEY}\""
echo ""
echo -e "${CYAN}4. DMARC Record (TXT):${NC}"
echo "   _dmarc.${DOMAIN}  TXT  \"v=DMARC1; p=quarantine; rua=mailto:postmaster@${DOMAIN}; pct=100\""
echo ""
echo -e "${CYAN}5. Reverse DNS (PTR):${NC}"
echo "   Set your server IP's PTR record to: ${MAIL_HOST}"
echo "   (Configure this in your hosting provider's control panel)"
echo ""
echo -e "${YELLOW}━━━ USEFUL COMMANDS ━━━${NC}"
echo ""
echo "  systemctl status maddy        # Check status"
echo "  journalctl -u maddy -f        # View logs"
echo "  systemctl restart maddy       # Restart"
echo "  maddy creds list              # List accounts"
echo "  maddy creds create <user>     # Add account"
echo ""
echo -e "${YELLOW}━━━ DELIVERABILITY TIPS ━━━${NC}"
echo ""
echo "  1. Add ALL DNS records above before sending emails"
echo "  2. Warm up slowly: send 50-100 emails/day for the first week"
echo "  3. Monitor bounces: journalctl -u maddy | grep -i bounce"
echo "  4. Test your setup: https://www.mail-tester.com"
echo "  5. Check blacklists: https://mxtoolbox.com/blacklists.aspx"
echo ""
