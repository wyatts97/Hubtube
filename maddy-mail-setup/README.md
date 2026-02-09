# Maddy Mail Server Setup for HubTube

Self-hosted transactional email server using [Maddy](https://maddy.email) — a single-binary, zero-dependency mail server written in Go.

## Why Maddy?

- **Single binary** (v0.8.2) — no Docker, no Ruby, no Java
- **Built-in DKIM, SPF, DMARC** — critical for adult site email deliverability
- **SMTP + STARTTLS** — Laravel connects directly via `MAIL_MAILER=smtp`
- **Lightweight** — ~30MB RAM, minimal CPU
- **Self-hosted** — no TOS restrictions for adult content

## Quick Start

```bash
# On your production server (Ubuntu 20.04+ / Debian 11+)
sudo bash setup-maddy.sh yourdomain.com

# Or with a custom mail hostname:
sudo bash setup-maddy.sh yourdomain.com mail.yourdomain.com
```

The script will:
1. Install Maddy v0.7.1
2. Obtain a TLS certificate via Let's Encrypt
3. Generate DKIM signing keys
4. Create a `noreply@yourdomain.com` account
5. Configure systemd service
6. Print the DNS records you need to add
7. Print the Laravel `.env` values to use

## Prerequisites

- Ubuntu 20.04+ or Debian 11+ (amd64)
- Root/sudo access
- Domain name with DNS access
- Ports 25 and 587 open (check your firewall/hosting provider)
- Port 80 open temporarily (for Let's Encrypt certificate)

## After Installation

### 1. Add DNS Records

The script prints all required DNS records. You **must** add these before sending emails:

| Type | Name | Value |
|------|------|-------|
| MX | yourdomain.com | `10 mail.yourdomain.com` |
| TXT | yourdomain.com | `v=spf1 mx a:mail.yourdomain.com ~all` |
| TXT | hubtube._domainkey.yourdomain.com | `v=DKIM1; k=rsa; p=<public-key>` |
| TXT | _dmarc.yourdomain.com | `v=DMARC1; p=quarantine; rua=mailto:postmaster@yourdomain.com; pct=100` |
| PTR | (your server IP) | `mail.yourdomain.com` |

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

### 3. Test Deliverability

```bash
# Send a test email from Laravel
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

**Maddy won't start:**
```bash
journalctl -u maddy -n 50
# Common: port 25/587 in use — check: ss -tlnp | grep -E ':(25|587)'
# Common: TLS cert missing — run certbot manually
```

**Emails going to spam:**
1. Verify all DNS records: `dig TXT yourdomain.com`
2. Check DKIM: `dig TXT hubtube._domainkey.yourdomain.com`
3. Test at https://www.mail-tester.com
4. Check blacklists: https://mxtoolbox.com/blacklists.aspx

**Certificate renewal:**
```bash
# Certbot auto-renews, but Maddy needs a reload after
echo '#!/bin/bash
systemctl reload maddy' > /etc/letsencrypt/renewal-hooks/post/maddy.sh
chmod +x /etc/letsencrypt/renewal-hooks/post/maddy.sh
```
