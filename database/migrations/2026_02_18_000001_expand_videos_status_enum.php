<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the status enum to include download-related states
        // needed by the WP Import + Bunny Stream download pipeline.
        DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('pending', 'pending_download', 'downloading', 'download_failed', 'processing', 'processed', 'failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // First reset any new-status rows to 'pending' so the narrower enum doesn't reject them
        DB::table('videos')->whereIn('status', ['pending_download', 'downloading', 'download_failed'])->update(['status' => 'pending']);
        DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('pending', 'processing', 'processed', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
