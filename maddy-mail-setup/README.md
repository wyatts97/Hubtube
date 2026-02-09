# Maddy Mail Server Setup for HubTube

Self-hosted transactional email server using [Maddy](https://maddy.email) — a single-binary, zero-dependency mail server written in Go.

## Why Maddy?

- **Single binary** (v0.8.2) — no Docker, no Ruby, no Java
- **Built-in DKIM, SPF, DMARC** — critical for adult site email deliverability
- **SMTP + STARTTLS** — Laravel connects directly via `MAIL_MAILER=smtp`
- **Lightweight** — ~30MB RAM, minimal CPU
- **Self-hosted** — no TOS restrictions for adult content

## Before You Start (Checklist)

Before running the setup script, make sure you have:

- [ ] **A domain name** (e.g., `yourdomain.com`) with access to DNS settings
- [ ] **A server with a public IP** (VPS recommended — residential IPs often block port 25)
- [ ] **Ubuntu 20.04+** or Debian 11+ (amd64 or arm64)
- [ ] **Root/sudo access** to the server

### Check if your ISP blocks port 25

Most residential ISPs (Comcast, AT&T, Spectrum, etc.) **block port 25**, which prevents your server from sending emails. Run this test:

```bash
timeout 5 bash -c 'echo QUIT | nc gmail-smtp-in.l.google.com 25'
```

- **If you see `220 mx.google.com`** → Port 25 is open, you're good!
- **If it hangs or says "connection refused"** → Port 25 is blocked. You need a VPS or SMTP relay.

### Find your server's public IP

```bash
curl -4 ifconfig.me
```

### Add DNS A record FIRST

Go to your DNS provider (Cloudflare, GoDaddy, Namecheap, etc.) and add:

| Type | Name | Value |
|------|------|-------|
| **A** | `mail` | `<your-server-IP>` |

Wait 1-5 minutes, then verify:
```bash
dig +short mail.yourdomain.com
# Should return your server's IP
```

## Quick Start

```bash
# On your production server
cd ~/hubtube
sudo bash maddy-mail-setup/setup-maddy.sh yourdomain.com

# Or with a custom mail hostname:
sudo bash maddy-mail-setup/setup-maddy.sh yourdomain.com mail.yourdomain.com
```

The script will:
1. Check port 25 and DNS (warns you if something is wrong)
2. Install Maddy v0.8.2
3. Handle TLS certificate (auto-detects port 80 conflicts, offers DNS challenge)
4. Generate DKIM signing keys
5. Create a `noreply@yourdomain.com` account
6. Configure and start the systemd service
7. Print the DNS records and Laravel `.env` values you need

## After Installation

### 1. Add DNS Records

The script prints all required DNS records. You **must** add these before sending emails:

| Type | Name | Value |
|------|------|-------|
| **MX** | `yourdomain.com` | `10 mail.yourdomain.com` |
| **TXT** | `yourdomain.com` | `v=spf1 mx a:mail.yourdomain.com ~all` |
| **TXT** | `hubtube._domainkey.yourdomain.com` | `v=DKIM1; k=rsa; p=<public-key>` |
| **TXT** | `_dmarc.yourdomain.com` | `v=DMARC1; p=quarantine; rua=mailto:postmaster@yourdomain.com; pct=100` |
| **PTR** | (your server IP) | `mail.yourdomain.com` |

> **PTR records** are set in your hosting provider's control panel (not your DNS provider). Look for "Reverse DNS" or "rDNS" settings.

### 2. Update HubTube `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=<generated-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

Or configure these during HubTube installation (Step 3: Application Settings).

The generated password is saved to `/etc/maddy/.hubtube-credentials` on the server.

### 3. Test Deliverability

```bash
# Send a test email from Laravel
cd ~/hubtube
php artisan tinker
> Mail::raw('Test from HubTube', fn($m) => $m->to('your@email.com')->subject('Test'));

# Check mail-tester.com score
# Send to the address they give you and check your score (aim for 9+/10)
```

## Management Commands

```bash
systemctl status maddy          # Check status
systemctl restart maddy         # Restart
journalctl -u maddy -f          # Live logs
journalctl -u maddy | grep -i bounce  # Check bounces

maddy creds list                # List mail accounts
maddy creds create user@domain  # Add account
maddy creds remove user@domain  # Remove account
```

## Email Warmup Plan (Important for Adult Sites)

Email providers are stricter with adult content senders. Follow this warmup schedule:

| Week | Daily Volume | Notes |
|------|-------------|-------|
| 1 | 50-100 | Only transactional (signups, verifications) |
| 2 | 200-500 | Add payment receipts |
| 3 | 500-1000 | Add subscription notifications |
| 4+ | Full volume | Monitor bounce rates, stay under 2% |

## Troubleshooting

### Maddy won't start

```bash
# Check the error
journalctl -u maddy -n 50
```

**Port 25 or 587 already in use:**
```bash
# Find what's using the port
sudo ss -tlnp | grep -E ':(25|587)'
# Kill it or stop the service
sudo fuser -k 25/tcp
```

**TLS certificate missing:**
```bash
# Check if cert exists
ls /etc/letsencrypt/live/mail.yourdomain.com/

# If not, get one (choose ONE method):

# Method 1: Standalone (port 80 must be free)
sudo systemctl stop nginx  # or apache2
sudo certbot certonly --standalone -d mail.yourdomain.com
sudo systemctl start nginx

# Method 2: DNS challenge (works even behind NAT/firewall)
sudo certbot certonly --manual --preferred-challenges dns -d mail.yourdomain.com
# Add the TXT record it shows, wait 1 min, THEN press Enter
```

### Certbot hangs or fails

**"Could not bind TCP port 80"** — Something else is using port 80:
```bash
# Find what's on port 80
sudo ss -tlnp | grep :80

# Common culprits and how to stop them:
sudo systemctl stop nginx       # Nginx
sudo systemctl stop apache2     # Apache (Ubuntu/Debian)
sudo snap stop nextcloud        # Nextcloud snap (uses bundled Apache)
sudo fuser -k 80/tcp            # Force kill anything on port 80
```

**"Timeout during connect"** — Port 80 is blocked by firewall or not forwarded:
- If behind a **router/NAT**: Forward port 80 to your server's local IP
- If using a **cloud firewall**: Open port 80 in security group
- **Workaround**: Use DNS challenge instead (no port 80 needed):
  ```bash
  sudo certbot certonly --manual --preferred-challenges dns -d mail.yourdomain.com
  ```

**"NXDOMAIN" or DNS error** — DNS record doesn't exist or hasn't propagated:
```bash
# Check if DNS resolves
dig +short mail.yourdomain.com

# If empty, add the A record and wait. Verify before retrying certbot.
```

### Port 25 blocked by ISP

If `timeout 5 bash -c 'echo QUIT | nc gmail-smtp-in.l.google.com 25'` hangs:
- **Residential ISPs** almost always block port 25. Use a VPS instead.
- **AWS/GCP**: Request port 25 unblock from support.
- **Alternative**: Use an SMTP relay (Mailgun, Amazon SES) instead of self-hosting.

### Emails going to spam

1. **Verify ALL DNS records are set:**
   ```bash
   dig MX yourdomain.com
   dig TXT yourdomain.com
   dig TXT hubtube._domainkey.yourdomain.com
   dig TXT _dmarc.yourdomain.com
   ```
2. **Test your setup:** https://www.mail-tester.com (aim for 9+/10)
3. **Check blacklists:** https://mxtoolbox.com/blacklists.aspx
4. **Check PTR record:** `dig -x <your-server-ip>` (should return `mail.yourdomain.com`)

### Certificate renewal

Certbot auto-renews certificates, but Maddy needs a reload after renewal:

```bash
# Set up auto-reload (run once)
echo '#!/bin/bash
systemctl reload maddy' | sudo tee /etc/letsencrypt/renewal-hooks/post/maddy.sh
sudo chmod +x /etc/letsencrypt/renewal-hooks/post/maddy.sh
```

> **Note:** If you used `--manual` DNS challenge, auto-renewal won't work. You'll need to manually renew before expiry (every 90 days) or switch to a plugin-based DNS challenge.

### Port forwarding (home servers)

If your server is behind a home router, you need to forward these ports:

| Port | Protocol | Purpose |
|------|----------|---------|
| 25 | TCP | SMTP (receiving mail / bounce notifications) |
| 80 | TCP | HTTP (certbot renewal only) |
| 587 | TCP | SMTP Submission (sending mail) |

Steps:
1. Find your server's local IP: `ip addr show | grep "inet 192"`
2. Log into your router (usually `192.168.1.1` in a browser)
3. Find "Port Forwarding" or "NAT" settings
4. Forward each port above to your server's local IP
