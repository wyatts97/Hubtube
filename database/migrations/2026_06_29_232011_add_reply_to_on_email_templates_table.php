<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('fin-mail.table_names.templates') ?? 'email_templates';

        if (!Schema::hasTable($table) || Schema::hasColumn($table, 'reply_to')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->json('reply_to')->nullable();
        });
    }

    public function down(): void
    {
        $table = config('fin-mail.table_names.templates') ?? 'email_templates';

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'reply_to')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn('reply_to');
        });
    }
};
