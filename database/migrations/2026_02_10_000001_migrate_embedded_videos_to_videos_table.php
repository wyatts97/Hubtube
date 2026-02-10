<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('embedded_videos')) {
            return;
        }

        $embeddedVideos = DB::table('embedded_videos')->get();

        foreach ($embeddedVideos as $ev) {
            // Skip if already migrated (check by source_video_id)
            $exists = DB::table('videos')
                ->where('source_video_id', $ev->source_video_id)
                ->where('source_site', $ev->source_site)
                ->exists();

            if ($exists) {
                continue;
            }

            $slug = Str::slug($ev->title) ?: 'video';
            $baseSlug = $slug;
            $suffix = 2;
            while (DB::table('videos')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            DB::table('videos')->insert([
                'user_id' => 1, // admin user
                'uuid' => (string) Str::uuid(),
                'title' => $ev->title,
                'slug' => $slug,
                'description' => $ev->description,
                'duration' => $ev->duration ?? 0,
                'privacy' => 'public',
                'status' => 'processed',
                'is_short' => false,
                'is_embedded' => true,
                'is_featured' => $ev->is_featured ?? false,
                'is_approved' => $ev->is_published ?? true,
                'age_restricted' => true,
                'embed_url' => $ev->embed_url,
                'embed_code' => $ev->embed_code,
                'external_thumbnail_url' => $ev->thumbnail_url,
                'external_preview_url' => $ev->thumbnail_preview_url,
                'source_site' => $ev->source_site,
                'source_video_id' => $ev->source_video_id,
                'source_url' => $ev->source_url,
                'views_count' => $ev->views_count ?? 0,
                'tags' => $ev->tags,
                'category_id' => $ev->category_id,
                'published_at' => $ev->source_upload_date ?? $ev->imported_at ?? $ev->created_at,
                'processing_completed_at' => $ev->imported_at ?? $ev->created_at,
                'created_at' => $ev->created_at,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Remove migrated embedded videos
        DB::table('videos')->where('is_embedded', true)->delete();
    }
};
