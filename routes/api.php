<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/videos/{video}/like', [LikeController::class, 'like']);
    Route::post('/videos/{video}/dislike', [LikeController::class, 'dislike']);

    Route::get('/videos/{video}/comments', [CommentController::class, 'index']);
    Route::post('/videos/{video}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('/channels/{user}/subscribe', [SubscriptionController::class, 'store']);
    Route::delete('/channels/{user}/subscribe', [SubscriptionController::class, 'destroy']);

    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);
    Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo']);
    Route::delete('/playlists/{playlist}/videos', [PlaylistController::class, 'removeVideo']);

    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    Route::post('/live', [LiveStreamController::class, 'store']);
    Route::post('/live/{liveStream}/start', [LiveStreamController::class, 'start']);
    Route::post('/live/{liveStream}/end', [LiveStreamController::class, 'end']);
    Route::post('/live/{liveStream}/viewers', [LiveStreamController::class, 'updateViewerCount']);

    Route::get('/gifts', [GiftController::class, 'index']);
    Route::post('/live/{liveStream}/gift', [GiftController::class, 'send']);
});

Route::get('/search', [SearchController::class, 'index']);
