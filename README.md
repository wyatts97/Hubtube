# HubTube - Modern Video Sharing Platform

A feature-rich, scalable video-sharing CMS built with Laravel 11, Vue 3, and Inertia.js. Optimized for modern web standards with PWA capabilities, real-time streaming, and comprehensive monetization features.

##  Key Features

### Core Platform
- **Video Management**: Upload, transcode (FFmpeg), HLS adaptive streaming, thumbnail generation
- **Shorts Support**: TikTok-style vertical video viewer with swipe navigation and ad interstitials
- **User Channels**: Profiles, subscriptions, verification badges, customization
- **Social Features**: Likes, comments, playlists, watch history, notifications
- **Advanced Search**: Full-text search with filters and real-time results

### Live Streaming & Monetization
- **Live Streaming**: Interactive streams using Agora.io with real-time chat
- **Virtual Gifts**: Real-time gift sending during live streams with wallet integration
- **Monetization**: Wallet system, paid content, channel subscriptions, ad revenue
- **Video Scheduling**: Admin/Pro users can schedule video publishing

### Modern Web Features
- **PWA Ready**: Service worker with offline support, push notifications, installable
- **Responsive Design**: Mobile-first with touch-friendly interfaces
- **Dark/Light Theme**: CSS variable-based theming system
- **Multi-language**: i18n framework with 10+ language support
- **Image Optimization**: Responsive srcset, WebP support, lazy loading
- **Error Boundaries**: Graceful error handling with retry functionality

### User Experience
- **Keyboard Shortcuts**: Comprehensive video player controls (Space, arrows, etc.)
- **Playlist Management**: Save videos to playlists with intuitive UI
- **Loading States**: Consistent loading indicators across all interactions
- **Toast Notifications**: Non-intrusive feedback system
- **Accessibility**: Semantic HTML, ARIA labels, keyboard navigation

## 🛠 Tech Stack

### Backend
- **Framework**: Laravel 11+ (PHP 8.2+)
- **Database**: MariaDB 10.6+
- **Queue**: Laravel Horizon + Redis
- **Real-time**: Laravel Reverb + Pusher
- **Search**: Laravel Scout + Meilisearch
- **Admin**: Filament 3

### Frontend
- **Framework**: Vue 3 (Composition API)
- **Routing**: Inertia.js
- **Styling**: Tailwind CSS with custom theming
- **Build Tool**: Vite
- **Icons**: Lucide Vue
- **Video**: HLS.js + Plyr

### Video & Media
- **Processing**: FFmpeg
- **Streaming**: HLS adaptive bitrate
- **Storage**: Cloud storage (Wasabi/Backblaze B2)
- **CDN**: BunnyCDN integration
- **Live**: Agora.io (RTC/RTM)

### PWA & Performance
- **Service Worker**: Custom SW with cache strategies
- **Manifest**: Web app manifest
- **Notifications**: Push API integration
- **Optimization**: Image lazy loading, API caching

##  Requirements

- **PHP**: 8.2+
- **Composer**: Latest
- **Node.js**: 18+
- **Database**: MariaDB 10.6+ or MySQL 8+
- **Redis**: For queues and caching
- **FFmpeg**: For video processing
- **Meilisearch**: Optional, for search functionality

##  Installation

### 1. Clone Repository
```bash
git clone https://github.com/wyatts97/Hubtube.git hubtube
cd hubtube
```

### 2. Install Dependencies
```bash
# PHP dependencies
composer install

# Node dependencies
npm install
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Environment
Edit your `.env` file with:
- Database credentials
- Redis configuration
- Agora.io credentials (for live streaming)
- Cloud storage settings
- Payment gateway credentials

### 5. Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### 6. Build Assets
```bash
npm run build
```

### 7. Start Services
```bash
# Main application
php artisan serve

# Queue worker (separate terminal)
php artisan horizon

# WebSocket server (separate terminal)
php artisan reverb:start
```

##  Default Credentials

- **Admin**: `admin@hubtube.com` / `password`
- **Demo User**: `demo@hubtube.com` / `password`

##  Admin Panel

Access the comprehensive admin dashboard at `/admin`:
- User management and moderation
- Video content management
- Live stream monitoring
- Financial analytics
- System metrics and health
- PWA settings and push notifications
- Ad configuration

##  Configuration

### Live Streaming (Agora.io)
1. Create account at [agora.io](https://agora.io)
2. Create project and get credentials
3. Add to `.env`:
```env
AGORA_APP_ID=your_app_id
AGORA_APP_CERTIFICATE=your_certificate
```

### Cloud Storage (Wasabi)

HubTube integrates with [Wasabi](https://wasabi.com) S3-compatible object storage for scalable video hosting with no egress fees.

**1. Create a Wasabi account and bucket:**
- Sign up at [console.wasabisys.com](https://console.wasabisys.com)
- Create a bucket in your preferred region
- Set the bucket policy to **public read** (for video playback) or use signed URLs for private content
- Create an IAM user with `s3:*` permissions on the bucket

**2. Configure `.env`:**
```env
CLOUD_STORAGE_DRIVER=wasabi
WASABI_ACCESS_KEY=your_access_key
WASABI_SECRET_KEY=your_secret_key
WASABI_BUCKET=your-bucket-name
WASABI_REGION=us-east-1
WASABI_ENDPOINT=https://s3.wasabisys.com
WASABI_URL=https://your-bucket-name.s3.wasabisys.com
```

The endpoint auto-resolves from the region. Available regions:
| Region | Location | Endpoint |
|--------|----------|----------|
| `us-east-1` | N. Virginia | `s3.wasabisys.com` |
| `us-east-2` | N. Virginia | `s3.us-east-2.wasabisys.com` |
| `us-central-1` | Texas | `s3.us-central-1.wasabisys.com` |
| `us-west-1` | Oregon | `s3.us-west-1.wasabisys.com` |
| `eu-central-1` | Amsterdam | `s3.eu-central-1.wasabisys.com` |
| `eu-central-2` | Frankfurt | `s3.eu-central-2.wasabisys.com` |
| `ap-northeast-1` | Tokyo | `s3.ap-northeast-1.wasabisys.com` |

**3. Install the S3 driver (if not already installed):**
```bash
composer require league/flysystem-aws-s3-v3
```

**4. Run the migration to add `storage_disk` tracking:**
```bash
php artisan migrate
```

**5. Configure via Admin Panel:**
Navigate to **Admin → Settings → Storage & CDN → Wasabi** tab to configure credentials, test the connection, and set Wasabi as the primary storage driver.

**6. Migrate existing local videos to Wasabi:**
```bash
# Preview what will be migrated
php artisan storage:migrate --from=public --to=wasabi --dry-run

# Migrate all local videos
php artisan storage:migrate --from=public --to=wasabi

# Migrate a limited batch
php artisan storage:migrate --from=public --to=wasabi --limit=50
```

**How it works:**
- New uploads are stored locally first (FFmpeg needs local filesystem access)
- After processing, `ProcessVideoJob` uploads all files to Wasabi automatically
- Each video tracks its `storage_disk` so the app knows where to find files
- URL generation uses `StorageManager` to resolve the correct public URL
- CDN URL can be configured to override Wasabi direct URLs

### FFmpeg
Ensure FFmpeg is installed and configure paths:
```env
FFMPEG_BINARY=/usr/bin/ffmpeg
FFPROBE_BINARY=/usr/bin/ffprobe
```

##  Directory Structure

```
├── app/
│   ├── Http/Controllers/     # API and web controllers
│   ├── Models/              # Eloquent models
│   ├── Jobs/                # Queue jobs (video processing)
│   ├── Services/            # Business logic
│   └── Filament/            # Admin panel resources
├── resources/
│   ├── js/
│   │   ├── Components/      # Reusable Vue components
│   │   ├── Pages/           # Inertia page components
│   │   ├── Layouts/         # App layouts
│   │   └── Composables/     # Vue composables
│   ├── css/                 # Tailwind styles
│   └── views/               # Blade templates
├── database/
│   ├── migrations/          # Database schema
│   └── seeders/             # Sample data
├── public/
│   ├── icons/               # PWA icons
│   ├── manifest.json        # Web app manifest
│   └── sw.js               # Service worker
└── scraper/                # Content scraping tools
```

##  API Endpoints

### Authentication
- `POST /login` - User login
- `POST /register` - User registration
- `POST /logout` - User logout

### Videos
- `GET /videos` - List videos with pagination
- `GET /{slug}` - View video page
- `POST /upload` - Upload video
- `POST /videos/{id}/like` - Like/dislike video
- `POST /videos/{id}/comments` - Add comment

### Playlists
- `GET /playlists` - User playlists
- `POST /playlists` - Create playlist
- `POST /playlists/{id}/videos` - Add video to playlist

### Live Streaming
- `GET /live` - Live streams list
- `POST /go-live` - Start streaming
- `POST /live/{id}/gift` - Send virtual gift

### Wallet & Monetization
- `GET /wallet` - Wallet balance
- `POST /wallet/deposit` - Add funds
- `POST /wallet/withdraw` - Request withdrawal

##  Frontend Components

### Core Components
- `VideoCard` - Responsive video thumbnail with hover effects
- `VideoPlayer` - HLS video player with controls
- `ShortsViewer` - TikTok-style vertical video viewer
- `CommentSection` - Nested comments with real-time updates

### UI Components
- `ToastContainer` - Non-intrusive notifications
- `Pagination` - Reusable pagination component
- `ErrorBoundary` - Graceful error handling
- `KeyboardShortcuts` - Video player shortcut guide
- `LanguageSwitcher` - Multi-language selector

### Composables
- `useFetch` - Centralized API requests with CSRF
- `useToast` - Toast notification system
- `useI18n` - Internationalization framework
- `useOptimizedImage` - Responsive image optimization
- `usePushNotifications` - Push notification management

##  Internationalization

HubTube supports 10+ languages:
- English (en)
- Spanish (es)
- French (fr)
- German (de)
- Portuguese (pt)
- Arabic (ar) - RTL support
- Chinese (zh)
- Japanese (ja)
- Korean (ko)
- Hindi (hi)

Add new languages by creating JSON files in `resources/js/i18n/`.

##  PWA Features

- **Offline Support**: Cached pages and assets
- **Push Notifications**: Browser-based notifications
- **App-like Experience**: Installable on desktop/mobile
- **Fast Loading**: Service worker caching strategies
- **Responsive Design**: Works on all screen sizes

##  Development

### Available Scripts
```bash
npm run dev          # Development server
npm run build        # Production build
npm run preview      # Preview production build
```

### Code Quality
- ESLint and Prettier configured
- TypeScript support available
- Component-based architecture
- Comprehensive error handling

##  Performance Optimizations

- **Lazy Loading**: Images and below-fold content
- **API Caching**: Service worker stale-while-revalidate
- **Image Optimization**: Responsive srcset and WebP
- **Code Splitting**: Automatic with Vite
- **CSS Optimization**: PurgeCSS in production

##  Security Features

- **Age Verification**: Compliant age gate
- **CSRF Protection**: Built-in Laravel protection
- **Input Sanitization**: XSS prevention
- **Content Moderation**: Admin moderation tools
- **Privacy Controls**: User privacy settings

##  Monetization Features

- **Virtual Gifts**: Real-time gift economy
- **Paid Content**: Pay-per-view videos
- **Channel Subscriptions**: Monthly subscriptions
- **Ad Revenue**: Integrated ad management
- **Wallet System**: Secure payment processing

## License

Proprietary - All rights reserved.

## Support

For support and questions:
- Create an issue in the repository
- Check the [GUIDE.MD](./GUIDE.MD) for detailed setup instructions
- Review the admin documentation at `/admin`

---

Built with ❤️ using modern web technologies
