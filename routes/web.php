<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
// EmbeddedVideoController removed - imported videos now use the regular Video model and /<slug> route
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ThumbnailProxyController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// ── Installer Routes ──
Route::middleware('installed:block')->prefix('install')->group(function () {
    Route::get('/', [\App\Http\Controllers\InstallController::class, 'requirements'])->name('install.requirements');
    Route::get('/database', [\App\Http\Controllers\InstallController::class, 'database'])->name('install.database');
    Route::post('/database', [\App\Http\Controllers\InstallController::class, 'saveDatabase'])->name('install.database.save');
    Route::get('/application', [\App\Http\Controllers\InstallController::class, 'application'])->name('install.application');
    Route::post('/application', [\App\Http\Controllers\InstallController::class, 'saveApplication'])->name('install.application.save');
    Route::get('/admin', [\App\Http\Controllers\InstallController::class, 'admin'])->name('install.admin');
    Route::post('/admin', [\App\Http\Controllers\InstallController::class, 'saveAdmin'])->name('install.admin.save');
    Route::get('/finalize', [\App\Http\Controllers\InstallController::class, 'finalize'])->name('install.finalize');
    Route::post('/finalize', [\App\Http\Controllers\InstallController::class, 'executeFinalize'])->name('install.finalize.execute');
});

// ── App Routes (require installation) ──
Route::middleware('installed:require')->group(function () {

// Admin auth is handled by main site login modal/page
Route::get('/admin/login', fn () => redirect()->route('login'))->name('admin.login.redirect');

// Sitemap & Robots (outside age verification)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap_index.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/robots.txt', function () {
    $content = \App\Models\Setting::get('seo_robots_txt', "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nSitemap: " . url('/sitemap.xml'));
    return response($content, 200, ['Content-Type' => 'text/plain']);
})->name('robots.txt');

// Offline page for PWA
Route::get('/offline', fn () => view('offline'))->name('offline');

// Stream video files with Range request support (php artisan serve doesn't support Range)
// Used by watermark preview and admin video edit player
Route::get('/admin/video-stream/{path}', function (string $path) {
    // Only allow admin users
    if (!auth()->check() || !auth()->user()->is_admin) {
        abort(403);
    }

    // Resolve the file from public storage
    $fullPath = storage_path('app/public/' . $path);
    if (!file_exists($fullPath)) {
        abort(404);
    }

    // Prevent directory traversal
    $realBase = realpath(storage_path('app/public'));
    $realPath = realpath($fullPath);
    if (!$realPath || !str_starts_with($realPath, $realBase)) {
        abort(403);
    }

    $size = filesize($fullPath);
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeMap = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo'];
    $mime = $mimeMap[$ext] ?? 'video/mp4';

    $headers = [
        'Content-Type' => $mime,
        'Accept-Ranges' => 'bytes',
        'Content-Length' => $size,
    ];

    $request = request();
    if ($request->header('Range')) {
        $range = $request->header('Range');
        if (preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
            $start = (int) $m[1];
            $end = $m[2] !== '' ? (int) $m[2] : $size - 1;
            $end = min($end, $size - 1);
            $length = $end - $start + 1;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
            $headers['Content-Length'] = $length;
            $stream = fopen($fullPath, 'rb');
            fseek($stream, $start);
            $data = fread($stream, $length);
            fclose($stream);
            return response($data, 206, $headers);
        }
    }
    return response()->file($fullPath, $headers);
})->where('path', '.+')->name('admin.video-stream');

// Thumbnail proxy for embedded video thumbnails
Route::get('/api/thumb-proxy', [ThumbnailProxyController::class, 'proxy'])
    ->middleware('throttle:30,1')
    ->name('thumb.proxy');

// Video Ads API (outside age.verified — the page itself already enforces age gate)
Route::get('/api/video-ads', [\App\Http\Controllers\VideoAdController::class, 'getAds'])
    ->middleware('throttle:30,1')
    ->name('video-ads.get');

Route::middleware('age.verified')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/api/videos/load-more', [HomeController::class, 'loadMoreVideos'])->name('videos.loadMore');
    Route::get('/trending', [HomeController::class, 'trending'])->name('trending');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');
    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
    Route::get('/categories', [HomeController::class, 'categories'])->name('categories.index');
    Route::get('/category/{category:slug}', [HomeController::class, 'category'])->name('categories.show');
    Route::get('/tags', [HomeController::class, 'tags'])->name('tags.index');
    Route::get('/tag/{tag}', [HomeController::class, 'tag'])->name('tags.show');

    Route::get('/channel/{user:username}', [ChannelController::class, 'show'])->name('channel.show');
    Route::get('/channel/{user:username}/videos', [ChannelController::class, 'videos'])->name('channel.videos');
    Route::get('/channel/{user:username}/playlists', [ChannelController::class, 'playlists'])->name('channel.playlists');
    Route::get('/channel/{user:username}/liked', [ChannelController::class, 'likedVideos'])->name('channel.liked');
    Route::get('/channel/{user:username}/history', [ChannelController::class, 'watchHistory'])->name('channel.history');
    Route::get('/channel/{user:username}/about', [ChannelController::class, 'about'])->name('channel.about');

    Route::get('/live', [LiveStreamController::class, 'index'])->name('live.index');
    Route::get('/live/{liveStream}', [LiveStreamController::class, 'show'])->name('live.show');

    Route::get('/public-playlists', [PlaylistController::class, 'publicIndex'])->name('playlists.public');
    Route::get('/playlist/{playlist:slug}', [PlaylistController::class, 'show'])->name('playlists.show');

    // Image & Gallery routes (public browse)
    Route::get('/images', [ImageController::class, 'index'])->name('images.index');
    Route::get('/image/{image:uuid}', [ImageController::class, 'show'])->name('images.show');
    Route::get('/galleries', [GalleryController::class, 'index'])->name('galleries.index');
    Route::get('/gallery/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');

    // Legal / Static Pages
    Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');

    // Social Login Routes (accessible by both guests and authenticated users for account linking)
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->where('provider', 'google|twitter|reddit')
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->where('provider', 'google|twitter|reddit')
        ->name('social.callback');

    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisterController::class, 'create'])->name('register');
        Route::post('/register', [RegisterController::class, 'store']);

        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store']);

        Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::get('/upload', [VideoController::class, 'create'])->name('videos.create');

        // Image upload & management
        Route::get('/image-upload', [ImageController::class, 'create'])->name('images.create');
        Route::post('/image-upload', [ImageController::class, 'store'])->middleware('throttle:10,1')->name('images.store');
        Route::delete('/images/{image}', [ImageController::class, 'destroy'])->name('images.destroy');

        // Gallery management
        Route::get('/galleries/create', [GalleryController::class, 'create'])->name('galleries.create');
        Route::post('/galleries', [GalleryController::class, 'store'])->name('galleries.store');
        Route::put('/gallery/{gallery}', [GalleryController::class, 'update'])->name('galleries.update');
        Route::delete('/gallery/{gallery}', [GalleryController::class, 'destroy'])->name('galleries.destroy');
        Route::get('/upload/success', [VideoController::class, 'uploadSuccess'])->name('videos.upload-success');
        Route::post('/upload', [VideoController::class, 'store'])->middleware('throttle:10,1')->name('videos.store');
        Route::post('/upload/chunk', [VideoController::class, 'uploadChunk'])->middleware('throttle:300,1')->name('videos.upload-chunk');
        Route::get('/videos/{video}/edit', [VideoController::class, 'edit'])->name('videos.edit');
        Route::get('/videos/{video}/status', [VideoController::class, 'status'])->name('videos.status');
        Route::get('/videos/{video}/processing-status', [VideoController::class, 'processingStatus'])->name('videos.processing-status');
        Route::post('/videos/{video}/select-thumbnail', [VideoController::class, 'selectThumbnail'])->name('videos.select-thumbnail');
        Route::put('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
        Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');

        Route::post('/videos/{video}/like', [LikeController::class, 'like'])->middleware('throttle:30,1')->name('videos.like');
        Route::post('/videos/{video}/dislike', [LikeController::class, 'dislike'])->middleware('throttle:30,1')->name('videos.dislike');

        Route::get('/videos/{video}/comments', [CommentController::class, 'index'])->middleware('throttle:30,1')->name('comments.index');
        Route::post('/videos/{video}/comments', [CommentController::class, 'store'])->middleware('throttle:10,1')->name('comments.store');
        Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/comments/{comment}/like', [CommentController::class, 'like'])->middleware('throttle:30,1')->name('comments.like');
        Route::post('/comments/{comment}/dislike', [CommentController::class, 'dislike'])->middleware('throttle:30,1')->name('comments.dislike');

        Route::post('/channel/{user}/subscribe', [SubscriptionController::class, 'store'])->name('subscription.store');
        Route::delete('/channel/{user}/subscribe', [SubscriptionController::class, 'destroy'])->name('subscription.destroy');
        Route::post('/channel/{user}/notifications', [SubscriptionController::class, 'toggleNotifications'])->name('subscription.notifications');

        Route::get('/playlists', [PlaylistController::class, 'index'])->name('playlists.index');
        Route::post('/playlists', [PlaylistController::class, 'store'])->name('playlists.store');
        Route::put('/playlists/{playlist}', [PlaylistController::class, 'update'])->name('playlists.update');
        Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');
        Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo'])->name('playlists.addVideo');
        Route::delete('/playlists/{playlist}/videos', [PlaylistController::class, 'removeVideo'])->name('playlists.removeVideo');
        Route::post('/playlists/{playlist}/favorite', [PlaylistController::class, 'toggleFavorite'])->name('playlists.toggleFavorite');

        Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
        Route::delete('/history', [HistoryController::class, 'destroy'])->name('history.destroy');

        Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::get('/wallet/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');
        Route::post('/wallet/deposit', [WalletController::class, 'processDeposit'])->middleware('throttle:10,1')->name('wallet.deposit.process');
        Route::get('/wallet/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');
        Route::post('/wallet/withdraw', [WalletController::class, 'processWithdraw'])->middleware('throttle:5,1')->name('wallet.withdraw.process');

        Route::get('/go-live', [LiveStreamController::class, 'create'])->name('live.create');
        Route::post('/go-live', [LiveStreamController::class, 'store'])->middleware('throttle:5,1')->name('live.store');
        Route::post('/live/{liveStream}/start', [LiveStreamController::class, 'start'])->name('live.start');
        Route::post('/live/{liveStream}/end', [LiveStreamController::class, 'end'])->name('live.end');

        Route::get('/gifts', [GiftController::class, 'index'])->name('gifts.index');
        Route::post('/live/{liveStream}/gift', [GiftController::class, 'send'])->middleware('throttle:60,1')->name('gifts.send');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar');
        Route::post('/settings/banner', [SettingsController::class, 'updateBanner'])->name('settings.banner');
        Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
        Route::put('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
        Route::delete('/settings/account', [SettingsController::class, 'deleteAccount'])->name('settings.delete-account');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');

        // Reports
        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:5,1')->name('reports.store');

        // Push Notifications
        Route::post('/api/push/vapid-key', [\App\Http\Controllers\PushNotificationController::class, 'vapidKey'])->name('push.vapid-key');
        Route::post('/api/push/subscribe', [\App\Http\Controllers\PushNotificationController::class, 'subscribe'])->name('push.subscribe');
        Route::delete('/api/push/unsubscribe', [\App\Http\Controllers\PushNotificationController::class, 'unsubscribe'])->name('push.unsubscribe');

        // Subscriptions Feed
        Route::get('/feed', FeedController::class)->name('feed');

        // Creator Dashboard
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });

    // Translation API routes
    Route::post('/api/translate', [TranslationController::class, 'translate'])->middleware('throttle:60,1')->name('translate');
    Route::post('/api/translate/batch', [TranslationController::class, 'translateBatch'])->middleware('throttle:30,1')->name('translate.batch');
    Route::get('/api/languages', [TranslationController::class, 'languages'])->name('languages');
    Route::post('/api/locale', [TranslationController::class, 'setLocale'])->name('locale.set');

    // ── Locale-prefixed routes for SEO (e.g. /es/trending, /fr/video-slug) ──
    // MUST be before the catch-all /{video:slug} route so /es etc. aren't matched as video slugs
    Route::prefix('{locale}')->where(['locale' => '[a-z]{2,3}'])->middleware(['locale', 'age.verified'])->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('locale.home');
        Route::get('/trending', [HomeController::class, 'trending'])->name('locale.trending');
        Route::get('/search', [SearchController::class, 'index'])->name('locale.search');
        Route::get('/videos', [VideoController::class, 'index'])->name('locale.videos.index');
        Route::get('/contact', [ContactController::class, 'show'])->name('locale.contact');
        Route::get('/categories', [HomeController::class, 'categories'])->name('locale.categories.index');
        Route::get('/category/{slug}', [HomeController::class, 'localeCategory'])->name('locale.categories.show');
        Route::get('/tags', [HomeController::class, 'localeTags'])->name('locale.tags.index');
        Route::get('/tag/{tag}', [HomeController::class, 'localeTag'])->name('locale.tags.show');
        Route::get('/channel/{username}', [ChannelController::class, 'localeShow'])->name('locale.channel.show');
        Route::get('/channel/{username}/videos', [ChannelController::class, 'localeVideos'])->name('locale.channel.videos');
        Route::get('/channel/{username}/playlists', [ChannelController::class, 'localePlaylists'])->name('locale.channel.playlists');
        Route::get('/channel/{username}/liked', [ChannelController::class, 'localeLikedVideos'])->name('locale.channel.liked');
        Route::get('/channel/{username}/history', [ChannelController::class, 'localeWatchHistory'])->name('locale.channel.history');
        Route::get('/channel/{username}/about', [ChannelController::class, 'localeAbout'])->name('locale.channel.about');
        Route::get('/public-playlists', [PlaylistController::class, 'publicIndex'])->name('locale.playlists.public');
        Route::get('/live', [LiveStreamController::class, 'index'])->name('locale.live.index');
        Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('locale.pages.show');

        // Locale-prefixed video show — uses plain {slug} param to avoid model binding conflict with {locale}
        Route::get('/{slug}', [VideoController::class, 'localeShow'])->name('locale.videos.show');
    });

    // Video show route - must be LAST to avoid conflicts with locale and other routes
    Route::get('/{video:slug}', [VideoController::class, 'show'])->where('video', '^(?!api|admin|livewire).*')->name('videos.show');
});

}); // end installed:require

