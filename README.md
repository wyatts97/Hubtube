# HubTube - Adult Video Tube CMS

A scalable, feature-rich video-sharing CMS optimized for adult content, built with Laravel 11, Vue 3, and Inertia.js.

## Features

- **Video Management**: Upload, transcode (FFmpeg), HLS adaptive streaming, thumbnails
- **User Channels**: Profiles, subscriptions, verification badges
- **Social Features**: Likes, comments, playlists, watch history, notifications
- **Live Streaming**: TikTok-style interactive streams using Agora.io
- **Virtual Gifts**: Real-time gift sending during live streams
- **Monetization**: Wallet system, paid videos, channel subscriptions, ad revenue
- **Adult Compliance**: Age verification gate, 2257 record-keeping placeholders
- **Admin Panel**: Filament-powered admin dashboard

## Tech Stack

- **Backend**: Laravel 11+ (PHP 8.2+)
- **Frontend**: Vue 3 (Composition API) + Inertia.js
- **Database**: MariaDB
- **Video Processing**: FFmpeg
- **Storage**: Wasabi/Backblaze B2 + BunnyCDN
- **Real-Time**: Agora.io (RTC/RTM) + Laravel Reverb
- **Queue**: Laravel Horizon + Redis
- **Search**: Laravel Scout + Meilisearch
- **Admin**: Filament 3

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- MariaDB 10.6+
- Redis
- FFmpeg
- Meilisearch (optional, for search)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/wyatts97/Hubtube.git hubtube
   cd hubtube
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your `.env` file**
   - Set database credentials
   - Configure Redis
   - Add Agora.io credentials
   - Configure cloud storage (Wasabi/B2)
   - Set payment gateway credentials

6. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

8. **Start the development server**
   ```bash
   php artisan serve
   ```

9. **Start queue worker (in separate terminal)**
   ```bash
   php artisan horizon
   ```

10. **Start WebSocket server (in separate terminal)**
    ```bash
    php artisan reverb:start
    ```

## Default Credentials

- **Admin**: admin@hubtube.com / password
- **Demo User**: demo@hubtube.com / password

## Admin Panel

Access the admin panel at `/admin` (requires admin user).

## Configuration

### Agora.io (Live Streaming)

1. Create an account at [agora.io](https://agora.io)
2. Create a project and get App ID + App Certificate
3. Add to `.env`:
   ```
   AGORA_APP_ID=your_app_id
   AGORA_APP_CERTIFICATE=your_certificate
   ```

### Cloud Storage

Configure Wasabi or Backblaze B2 in `.env`:
```
CLOUD_STORAGE_DRIVER=wasabi
WASABI_ACCESS_KEY=your_key
WASABI_SECRET_KEY=your_secret
WASABI_BUCKET=your_bucket
WASABI_REGION=us-east-1
```

### FFmpeg

Ensure FFmpeg is installed and configure paths:
```
FFMPEG_BINARY=/usr/bin/ffmpeg
FFPROBE_BINARY=/usr/bin/ffprobe
```

## Directory Structure

```
app/
├── Events/          # Application events
├── Filament/        # Admin panel resources
├── Http/
│   ├── Controllers/ # HTTP controllers
│   ├── Middleware/  # Custom middleware
│   └── Requests/    # Form requests
├── Jobs/            # Queue jobs (video processing)
├── Listeners/       # Event listeners
├── Models/          # Eloquent models
├── Policies/        # Authorization policies
├── Providers/       # Service providers
└── Services/        # Business logic services

resources/
├── css/             # Tailwind CSS
├── js/
│   ├── Components/  # Vue components
│   ├── Layouts/     # Page layouts
│   └── Pages/       # Inertia pages
└── views/           # Blade templates

database/
├── migrations/      # Database migrations
└── seeders/         # Database seeders
```

## API Endpoints

### Authentication
- `POST /register` - User registration
- `POST /login` - User login
- `POST /logout` - User logout

### Videos
- `GET /videos` - List videos
- `GET /watch/{slug}` - View video
- `POST /upload` - Upload video
- `POST /videos/{id}/like` - Like video
- `POST /videos/{id}/dislike` - Dislike video

### Live Streaming
- `GET /live` - List live streams
- `GET /live/{id}` - View live stream
- `POST /go-live` - Start live stream
- `POST /live/{id}/gift` - Send gift

### Wallet
- `GET /wallet` - View wallet
- `POST /wallet/deposit` - Deposit funds
- `POST /wallet/withdraw` - Request withdrawal

## License

Proprietary - All rights reserved.
