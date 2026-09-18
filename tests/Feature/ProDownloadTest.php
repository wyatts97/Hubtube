<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_pro_user_can_download_video(): void
    {
        $pro = User::factory()->create(['is_pro' => true]);
        $video = Video::factory()->create([
            'title' => 'Test Video',
            'video_path' => 'videos/test/test.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original'],
        ]);
        Storage::disk('public')->put($video->video_path, 'fake video content');

        $response = $this->actingAs($pro)->get("/videos/{$video->id}/download");

        $response->assertOk();
        $response->assertDownload('test_video.mp4');
    }

    public function test_video_owner_can_download_own_video(): void
    {
        $owner = User::factory()->create(['is_pro' => false]);
        $video = Video::factory()->create([
            'user_id' => $owner->id,
            'title' => 'My Video',
            'video_path' => 'videos/test/owner.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original'],
        ]);
        Storage::disk('public')->put($video->video_path, 'fake video content');

        $response = $this->actingAs($owner)->get("/videos/{$video->id}/download");

        $response->assertOk();
    }

    public function test_admin_can_download_any_video(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_pro' => false]);
        $video = Video::factory()->create([
            'title' => 'Admin Video',
            'video_path' => 'videos/test/admin.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original'],
        ]);
        Storage::disk('public')->put($video->video_path, 'fake video content');

        $response = $this->actingAs($admin)->get("/videos/{$video->id}/download");

        $response->assertOk();
    }

    public function test_free_user_cannot_download_other_videos(): void
    {
        $user = User::factory()->create(['is_pro' => false]);
        $video = Video::factory()->create([
            'title' => 'Protected Video',
            'video_path' => 'videos/test/protected.mp4',
            'storage_disk' => 'public',
        ]);
        Storage::disk('public')->put($video->video_path, 'fake video content');

        $response = $this->actingAs($user)->get("/videos/{$video->id}/download");

        $response->assertForbidden();
    }

    public function test_download_rate_limit(): void
    {
        $pro = User::factory()->create(['is_pro' => true]);
        $video = Video::factory()->create([
            'title' => 'Rate Limit Video',
            'video_path' => 'videos/test/rate.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original'],
        ]);
        Storage::disk('public')->put($video->video_path, 'fake video content');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($pro)->get("/videos/{$video->id}/download");
        }

        $response = $this->actingAs($pro)->get("/videos/{$video->id}/download");
        $response->assertStatus(429);
    }

    /**
     * The quality keys used to be sorted as strings, which orders them
     * original, 720p, 480p, 360p, 1080p — so the highest rendition was never
     * picked when a 1080p existed.
     */
    public function test_the_highest_rendition_is_served_not_the_alphabetically_last(): void
    {
        $pro = User::factory()->create(['is_pro' => true]);
        $video = Video::factory()->create([
            'title' => 'Ladder',
            'video_path' => 'videos/ladder/source.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original', '720p', '1080p'],
        ]);

        Storage::disk('public')->put($video->video_path, 'the original');
        Storage::disk('public')->put('videos/ladder/processed/720p.mp4', 'seven twenty');
        Storage::disk('public')->put('videos/ladder/processed/1080p.mp4', 'ten eighty');

        $this->assertSame('videos/ladder/processed/1080p.mp4', $video->bestDownloadPath());
        $this->actingAs($pro)->get("/videos/{$video->id}/download")->assertOk();
    }

    /**
     * A label whose file never made it to disk must fall through to the next
     * one down, not to the original — the original is the largest file on the
     * box, which is what storage reclaim exists to shrink.
     */
    public function test_a_missing_rendition_falls_through_to_the_next_one_down(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'videos/gappy/source.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original', '480p', '1080p'],
        ]);

        Storage::disk('public')->put($video->video_path, 'the original');
        Storage::disk('public')->put('videos/gappy/processed/480p.mp4', 'four eighty');

        $this->assertSame('videos/gappy/processed/480p.mp4', $video->bestDownloadPath());
    }

    public function test_the_original_is_the_fallback_when_no_rendition_exists(): void
    {
        $video = Video::factory()->create([
            'video_path' => 'videos/bare/source.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original', '720p'],
        ]);

        Storage::disk('public')->put($video->video_path, 'the original');

        $this->assertSame('videos/bare/source.mp4', $video->bestDownloadPath());
    }

    public function test_a_video_with_nothing_on_disk_is_not_downloadable(): void
    {
        $pro = User::factory()->create(['is_pro' => true]);
        $video = Video::factory()->create([
            'video_path' => 'videos/missing/source.mp4',
            'storage_disk' => 'public',
            'qualities_available' => ['original'],
        ]);

        $this->assertNull($video->bestDownloadPath());
        $this->actingAs($pro)->get("/videos/{$video->id}/download")->assertNotFound();
    }
}
