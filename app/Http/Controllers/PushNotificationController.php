<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function vapidKey(): JsonResponse
    {
        $key = Setting::get('vapid_public_key', '');
        
        if (empty($key)) {
            return response()->json(['error' => 'Push notifications not configured'], 503);
        }

        return response()->json(['key' => $key]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'endpoint' => $request->input('endpoint'),
            ],
            [
                'p256dh_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url',
        ]);

        PushSubscription::where('user_id', auth()->id())
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['success' => true]);
    }
}
