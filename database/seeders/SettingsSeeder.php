<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['group' => 'general', 'key' => 'site_name', 'value' => 'HubTube', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_description', 'value' => 'Adult Video Platform', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_keywords', 'value' => 'videos, streaming, adult', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'primary_color', 'value' => '#ef4444', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'general', 'key' => 'registration_enabled', 'value' => '1', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'general', 'key' => 'email_verification_required', 'value' => '1', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'general', 'key' => 'age_verification_required', 'value' => '1', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'general', 'key' => 'minimum_age', 'value' => '18', 'type' => 'integer', 'is_public' => true],
            
            // Videos
            ['group' => 'videos', 'key' => 'max_upload_size_free', 'value' => '500', 'type' => 'integer', 'is_public' => false],
            ['group' => 'videos', 'key' => 'max_upload_size_pro', 'value' => '5000', 'type' => 'integer', 'is_public' => false],
            ['group' => 'videos', 'key' => 'max_daily_uploads_free', 'value' => '5', 'type' => 'integer', 'is_public' => false],
            ['group' => 'videos', 'key' => 'max_daily_uploads_pro', 'value' => '50', 'type' => 'integer', 'is_public' => false],
            ['group' => 'videos', 'key' => 'video_auto_approve', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'videos', 'key' => 'comments_enabled', 'value' => '1', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'videos', 'key' => 'comments_require_approval', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            
            // Payments
            ['group' => 'payments', 'key' => 'currency', 'value' => 'USD', 'type' => 'string', 'is_public' => true],
            ['group' => 'payments', 'key' => 'min_deposit', 'value' => '10', 'type' => 'integer', 'is_public' => true],
            ['group' => 'payments', 'key' => 'min_withdrawal', 'value' => '50', 'type' => 'integer', 'is_public' => true],
            ['group' => 'payments', 'key' => 'platform_fee_percent', 'value' => '20', 'type' => 'integer', 'is_public' => false],
            ['group' => 'payments', 'key' => 'gift_platform_cut', 'value' => '20', 'type' => 'integer', 'is_public' => false],
            ['group' => 'payments', 'key' => 'stripe_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'payments', 'key' => 'paypal_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'payments', 'key' => 'ccbill_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            
            // Storage
            ['group' => 'storage', 'key' => 'storage_driver', 'value' => 'local', 'type' => 'string', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cdn_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'storage', 'key' => 'wasabi_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'storage', 'key' => 'b2_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'storage', 'key' => 's3_enabled', 'value' => '0', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'storage', 'key' => 'ffmpeg_enabled', 'value' => '1', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'storage', 'key' => 'ffmpeg_path', 'value' => '/usr/bin/ffmpeg', 'type' => 'string', 'is_public' => false],
            ['group' => 'storage', 'key' => 'ffprobe_path', 'value' => '/usr/bin/ffprobe', 'type' => 'string', 'is_public' => false],
            ['group' => 'storage', 'key' => 'ffmpeg_threads', 'value' => '4', 'type' => 'integer', 'is_public' => false],
            
            // Streaming
            ['group' => 'streaming', 'key' => 'live_streaming_enabled', 'value' => '1', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'streaming', 'key' => 'gifts_enabled', 'value' => '1', 'type' => 'boolean', 'is_public' => true],
            ['group' => 'streaming', 'key' => 'max_stream_duration', 'value' => '480', 'type' => 'integer', 'is_public' => false],
            ['group' => 'streaming', 'key' => 'require_verification_to_stream', 'value' => '1', 'type' => 'boolean', 'is_public' => false],
            ['group' => 'streaming', 'key' => 'agora_token_expiry', 'value' => '86400', 'type' => 'integer', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
