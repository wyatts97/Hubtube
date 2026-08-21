<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from `failure_reason` (already reused for admin rejection reasons on
     * 'processed' videos) — this records when ProcessVideoJob had to ship a video as the
     * raw, un-transcoded original after a genuine transcoding failure, without disturbing
     * `status`, which many publish/approval/listing checks compare directly against 'processed'.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->text('processing_fallback_reason')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('processing_fallback_reason');
        });
    }
};
