<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\WithdrawalRequest;
use App\Notifications\Channels\CustomDatabaseChannel;
use App\Notifications\Channels\EmailServiceChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WithdrawalApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(protected WithdrawalRequest $withdrawal)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class, EmailServiceChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationModel::TYPE_WITHDRAWAL_APPROVED,
            'title' => 'Withdrawal Approved',
            'message' => "Your withdrawal request of \${$this->withdrawal->amount} has been approved.",
            'data' => [
                'withdrawal_id' => $this->withdrawal->id,
                'amount' => (string) $this->withdrawal->amount,
                'transaction_id' => $this->withdrawal->transaction_id,
            ],
        ];
    }

    public function toEmailService(object $notifiable): ?array
    {
        return [
            'template' => 'withdrawal-approved',
            'to' => $notifiable->email,
            'data' => [
                'username' => $notifiable->username,
                'amount' => (string) $this->withdrawal->amount,
                'transaction_id' => $this->withdrawal->transaction_id,
            ],
        ];
    }
}
