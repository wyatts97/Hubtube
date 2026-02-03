<?php

namespace App\Http\Controllers;

use App\Events\NewSubscriber;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function store(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['error' => 'Cannot subscribe to yourself'], 422);
        }

        $subscription = Subscription::firstOrCreate([
            'subscriber_id' => $request->user()->id,
            'channel_id' => $user->id,
        ]);

        if ($subscription->wasRecentlyCreated) {
            $user->channel?->incrementSubscribers();
            event(new NewSubscriber($subscription));
        }

        return response()->json([
            'subscribed' => true,
            'subscriberCount' => $user->subscriber_count,
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $deleted = Subscription::where([
            'subscriber_id' => $request->user()->id,
            'channel_id' => $user->id,
        ])->delete();

        if ($deleted) {
            $user->channel?->decrementSubscribers();
        }

        return response()->json([
            'subscribed' => false,
            'subscriberCount' => $user->subscriber_count,
        ]);
    }

    public function toggleNotifications(Request $request, User $user): JsonResponse
    {
        $subscription = Subscription::where([
            'subscriber_id' => $request->user()->id,
            'channel_id' => $user->id,
        ])->first();

        if (!$subscription) {
            return response()->json(['error' => 'Not subscribed'], 404);
        }

        $subscription->update([
            'notifications_enabled' => !$subscription->notifications_enabled,
        ]);

        return response()->json([
            'notificationsEnabled' => $subscription->notifications_enabled,
        ]);
    }
}
