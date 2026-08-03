<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Models\Report;
use App\Notifications\Channels\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app only — sent to admin users. The admin email for new reports is
 * already handled separately via EmailService::sendToAdmin() in
 * ReportController to avoid duplicate emails and keep the existing
 * admin_notify_admin-new-report setting in control.
 */
class ReportSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Report $report, protected string $reportedContentLabel)
    {
    }

    public function via(object $notifiable): array
    {
        return [CustomDatabaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'from_user_id' => $this->report->user_id,
            'type' => NotificationModel::TYPE_REPORT_SUBMITTED,
            'title' => 'New Content Report',
            'message' => "New report ({$this->report->reason}) for {$this->reportedContentLabel}",
            'data' => [
                'report_id' => $this->report->id,
                'reportable_type' => $this->report->reportable_type,
                'reportable_id' => $this->report->reportable_id,
            ],
        ];
    }
}
