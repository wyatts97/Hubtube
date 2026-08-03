<?php

namespace App\Notifications\Channels;

use App\Services\EmailService;
use Illuminate\Notifications\Notification;

/**
 * Routes notification emails through HubTube's admin-configurable FinMail
 * template system (App\Services\EmailService) instead of Laravel's built-in
 * mail channel, so all existing admin-editable templates keep working.
 *
 * Notification classes using this channel must implement:
 *   toEmailService($notifiable): ?array{template: string, to?: string, data?: array}
 * Return null to skip sending an email for this notification.
 */
class EmailServiceChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toEmailService')) {
            return;
        }

        $payload = $notification->toEmailService($notifiable);

        if (empty($payload) || empty($payload['template'])) {
            return;
        }

        $to = $payload['to'] ?? ($notifiable->email ?? null);

        if (!$to) {
            return;
        }

        EmailService::sendToUser($payload['template'], $to, $payload['data'] ?? []);
    }
}
