<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoAd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProAdSuppressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_users_receive_empty_ad_payload(): void
    {
        Setting::set('pro_ad_free', true, 'pro', 'boolean');
        Setting::set('video_ad_pre_roll_enabled', true, 'ads', 'boolean');

        $category = Category::factory()->create();
        VideoAd::factory()->create([
            'name' => 'Test Ad',
            'type' => 'mp4',
            'content' => 'https://example.com/ad.mp4',
            'placement' => 'pre_roll',
        ]);

        $user = User::factory()->create(['is_pro' => true]);

        $response = $this->actingAs($user)->getJson('/api/video-ads?category_id=' . $category->id);

        $response->assertOk()
            ->assertJsonPath('ads.pre_roll', [])
            ->assertJsonPath('ads.mid_roll', [])
            ->assertJsonPath('ads.post_roll', [])
            ->assertJsonPath('ads.outstream', []);
    }

    public function test_non_pro_users_still_receive_ads(): void
    {
        Setting::set('pro_ad_free', true, 'pro', 'boolean');
        Setting::set('video_ad_pre_roll_enabled', true, 'ads', 'boolean');

        $category = Category::factory()->create();
        VideoAd::factory()->create([
            'name' => 'Test Ad',
            'type' => 'mp4',
            'content' => 'https://example.com/ad.mp4',
            'placement' => 'pre_roll',
        ]);

        $user = User::factory()->create(['is_pro' => false]);

        $response = $this->actingAs($user)->getJson('/api/video-ads?category_id=' . $category->id);

        $response->assertOk();
        $this->assertNotEmpty($response->json('ads.pre_roll'));
    }

    public function test_pro_users_receive_ads_when_ad_free_disabled(): void
    {
        Setting::set('pro_ad_free', false, 'pro', 'boolean');
        Setting::set('video_ad_pre_roll_enabled', true, 'ads', 'boolean');

        $category = Category::factory()->create();
        VideoAd::factory()->create([
            'name' => 'Test Ad',
            'type' => 'mp4',
            'content' => 'https://example.com/ad.mp4',
            'placement' => 'pre_roll',
        ]);

        $user = User::factory()->create(['is_pro' => true]);

        $response = $this->actingAs($user)->getJson('/api/video-ads?category_id=' . $category->id);

        $response->assertOk();
        $this->assertNotEmpty($response->json('ads.pre_roll'));
    }
}
