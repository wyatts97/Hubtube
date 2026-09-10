<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAST requires media metadata the ad system never had a reason to store.
 *
 * `<Linear>` mandates a `<Duration>`, and every `<MediaFile>` must carry width
 * and height attributes. Serving local MP4 creatives as VAST (so one ad path
 * covers every creative type) therefore needs all three probed and persisted —
 * ProcessAdCreativeJob only ever transcoded, it never inspected the source.
 *
 * Nullable because existing rows predate the probe: VastBuilder falls back to a
 * nominal 30s / 640x360 until `ads:backfill-media-metadata` has run, which both
 * IMA and Fluid Player tolerate (only skip-offset math is affected).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_ads', function (Blueprint $table) {
            $table->unsignedInteger('duration')->nullable()->after('hls_status');
            $table->unsignedSmallInteger('width')->nullable()->after('duration');
            $table->unsignedSmallInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('video_ads', function (Blueprint $table) {
            $table->dropColumn(['duration', 'width', 'height']);
        });
    }
};
