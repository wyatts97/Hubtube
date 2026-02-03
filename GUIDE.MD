# GUIDE.MD: Building Adult Video Tube CMS (Laravel + Vue + MariaDB + Agora Live Streaming)

**Project Goal**  
Build a scalable, feature-rich video-sharing CMS optimized for adult content (user-uploaded videos, channels, monetization, live streaming).  
- Core: Video upload/import, user channels, social features (likes, comments, playlists, subscriptions), monetization (ads, paid videos, channel subs, wallet).  
- Live Streaming: TikTok-style interactive live streams using Agora.io. Users preload wallet, send priced virtual gifts (emojis/custom icons/animations) during streams. Gifts deduct from viewer wallet, credit streamer (with platform cut).  
- Adult Focus: Strong privacy (private/unlisted/GEO-block), age verification gate, 2257 record-keeping placeholders, adult-friendly payments (CCBill, crypto).

**Finalized Tech Stack**  
- **Backend**: Laravel 11+ (PHP 8.2+)  
- **Frontend**: Vue 3 (Composition API) + Inertia.js (for SPA feel without full SPA complexity)  
- **Database**: MariaDB (MySQL-compatible)  
- **Video Processing**: FFmpeg (transcoding, thumbnails, HLS/M3U8 adaptive streaming)  
- **Storage/Streaming**: Wasabi/Backblaze B2 + BunnyCDN or Cloudflare Stream (adult-tolerant)  
- **Real-Time/Live**: Agora.io (RTC for video streaming, RTM for chat & gifts) + Laravel Reverb (WebSockets fallback)  
- **Queue/Background**: Laravel Horizon + Redis  
- **Auth & API**: Laravel Sanctum + Socialite  
- **Admin Panel**: Filament or Laravel Nova  
- **Search**: Laravel Scout + Meilisearch  
- **Payments**: Custom wallet + adult gateways (CCBill/Epoch/Verotel/Crypto)  

**Core Principles for AI Agent**  
- **Follow Laravel Conventions**: Use Eloquent, migrations, seeders, resources, policies, gates.  
- **Ensure Code Correctness Constantly**:  
  - Write unit/feature tests (PHPUnit/Pest) for every major feature. Run `php artisan test` after each major change.  
  - Use static analysis: PHPStan level 8+, Larastan.  
  - Lint: PHP CS Fixer + ESLint/Prettier for Vue.  
  - Type safety: Use TypeScript in Vue where possible.  
  - Validate everything: Requests, inputs, policies for authorization.  
  - Security: Sanctum auth, rate limiting, CSRF, HTTPS-only, sanitize inputs.  
  - Self-review: Before committing code, explain why it works, potential edge cases, and run tests.  
- **Modular & Extensible**: Use Laravel modules/packages where sensible. Separate concerns (e.g., LiveStreamingService).  
- **Performance**: Queue heavy tasks (transcoding, imports). Use caching (Redis).  
- **Adult Compliance Placeholders**: Add middleware for age verification popup/gate. Include fields/models for 2257 records (performer ID proofs). Never bypass legal checks.  
- **Development Flow**: Start with setup → auth → core video → monetization → admin → live streaming last (most complex).  

## 1. Project Setup  
1. `composer create-project laravel/laravel video-cms`  
2. Configure `.env`: DB (MariaDB), APP_URL, broadcasting (Reverb), mail, queue (Redis/Horizon).  
3. Install Inertia + Vue: `composer require inertiajs/inertia-laravel` → `npm install @inertiajs/vue3 vue` → setup `app.js` + `app.blade.php`.  
4. Setup MariaDB: Run migrations.  
5. Install key packages:  
   ```bash
   composer require filament/filament laravel/sanctum laravel/scout meilisearch/laravel predis/predis laravel/horizon
   npm install pinia @inertiajs/progress axios

Publish configs & run initial migrations.

2. Core Features Implementation Order

Auth & Users
Registration, login, social login (Socialite).
User model: Add fields (wallet_balance decimal, channel_name, bio, avatar).
Profiles: Channels with banner, description, subscriber count.
2FA, email verification.
Test: Auth flows, policies.

Video Upload & Management
Direct upload endpoint (chunked for large files).
FFmpeg job: Transcode to multiple qualities, HLS, thumbnails. Queue via Horizon.
Cloud storage: Filesystem driver for Wasabi/B2.
Metadata: Title, desc, tags, hashtags, categories, privacy (public/private/unlisted), GEO-block (country list).
Shorts support (<60s).
Test: Upload, transcoding, streaming playback.

Social Features
Subscriptions, watch history, playlists, likes/dislikes, nested comments (with moderation/pinning).
Notifications (database + realtime via Reverb).
Hashtag search.

Monetization & Wallet
Wallet: Balance, transactions table (deposits, gifts sent/received, earnings).
Adult payments: Integrate CCBill/Epoch APIs for deposits.
Ads: VAST pre-roll, site banners.
Paid videos/rentals, channel subscriptions (recurring).
Pro tiers: Unlimited uploads, badges.
Platform cut (e.g., 20% on gifts).
Test: Deposit, balance updates, transactions.

Admin Panel (Filament)
Resources for users, videos, categories, reports, monetization.
Feature toggles, SEO tools (meta, sitemap), multilingual.
Content moderation queue.


3. Live Streaming with Agora.io (TikTok-Style)
Why Agora: Scalable RTC (video) + RTM (chat/gifts). Handles low-latency live, audience up to thousands.
Setup

Sign up at agora.io → Create project → Get App ID + App Certificate (for token gen).
Install Agora packages:Bashnpm install agora-rtc-sdk-ng agora-rtm-sdk(RTC for video, RTM for chat/gifts)
Token Generation (Security Critical)
Use server-side token builder (avoid exposing App Certificate).
Recommended: cyberdeep/laravel-agora-token-generator or manual from Agora GitHub (RtcTokenBuilder.php).
Endpoint: /api/agora/token?channel=stream123&uid=456&role=host|audience
Generate RTC token (live mode) + RTM token if needed.
Expire tokens (e.g., 24h).
Test: Token validation with Agora tools.

Live Stream Creation
User starts live: Create channel name (e.g., user_id-timestamp).
Set role: Host publishes camera/mic.
Vue component: LiveStream.vue (onMounted: init AgoraRTC client, join channel, create/publish tracks).
Audience: Join as audience, subscribe to host stream.

Virtual Gifts System
Gifts table: id, name, icon (SVG/emoji), price (coins), animation (CSS/ Lottie).
Viewer sends gift:
API: POST /api/live/gift (auth, check balance ≥ price, deduct, credit streamer minus cut, log transaction).
Use RTM: Send channel message {type: "gift", gift_id: 1, sender: username}.
Host/audience clients listen RTM events → show animation (e.g., flying rose emoji with sound).

Realtime: Use RTM for low-latency chat/gifts. Fallback to Laravel Reverb.
Test: Balance deduction, gift broadcast, animation trigger.

Frontend Live UI
Host: Start/End buttons, viewer count, gift leaderboard.
Viewer: Wallet balance, gift buttons (with prices), chat input.
Use Pinia store for Agora client state, local/remote tracks.
Cleanup: Leave channel, stop tracks on unmount.

Best Practices for Live
HTTPS only (Agora requires).
Handle network issues: Re-join logic.
Role switching (host → co-host if multi).
Moderation: Mute/block via Agora APIs or custom.
Analytics: Track viewers, gifts via DB.
Test: Simulate multi-user live, gift spam prevention.


Final Reminders for Agent

After each major feature: Run full test suite, fix failures, lint, analyze.
Commit often with clear messages.
Document new endpoints/models in README or Swagger.
If stuck: Reference Laravel docs, Agora docs, official tutorials.
Prioritize security & performance for adult traffic (high bandwidth, privacy).

Follow this guide sequentially. Ask for clarification only if blocked on logic or external API.
textThis guide is actionable, enforces best practices, and covers the live/gifts feature in depth based on Agora's RTC/RTM capabilities. Your AI agent should be able to follow it step-by-step while self-validating code. Let me know if you want expansions (e.g., specific code skeletons or database schema).