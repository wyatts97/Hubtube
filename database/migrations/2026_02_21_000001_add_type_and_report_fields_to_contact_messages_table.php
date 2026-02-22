<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('type', 20)->default('contact')->after('id'); // 'contact' or 'report'
            $table->foreignId('user_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->foreignId('report_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['report_id']);
            $table->dropColumn(['type', 'user_id', 'report_id']);
        });
    }
};
