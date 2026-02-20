# HubTube — Development Roadmap & Implementation Plan

> Generated 2026-02-13. Each item includes recommended packages, architecture, files to create/modify, and estimated effort.

## 1. Polish Mobile UI & PWA Support / Push Notifications

### Overview
The PWA foundation already exists (manifest.json, sw.js, push notifications, offline page). This task is about polishing the mobile experience.

### Remaining Work

#### A. Mobile Navigation Polish
- **Pull-to-refresh** — on feed pages, pull down to refresh content

#### B. PWA Enhancements
- **Cache strategy refinement** — cache video thumbnails and static assets aggressively, use network-first for API calls

#### C. Push Notification Triggers
Expand push notifications beyond the current setup to trigger on:
- New video from subscribed channel
- Comment reply on your video/comment
- New video uploaded to site needs moderation (Admin only)

**Implementation:** Create a `SendPushNotification` job that accepts a user + payload. Call it from existing event listeners/observers.

#### D. Touch Optimizations
- **44px minimum touch targets** — audit all buttons and links

### Recommended Packages
- **`workbox`** (Google) — for advanced service worker caching strategies. Replace the hand-written `sw.js` with Workbox for better cache management.
- **`vite-plugin-pwa`** — auto-generates service worker and manifest from Vite config.

### Estimated Effort: **10–15 hours**

---

## 8. Better Analytics & Reporting in Admin Panel

### Overview
Build a comprehensive analytics dashboard in the Filament admin panel with charts, trends, and exportable reports.

### Recommended Packages
- **`flowframe/laravel-trend`** — Eloquent-based trend/time-series queries (e.g., "videos uploaded per day for last 30 days"). Lightweight, no external dependencies.
- **`filament/widgets`** — Already included with Filament. Use `StatsOverviewWidget` and `ChartWidget`.
- **`maatwebsite/excel`** (v3.x) — Export reports to CSV/XLSX.

### Analytics Sections

#### A. Overview Dashboard (Enhance existing `Dashboard.php`)
Already has basic stats. Add:
- **Trend charts** — Videos uploaded (7d/30d/90d), New users (7d/30d/90d), Views (7d/30d/90d)
- **Revenue chart** — Wallet transactions over time (deposits, withdrawals, gifts)
- **Top content** — Top 10 videos by views this day/week/month
- **Geographic data** — Views by country (if tracking IP → country)
- **Device breakdown** — Desktop vs Mobile vs Tablet (from User-Agent)

#### B. Video Analytics Page
**New Filament page: `app/Filament/Pages/VideoAnalytics.php`**
- Total views over time (line chart)
- Views by category (pie chart)
- Average watch duration (if tracked)
- Upload volume trend
- Most viewed videos (table with sparklines)
- Videos by status breakdown (processed, pending, failed)

#### C. User Analytics Page
**New Filament page: `app/Filament/Pages/UserAnalytics.php`**
- New registrations over time (line chart)
- Active users (daily/weekly/monthly)
- User retention (cohort analysis — simplified)
- Top uploaders (by video count, by total views)
- User growth rate

#### D. Revenue Analytics Page
**New Filament page: `app/Filament/Pages/RevenueAnalytics.php`**
- Transaction volume over time
- Revenue by type (deposits, gifts, video sales)
- Withdrawal requests trend
- Average transaction value
- Top earners

#### E. Ad Performance (if tracking impressions/clicks)
Add impression/click tracking to the ad system:
- **New migration:** Add `impressions_count` and `clicks_count` to `video_ads` table
- **API endpoint:** `POST /api/ad-impression` and `POST /api/ad-click` (fire-and-forget, queued)
- **Dashboard section:** CTR by ad, revenue by placement, top-performing ads

#### F. Export System
- Add "Export CSV" and "Export PDF" buttons to each analytics page
- Use `maatwebsite/excel` for CSV exports
- Use `barryvdh/laravel-dompdf` for PDF reports (optional)

### Database Changes
**Optional: `analytics_events` table for granular tracking**
```
analytics_events
├── id
├── event_type (string: video_view, page_view, search, ad_impression, ad_click)
├── user_id (nullable)
├── video_id (nullable)
├── ip_hash (string, hashed for privacy)
├── country (string, nullable)
├── device_type (string: desktop, mobile, tablet)
├── referrer (string, nullable)
├── metadata (json, nullable)
├── created_at
```

This enables detailed analytics without relying solely on aggregate counters. Use a **pruning schedule** to delete events older than 90 days to manage table size.

### Files to Create
| File | Purpose |
|------|---------|
| `app/Filament/Pages/VideoAnalytics.php` | Video analytics dashboard |
| `app/Filament/Pages/UserAnalytics.php` | User analytics dashboard |
| `app/Filament/Pages/RevenueAnalytics.php` | Revenue analytics dashboard |
| `app/Filament/Widgets/VideoTrendChart.php` | Filament chart widget |
| `app/Filament/Widgets/UserGrowthChart.php` | Filament chart widget |
| `app/Filament/Widgets/RevenueTrendChart.php` | Filament chart widget |
| `app/Services/AnalyticsService.php` | Centralized analytics queries |
| `app/Http/Middleware/TrackAnalytics.php` | Middleware to log page views (optional) |
| `database/migrations/xxxx_create_analytics_events_table.php` | Migration (optional) |

### Estimated Effort: **12–18 hours**

---

## Priority & Dependency Order

| Priority | Item | Dependencies | Est. Hours |
|----------|------|-------------|------------|
| 🟡 5 | **#1 Admin Analytics** | None (uses existing data) | 12–18h |
| 🟢 6 | **#2 UI/UX Polish** | None (incremental) | 15–25h |
| 🟢 7 | **#3 Mobile/PWA Polish** | None (incremental) | 10–15h |
**Total estimated effort: 77–117 hours**

### Recommended Sprint Plan
- **Sprint 1 (Week 1):** #8 Admin Analytics + #5 UI/UX Polish
- **Sprint 2 (Week 2):** #6 Mobile/PWA Polish
- **Sprint 3 (Week 3):** #7 Image/GIF System
