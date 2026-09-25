<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Put back any core email template that doesn't exist.
 *
 * The seeder that created these was removed when email moved to FinMail, so
 * installs after that had no templates at all. Every send then failed with
 * "Email template not found" — caught and logged, so a user asking for a
 * password reset was told it was sent when nothing went out.
 *
 * Existing templates are never changed, except that the two account emails
 * (verification and password reset) are switched back on if they were
 * deleted or deactivated: without them nobody can verify or recover an
 * account.
 */
return new class extends Migration
{
    private const ACCOUNT_TEMPLATES = ['verify-email', 'reset-password'];

    public function up(): void
    {
        $table = config('fin-mail.table_names.templates') ?? 'email_templates';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'key')) {
            return;
        }

        $now = now();

        foreach ($this->templates() as $key => [$name, $subject, $body]) {
            $existing = DB::table($table)->where('key', $key)->first();

            if (! $existing) {
                DB::table($table)->insert([
                    'key' => $key,
                    'name' => json_encode(['en' => $name]),
                    'category' => 'transactional',
                    'subject' => json_encode(['en' => $subject]),
                    'body' => json_encode(['en' => $body]),
                    'is_active' => true,
                    'is_locked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if (in_array($key, self::ACCOUNT_TEMPLATES, true) && (! $existing->is_active || $existing->deleted_at)) {
                DB::table($table)->where('key', $key)->update([
                    'is_active' => true,
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Templates may have been edited since; leave them.
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    private function templates(): array
    {
        $button = fn (string $url, string $label) => '<p><a href="{{ '.$url.' }}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">'.$label.'</a></p>';

        return [
            'verify-email' => [
                'Email Verification',
                'Verify your email address — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>Thanks for signing up! Please verify your email address by clicking the button below.</p>'
                    .$button('verify_url', 'Verify Email Address')
                    ."<p>If you didn't create an account, no action is needed.</p>",
            ],
            'reset-password' => [
                'Password Reset',
                'Reset your password — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>We received a password reset request for your account. Click the button below to choose a new password.</p>'
                    .$button('reset_url', 'Reset Password')
                    ."<p>This link will expire in {{ expiry_minutes }} minutes.</p><p>If you didn't request this, you can safely ignore this email.</p>",
            ],
            'welcome' => [
                'Welcome Email',
                'Welcome to {{ site_name }}!',
                '<p>Hi {{ username }},</p><p>Welcome to {{ site_name }}! Your account is all set up and ready to go.</p>'
                    .$button('login_url', 'Get Started'),
            ],
            'video-published' => [
                'Video Published',
                'Your video is live — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>Great news! Your video <strong>{{ video_title }}</strong> has been processed and is now live on {{ site_name }}.</p>'
                    .$button('video_url', 'View Your Video'),
            ],
            'new-subscriber' => [
                'New Subscriber',
                'You have a new subscriber! — {{ site_name }}',
                '<p>Hi {{ username }},</p><p><strong>{{ subscriber_name }}</strong> just subscribed to your channel on {{ site_name }}!</p>'
                    .$button('channel_url', 'View Your Channel'),
            ],
            'contact-form-admin' => [
                'Contact Form (Admin)',
                'New contact message: {{ subject }} — {{ site_name }}',
                '<p>A new contact form submission has been received.</p><p><strong>From:</strong> {{ sender_name }} ({{ sender_email }})<br><strong>Subject:</strong> {{ subject }}</p><hr><p>{{ message }}</p><hr><p><em>Reply directly to {{ sender_email }} to respond.</em></p>',
            ],
            'video-approved' => [
                'Video Approved',
                'Your video has been approved — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>Your video <strong>{{ video_title }}</strong> has been approved and is now visible on {{ site_name }}.</p>'
                    .$button('video_url', 'View Your Video'),
            ],
            'video-rejected' => [
                'Video Rejected',
                'Your video was not approved — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>Unfortunately, your video <strong>{{ video_title }}</strong> was not approved for the following reason:</p><blockquote style="border-left:4px solid #ef4444;padding-left:16px;color:#6b7280;margin:16px 0;"><strong>{{ rejection_reason }}</strong></blockquote><p>If you believe this was a mistake, you may edit and resubmit your video.</p>',
            ],
            'withdrawal-approved' => [
                'Withdrawal Approved',
                'Your withdrawal has been approved — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>Your withdrawal request for <strong>{{ amount }}</strong> has been approved and is being processed.</p>',
            ],
            'withdrawal-rejected' => [
                'Withdrawal Rejected',
                'Your withdrawal was not approved — {{ site_name }}',
                '<p>Hi {{ username }},</p><p>Your withdrawal request for <strong>{{ amount }}</strong> was not approved.</p><blockquote style="border-left:4px solid #e5e7eb;padding-left:16px;color:#6b7280;">{{ rejection_reason }}</blockquote>',
            ],
            'admin-new-user' => [
                'Admin: New User Signup',
                'New user registered: {{ username }} — {{ site_name }}',
                '<p>A new user has registered on {{ site_name }}.</p><p><strong>Username:</strong> {{ username }}<br><strong>Email:</strong> {{ email }}<br><strong>Registered:</strong> {{ registered_at }}</p>',
            ],
            'admin-new-video' => [
                'Admin: New Video Upload',
                'New video uploaded: {{ video_title }} — {{ site_name }}',
                '<p>A new video has been uploaded on {{ site_name }}.</p><p><strong>Title:</strong> {{ video_title }}<br><strong>Uploaded by:</strong> {{ username }}</p>'
                    .$button('video_url', 'View Video'),
            ],
            'admin-new-report' => [
                'Admin: New Report',
                'New {{ report_type }} report: {{ report_reason }} — {{ site_name }}',
                '<p>A user has reported content on {{ site_name }}.</p><p><strong>Type:</strong> {{ report_type }}<br><strong>Reason:</strong> {{ report_reason }}<br><strong>Reported by:</strong> {{ reporter }}<br><strong>Content:</strong> {{ reported_content }}</p><p>{{ description }}</p>',
            ],
        ];
    }
};
