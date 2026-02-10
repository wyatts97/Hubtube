<?php

/**
 * API routes (Sanctum token auth).
 *
 * Most interactive endpoints (likes, comments, playlists, subscriptions,
 * gifts, live-stream CRUD) are handled by web.php with session auth.
 * Only endpoints that are unique to the API or needed by external/mobile
 * clients are kept here to avoid duplication and auth confusion.
 */

use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'is_verified' => $user->is_verified,
            'is_pro' => $user->is_pro,
        ]);
    });

    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    Route::post('/live/{liveStream}/viewers', [LiveStreamController::class, 'updateViewerCount']);
});

Route::get('/search', [SearchController::class, 'index']);
