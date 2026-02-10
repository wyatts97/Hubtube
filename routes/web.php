<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CommentController;
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
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ThumbnailProxyController;
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

// Sitemap & Robots (outside age verification)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', function () {
    $content = \App\Models\Setting::get('seo_robots_txt', "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nSitemap: " . url('/sitemap.xml'));
    return response($content, 200, ['Content-Type' => 'text/plain']);
})->name('robots.txt');

// Offline page for PWA
Route::get('/offline', fn () => view('offline'))->name('offline');

// Thumbnail proxy for embedded video thumbnails
Route::get('/api/thumb-proxy', [ThumbnailProxyController::class, 'proxy'])->name('thumb.proxy');

Route::middleware('age.verified')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/api/videos/load-more', [HomeController::class, 'loadMoreVideos'])->name('videos.loadMore');
    Route::get('/trending', [HomeController::class, 'trending'])->name('trending');
    Route::get('/shorts', [HomeController::class, 'shorts'])->name('shorts');
    Route::get('/shorts/{video}', [HomeController::class, 'shorts'])->name('shorts.show');
    Route::get('/api/shorts/load-more', [HomeController::class, 'loadMoreShorts'])->name('shorts.loadMore');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');
    Route::get('/categories', [HomeController::class, 'categories'])->name('categories.index');
    Route::get('/category/{category:slug}', [HomeController::class, 'category'])->name('categories.show');
    Route::get('/tag/{tag}', [HomeController::class, 'tag'])->name('tags.show');

    Route::get('/channel/{user:username}', [ChannelController::class, 'show'])->name('channel.show');
    Route::get('/channel/{user:username}/videos', [ChannelController::class, 'videos'])->name('channel.videos');
    Route::get('/channel/{user:username}/shorts', [ChannelController::class, 'shorts'])->name('channel.shorts');
    Route::get('/channel/{user:username}/playlists', [ChannelController::class, 'playlists'])->name('channel.playlists');
    Route::get('/channel/{user:username}/about', [ChannelController::class, 'about'])->name('channel.about');

    Route::get('/live', [LiveStreamController::class, 'index'])->name('live.index');
    Route::get('/live/{liveStream}', [LiveStreamController::class, 'show'])->name('live.show');

    Route::get('/playlist/{playlist:slug}', [PlaylistController::class, 'show'])->name('playlists.show');

    // Legal / Static Pages
    Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');

    // Video Ads API (accessible by all users including guests)
    Route::get('/api/video-ads', [\App\Http\Controllers\VideoAdController::class, 'getAds'])->name('video-ads.get');

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

        Route::get('/videos/{video}/comments', [CommentController::class, 'index'])->name('comments.index');
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

    // Video show route - must be last to avoid conflicts with other routes
    Route::get('/{video:slug}', [VideoController::class, 'show'])->where('video', '^(?!api|admin|livewire).*')->name('videos.show');
});

}); // end installed:require
