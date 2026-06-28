<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
