# HubTube — Development Roadmap & Implementation Plan

> Generated 2026-02-13. Each item includes recommended packages, architecture, files to create/modify, and estimated effort.

## 6. Polish Mobile UI & PWA Support / Push Notifications

### Overview
The PWA foundation already exists (manifest.json, sw.js, push notifications, offline page). This task is about polishing the mobile experience.

### Current State (Already Done)
- ✅ `manifest.json` and service worker (`sw.js`)
- ✅ `offline.blade.php` for offline fallback
- ✅ `PushNotificationController` + `PushSubscription` model
- ✅ `usePushNotifications` composable
- ✅ Admin `PwaSettings` Filament page
- ✅ User Settings page with browser push toggle

### Remaining Work

#### A. Mobile Navigation Polish
- **Pull-to-refresh** — on feed pages, pull down to refresh content

#### B. PWA Enhancements
- **Cache strategy refinement** — cache video thumbnails and static assets aggressively, use network-first for API calls
- **Splash screens** — proper splash screen images for iOS/Android

#### C. Push Notification Triggers
Expand push notifications beyond the current setup to trigger on:
- New video from subscribed channel
- Comment reply on your video/comment
- New video uploaded to site needs moderation (Admin only)
- Gift received

**Implementation:** Create a `SendPushNotification` job that accepts a user + payload. Call it from existing event listeners/observers.

#### D. Touch Optimizations
- **44px minimum touch targets** — audit all buttons and links
- **Haptic feedback** — on like/subscribe actions (via Vibration API)

### Recommended Packages
- **`workbox`** (Google) — for advanced service worker caching strategies. Replace the hand-written `sw.js` with Workbox for better cache management.
- **`vite-plugin-pwa`** — auto-generates service worker and manifest from Vite config.

### Estimated Effort: **10–15 hours**

---

## 7. Image/GIF Upload, Processing & Gallery

### Overview
Add support for image and GIF content. Users can upload images/GIFs, create galleries, and view them in a lightbox.

### Recommended Packages
- **`intervention/image`** (v3.x) — PHP image processing (resize, crop, watermark, format conversion). Already the standard for Laravel.
- **`spatie/laravel-medialibrary`** (v11.x) — **Already in composer.json**. Handles file uploads, conversions, responsive images, and media collections.
- **`blurhash`** — Generate blurhash placeholders for progressive image loading.

### Database Changes

**Option A: Separate `images` table (Recommended)**
```
images
├── id
├── user_id (FK → users)
├── uuid (ULID)
├── title (string, nullable)
├── description (text, nullable)
├── file_path (string)
├── thumbnail_path (string, nullable)
├── storage_disk (string, default: 'public')
├── mime_type (string: image/jpeg, image/png, image/gif, image/webp)
├── width (int)
├── height (int)
├── file_size (bigint, bytes)
├── is_animated (boolean, for GIFs/animated WebP)
├── blurhash (string, nullable)
├── privacy (enum: public, private, unlisted)
├── is_approved (boolean, default: true)
├── views_count (int, default: 0)
├── likes_count (int, default: 0)
├── category_id (FK → categories, nullable)
├── tags (json, nullable)
├── published_at (timestamp)
└── timestamps
```

**New: `galleries` table**
```
galleries
├── id
├── user_id (FK → users)
├── title (string)
├── slug (string, unique)
├── description (text, nullable)
├── cover_image_id (FK → images, nullable)
├── privacy (enum: public, private, unlisted)
├── images_count (int, default: 0)
├── views_count (int, default: 0)
├── sort_order (string: manual, newest, oldest)
└── timestamps
```

**New: `gallery_image` pivot table**
```
gallery_image
├── gallery_id (FK → galleries)
├── image_id (FK → images)
├── sort_order (int)
└── timestamps
```

### Processing Pipeline
```
1. User uploads image/GIF via chunked upload (reuse existing chunk upload system)
2. ImageService::process(Image $image):
   a. Validate dimensions and file size
   b. Strip EXIF data (privacy — removes GPS, camera info)
   c. Generate thumbnail (400x300 crop)
   d. Generate responsive variants: small (480w), medium (960w), large (1920w)
   e. Convert to WebP for optimized delivery (keep original)
   f. Generate blurhash placeholder
   g. For GIFs: generate static thumbnail from first frame, keep animated original
   h. Apply watermark if enabled in admin settings
   i. Store on configured disk (local/S3/Wasabi via StorageManager)
3. Update image record with paths and metadata
4. Moderation approval workflow
   a. New images default to `is_approved = false`
   b. Admin can approve/reject images
   c. Approved images are visible to public
   d. Rejected images are deleted
5. Image deletion workflow
   a. Images can be deleted by admin
   b. Deleted images are permanently deleted
```

### Files to Create
| File | Purpose |
|------|---------|
| `app/Models/Image.php` | Eloquent model with scopes, relationships, accessors |
| `app/Models/Gallery.php` | Eloquent model with images() belongsToMany |
| `app/Services/ImageService.php` | Upload handling, processing pipeline, variant generation |
| `app/Http/Controllers/ImageController.php` | CRUD: upload, show, edit, delete |
| `app/Http/Controllers/GalleryController.php` | CRUD: create gallery, add/remove images, reorder |
| `app/Policies/ImagePolicy.php` | Authorization |
| `app/Policies/GalleryPolicy.php` | Authorization |
| `app/Filament/Resources/ImageResource.php` | Admin management |
| `app/Filament/Resources/GalleryResource.php` | Admin management |
| `database/migrations/xxxx_create_images_table.php` | Migration |
| `database/migrations/xxxx_create_galleries_table.php` | Migration |
| `database/migrations/xxxx_create_gallery_image_table.php` | Pivot migration |
| `resources/js/Pages/Images/Index.vue` | Image browse/grid page |
| `resources/js/Pages/Images/Show.vue` | Single image view with lightbox |
| `resources/js/Pages/Images/Upload.vue` | Image upload page (drag-drop, multi-file) |
| `resources/js/Pages/Galleries/Index.vue` | Gallery listing |
| `resources/js/Pages/Galleries/Show.vue` | Gallery view with masonry grid + lightbox |
| `resources/js/Pages/Galleries/Create.vue` | Gallery creation with image picker |
| `resources/js/Components/ImageCard.vue` | Image card component (like VideoCard) |
| `resources/js/Components/ImageCardSkeleton.vue` | Skeleton loader for image cards |
| `resources/js/Components/Lightbox.vue` | Full-screen image viewer with prev/next, zoom, download |
| `resources/js/Components/MasonryGrid.vue` | Pinterest-style masonry layout for galleries |

### Routes
```php
// Image routes
Route::get('/images', [ImageController::class, 'index'])->name('images.index');
Route::get('/image/{image:uuid}', [ImageController::class, 'show'])->name('images.show');
Route::middleware('auth')->group(function () {
    Route::get('/upload/image', [ImageController::class, 'create'])->name('images.create');
    Route::post('/images', [ImageController::class, 'store'])->name('images.store');
    Route::delete('/image/{image}', [ImageController::class, 'destroy'])->name('images.destroy');
});

// Gallery routes
Route::get('/galleries', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/gallery/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');
Route::middleware('auth')->group(function () {
    Route::post('/galleries', [GalleryController::class, 'store'])->name('galleries.store');
    Route::put('/gallery/{gallery}', [GalleryController::class, 'update'])->name('galleries.update');
    Route::delete('/gallery/{gallery}', [GalleryController::class, 'destroy'])->name('galleries.destroy');
});
```

### Frontend Libraries
- **`blurhash`** (npm) — decode blurhash placeholders in the browser

### Estimated Effort: **20–30 hours**

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
| 🔴 1 | **#3 Remove Shorts** | None — do this first to clean the codebase | 2–3h |
| 🟠 2 | **#1 Social Login** | None | 4–6h |
| 🟠 3 | **#2 Auto-Tweet Service** | Twitter API credentials | 6–8h |
| 🟡 4 | **#4 More Ad Placements** | None (extends existing system) | 8–12h |
| 🟡 5 | **#8 Admin Analytics** | None (uses existing data) | 12–18h |
| 🟢 6 | **#5 UI/UX Polish** | None (incremental) | 15–25h |
| 🟢 7 | **#6 Mobile/PWA Polish** | None (incremental) | 10–15h |
| 🔵 8 | **#7 Image/GIF System** | `intervention/image` package | 20–30h |

**Total estimated effort: 77–117 hours**

### Recommended Sprint Plan
- **Sprint 1 (Week 1):** #3 Remove Shorts + #1 Social Login
- **Sprint 2 (Week 2):** #2 Auto-Tweet + #4 Ad Placements (banners)
- **Sprint 3 (Week 3):** #8 Admin Analytics + #4 Ad Placements (native/interstitial)
- **Sprint 4 (Week 4):** #5 UI/UX Polish + #6 Mobile/PWA Polish
- **Sprint 5–6 (Weeks 5–6):** #7 Image/GIF System
