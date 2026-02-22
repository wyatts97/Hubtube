<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'subject',
        'body_html',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Available placeholder variables per template type.
     */
    public const PLACEHOLDERS = [
        'verify-email' => ['{{username}}', '{{verify_url}}', '{{site_name}}'],
        'reset-password' => ['{{username}}', '{{reset_url}}', '{{site_name}}', '{{expiry_minutes}}'],
        'video-published' => ['{{username}}', '{{video_title}}', '{{video_url}}', '{{site_name}}'],
        'new-subscriber' => ['{{username}}', '{{subscriber_name}}', '{{channel_url}}', '{{site_name}}'],
        'welcome' => ['{{username}}', '{{login_url}}', '{{site_name}}'],
        'contact-form-admin' => ['{{sender_name}}', '{{sender_email}}', '{{subject}}', '{{message}}', '{{site_name}}'],
        'video-approved' => ['{{username}}', '{{video_title}}', '{{video_url}}', '{{site_name}}'],
        'video-rejected' => ['{{username}}', '{{video_title}}', '{{rejection_reason}}', '{{site_name}}'],
        'withdrawal-approved' => ['{{username}}', '{{amount}}', '{{site_name}}'],
        'withdrawal-rejected' => ['{{username}}', '{{amount}}', '{{rejection_reason}}', '{{site_name}}'],
        'admin-new-user' => ['{{username}}', '{{email}}', '{{registered_at}}', '{{site_name}}'],
        'admin-new-video' => ['{{username}}', '{{video_title}}', '{{video_url}}', '{{site_name}}'],
        'admin-new-report' => ['{{reporter}}', '{{report_type}}', '{{report_reason}}', '{{reported_content}}', '{{description}}', '{{site_name}}'],
    ];

    /**
     * Default templates seeded on first install.
     */
    public static function defaults(): array
    {
        $site = '{{site_name}}';

        return [
            [
                'slug' => 'verify-email',
                'name' => 'Email Verification',
                'description' => 'Sent when a new user registers and needs to verify their email address.',
                'subject' => "Verify your email address — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>Thanks for signing up! Please verify your email address by clicking the button below.</p><p><a href="{{verify_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Verify Email Address</a></p><p>If you didn\'t create an account, no action is needed.</p>',
                'is_active' => true,
            ],
            [
                'slug' => 'reset-password',
                'name' => 'Password Reset',
                'description' => 'Sent when a user requests a password reset link.',
                'subject' => "Reset your password — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>We received a password reset request for your account. Click the button below to choose a new password.</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Reset Password</a></p><p>This link will expire in {{expiry_minutes}} minutes.</p><p>If you didn\'t request this, you can safely ignore this email.</p>',
                'is_active' => true,
            ],
            [
                'slug' => 'welcome',
                'name' => 'Welcome Email',
                'description' => 'Sent after a user successfully verifies their email.',
                'subject' => "Welcome to {$site}!",
                'body_html' => '<p>Hi {{username}},</p><p>Welcome to {{site_name}}! Your account is all set up and ready to go.</p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Get Started</a></p>',
                'is_active' => true,
            ],
            [
                'slug' => 'video-published',
                'name' => 'Video Published',
                'description' => 'Sent to the uploader when their video has been processed and published.',
                'subject' => "Your video is live — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>Great news! Your video <strong>{{video_title}}</strong> has been processed and is now live on {{site_name}}.</p><p><a href="{{video_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">View Your Video</a></p>',
                'is_active' => true,
            ],
            [
                'slug' => 'new-subscriber',
                'name' => 'New Subscriber',
                'description' => 'Sent to a channel owner when someone subscribes to their channel.',
                'subject' => "You have a new subscriber! — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p><strong>{{subscriber_name}}</strong> just subscribed to your channel on {{site_name}}!</p><p><a href="{{channel_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">View Your Channel</a></p>',
                'is_active' => true,
            ],
            [
                'slug' => 'contact-form-admin',
                'name' => 'Contact Form (Admin)',
                'description' => 'Sent to the admin when someone submits the contact form.',
                'subject' => "New contact message: {{subject}} — {$site}",
                'body_html' => '<p>A new contact form submission has been received.</p><p><strong>From:</strong> {{sender_name}} ({{sender_email}})<br><strong>Subject:</strong> {{subject}}</p><hr><p>{{message}}</p><hr><p><em>Reply directly to {{sender_email}} to respond.</em></p>',
                'is_active' => true,
            ],
            [
                'slug' => 'video-approved',
                'name' => 'Video Approved',
                'description' => 'Sent to the uploader when an admin approves their video (moderation mode).',
                'subject' => "Your video has been approved — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>Your video <strong>{{video_title}}</strong> has been approved and is now visible on {{site_name}}.</p><p><a href="{{video_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">View Your Video</a></p>',
                'is_active' => true,
            ],
            [
                'slug' => 'video-rejected',
                'name' => 'Video Rejected',
                'description' => 'Sent to the uploader when an admin rejects their video.',
                'subject' => "Your video was not approved — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>Unfortunately, your video <strong>{{video_title}}</strong> was not approved for the following reason:</p><blockquote style="border-left:4px solid #e5e7eb;padding-left:16px;color:#6b7280;">{{rejection_reason}}</blockquote><p>You can edit and resubmit your video if you\'d like.</p>',
                'is_active' => true,
            ],
            [
                'slug' => 'withdrawal-approved',
                'name' => 'Withdrawal Approved',
                'description' => 'Sent when an admin approves a withdrawal request.',
                'subject' => "Your withdrawal has been approved — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>Your withdrawal request for <strong>{{amount}}</strong> has been approved and is being processed.</p>',
                'is_active' => true,
            ],
            [
                'slug' => 'withdrawal-rejected',
                'name' => 'Withdrawal Rejected',
                'description' => 'Sent when an admin rejects a withdrawal request.',
                'subject' => "Your withdrawal was not approved — {$site}",
                'body_html' => '<p>Hi {{username}},</p><p>Your withdrawal request for <strong>{{amount}}</strong> was not approved.</p><blockquote style="border-left:4px solid #e5e7eb;padding-left:16px;color:#6b7280;">{{rejection_reason}}</blockquote>',
                'is_active' => true,
            ],
            [
                'slug' => 'admin-new-user',
                'name' => 'Admin: New User Signup',
                'description' => 'Sent to admin when a new user registers.',
                'subject' => "New user registered: {{username}} — {$site}",
                'body_html' => '<p>A new user has registered on {{site_name}}.</p><p><strong>Username:</strong> {{username}}<br><strong>Email:</strong> {{email}}<br><strong>Registered:</strong> {{registered_at}}</p>',
                'is_active' => true,
            ],
            [
                'slug' => 'admin-new-video',
                'name' => 'Admin: New Video Upload',
                'description' => 'Sent to admin when a new video is uploaded.',
                'subject' => "New video uploaded: {{video_title}} — {$site}",
                'body_html' => '<p>A new video has been uploaded on {{site_name}}.</p><p><strong>Title:</strong> {{video_title}}<br><strong>Uploaded by:</strong> {{username}}</p><p><a href="{{video_url}}" style="display:inline-block;padding:12px 24px;background-color:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">View Video</a></p>',
                'is_active' => true,
            ],
            [
                'slug' => 'admin-new-report',
                'name' => 'Admin: New Report',
                'description' => 'Sent to admin when content is reported by a user.',
                'subject' => "New {{report_type}} report: {{report_reason}} — {$site}",
                'body_html' => '<p>A user has reported content on {{site_name}}.</p><p><strong>Type:</strong> {{report_type}}<br><strong>Reason:</strong> {{report_reason}}<br><strong>Reported by:</strong> {{reporter}}<br><strong>Content:</strong> {{reported_content}}</p><p>{{description}}</p>',
                'is_active' => true,
            ],
        ];
    }

    /**
     * Get a template by slug, or return null if not found or inactive.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Render the subject line with variable substitution.
     */
    public function renderSubject(array $data = []): string
    {
        return $this->replacePlaceholders($this->subject, $data);
    }

    /**
     * Render the body HTML with variable substitution.
     */
    public function renderBody(array $data = []): string
    {
        return $this->replacePlaceholders($this->body_html, $data);
    }

    protected function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace("{{{$key}}}", (string) $value, $text);
        }

        // Always replace site_name
        $text = str_replace('{{site_name}}', config('app.name', 'HubTube'), $text);

        return $text;
    }
}
