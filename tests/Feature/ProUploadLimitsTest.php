<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProUploadLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_user_can_upload_until_pro_daily_cap(): void
    {
        Setting::set('max_daily_uploads_pro', 2, 'site', 'integer');
        Setting::set('max_daily_uploads_free', 1, 'site', 'integer');

        $user = User::factory()->create(['is_pro' => true]);
        Video::factory()->count(2)->create(['user_id' => $user->id, 'created_at' => now()]);

        $this->assertFalse($user->canUpload());
    }

    public function test_free_user_can_upload_until_free_daily_cap(): void
    {
        Setting::set('max_daily_uploads_pro', 50, 'site', 'integer');
        Setting::set('max_daily_uploads_free', 1, 'site', 'integer');

        $user = User::factory()->create(['is_pro' => false]);
        Video::factory()->create(['user_id' => $user->id, 'created_at' => now()]);

        $this->assertFalse($user->canUpload());
    }

    public function test_pro_user_has_higher_max_video_size(): void
    {
        Setting::set('max_upload_size_pro', 1024, 'site', 'integer');
        Setting::set('max_upload_size_free', 500, 'site', 'integer');

        $pro = User::factory()->create(['is_pro' => true]);
        $free = User::factory()->create(['is_pro' => false]);

        $this->assertEquals(1024 * 1048576, $pro->max_video_size);
        $this->assertEquals(500 * 1048576, $free->max_video_size);
    }
}
