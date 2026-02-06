<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('is_embedded')->default(false)->after('is_short');
            $table->string('embed_url', 500)->nullable()->after('video_path');
            $table->text('embed_code')->nullable()->after('embed_url');
            $table->string('external_thumbnail_url', 500)->nullable()->after('thumbnail');
            $table->string('external_preview_url', 500)->nullable()->after('preview_path');
            $table->string('source_site', 50)->nullable()->after('is_embedded');
            $table->string('source_video_id')->nullable()->after('source_site');
            $table->string('source_url', 500)->nullable()->after('source_video_id');

            $table->index('is_embedded');
            $table->index(['source_site', 'source_video_id']);
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['is_embedded']);
            $table->dropIndex(['source_site', 'source_video_id']);
            $table->dropColumn([
                'is_embedded',
                'embed_url',
                'embed_code',
                'external_thumbnail_url',
                'external_preview_url',
                'source_site',
                'source_video_id',
                'source_url',
            ]);
        });
    }
};
