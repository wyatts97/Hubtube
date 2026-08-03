<?php

namespace App\Notifications\Channels;

use App\Models\Notification as NotificationModel;
use Illuminate\Notifications\Notification;

/**
 * Writes notifications into HubTube's existing custom `notifications` table
 * (user_id, from_user_id, type, title, message, data, read_at) instead of
 * Laravel's default UUID-based `notifications` table.
 *
 * Notification classes using this channel must implement:
 *   toDatabase($notifiable): array{type: string, title: string, message?: string, data?: array, from_user_id?: int}
 */
class CustomDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toDatabase')) {
            return;
        }

        $payload = $notification->toDatabase($notifiable);

        if (empty($payload)) {
            return;
        }

        // Prevent duplicate notifications for the same event when idempotency
        // data is provided (e.g. video_id, comment_id) via a "dedupe" key.
        // Accepts either a single [column, value] pair or an array of pairs.
        if (!empty($payload['dedupe'])) {
            $conditions = is_array($payload['dedupe'][0]) ? $payload['dedupe'] : [$payload['dedupe']];

            $query = NotificationModel::where('user_id', $notifiable->id)
                ->where('type', $payload['type']);

            foreach ($conditions as [$column, $value]) {
                $query->where($column, $value);
            }

            if ($query->exists()) {
                return;
            }
        }
        unset($payload['dedupe']);

        NotificationModel::create(array_merge([
            'user_id' => $notifiable->id,
        ], $payload));
    }
}
