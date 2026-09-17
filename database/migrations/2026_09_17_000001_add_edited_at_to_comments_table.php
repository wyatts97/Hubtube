<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            // Drives the "(edited)" marker. Null means never edited, which is
            // not the same as "edited at created_at".
            $table->timestamp('edited_at')->nullable()->after('is_approved');

            // Replies are paginated per parent comment now, so they need their
            // own ordering index; the existing ones are all video-scoped.
            $table->index(['parent_id', 'created_at'], 'comments_parent_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_parent_created_index');
            $table->dropColumn('edited_at');
        });
    }
};
