<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (!Schema::hasColumn('videos', 'suppress_notifications')) {
                $table->boolean('suppress_notifications')
                    ->default(false)
                    ->after('requires_schedule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'suppress_notifications')) {
                $table->dropColumn('suppress_notifications');
            }
        });
    }
};
