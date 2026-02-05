<?php

use App\Http\Controllers\AgeVerificationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EmbeddedVideoController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/age-verify', [AgeVerificationController::class, 'show'])->name('age.verify');
Route::post('/age-verify', [AgeVerificationController::class, 'verify'])->name('age.verify.confirm');
Route::get('/age-verify/decline', [AgeVerificationController::class, 'decline'])->name('age.verify.decline');

Route::middleware('age.verified')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/api/videos/load-more', [HomeController::class, 'loadMoreVideos'])->name('videos.loadMore');
    Route::get('/trending', [HomeController::class, 'trending'])->name('trending');
    Route::get('/shorts', [HomeController::class, 'shorts'])->name('shorts');
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');

    Route::get('/channel/{user:username}', [ChannelController::class, 'show'])->name('channel.show');
    Route::get('/channel/{user:username}/videos', [ChannelController::class, 'videos'])->name('channel.videos');
    Route::get('/channel/{user:username}/shorts', [ChannelController::class, 'shorts'])->name('channel.shorts');
    Route::get('/channel/{user:username}/playlists', [ChannelController::class, 'playlists'])->name('channel.playlists');
    Route::get('/channel/{user:username}/about', [ChannelController::class, 'about'])->name('channel.about');

    Route::get('/live', [LiveStreamController::class, 'index'])->name('live.index');
    Route::get('/live/{liveStream}', [LiveStreamController::class, 'show'])->name('live.show');

    Route::get('/playlist/{playlist:slug}', [PlaylistController::class, 'show'])->name('playlists.show');

    // Embedded Videos
    Route::get('/embedded', [EmbeddedVideoController::class, 'index'])->name('embedded.index');
    Route::get('/embedded/featured', [EmbeddedVideoController::class, 'featured'])->name('embedded.featured');
    Route::get('/embedded/{embeddedVideo}', [EmbeddedVideoController::class, 'show'])->name('embedded.show');

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
        Route::post('/upload', [VideoController::class, 'store'])->name('videos.store');
        Route::get('/videos/{video}/edit', [VideoController::class, 'edit'])->name('videos.edit');
        Route::put('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
        Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');

        Route::post('/videos/{video}/like', [LikeController::class, 'like'])->name('videos.like');
        Route::post('/videos/{video}/dislike', [LikeController::class, 'dislike'])->name('videos.dislike');

        Route::get('/videos/{video}/comments', [CommentController::class, 'index'])->name('comments.index');
        Route::post('/videos/{video}/comments', [CommentController::class, 'store'])->name('comments.store');
        Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('/comments/{comment}/like', [CommentController::class, 'like'])->name('comments.like');
        Route::post('/comments/{comment}/dislike', [CommentController::class, 'dislike'])->name('comments.dislike');

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
        Route::post('/wallet/deposit', [WalletController::class, 'processDeposit'])->name('wallet.deposit.process');
        Route::get('/wallet/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');
        Route::post('/wallet/withdraw', [WalletController::class, 'processWithdraw'])->name('wallet.withdraw.process');

        Route::get('/go-live', [LiveStreamController::class, 'create'])->name('live.create');
        Route::post('/go-live', [LiveStreamController::class, 'store'])->name('live.store');
        Route::post('/live/{liveStream}/start', [LiveStreamController::class, 'start'])->name('live.start');
        Route::post('/live/{liveStream}/end', [LiveStreamController::class, 'end'])->name('live.end');

        Route::get('/gifts', [GiftController::class, 'index'])->name('gifts.index');
        Route::post('/live/{liveStream}/gift', [GiftController::class, 'send'])->name('gifts.send');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
        Route::put('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy');
    });

    // Video show route - must be last to avoid conflicts with other routes
    Route::get('/{video:slug}', [VideoController::class, 'show'])->name('videos.show');
});
