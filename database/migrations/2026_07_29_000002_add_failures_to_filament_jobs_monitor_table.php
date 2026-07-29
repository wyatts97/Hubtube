<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('queue_monitors')) {
            Schema::table('queue_monitors', function (Blueprint $table) {
                if (!Schema::hasColumn('queue_monitors', 'exception_class')) {
                    $table->string('exception_class')->nullable()->index()->after('exception_message');
                }
                if (!Schema::hasColumn('queue_monitors', 'exception')) {
                    $table->text('exception')->nullable()->after('exception_class');
                }
                if (!Schema::hasColumn('queue_monitors', 'failure_signature')) {
                    $table->string('failure_signature', 64)->nullable()->index()->after('exception');
                }
            });
        }

        if (!Schema::hasTable('queue_monitor_failure_groups')) {
            Schema::create('queue_monitor_failure_groups', function (Blueprint $table) {
                $table->id();
                $table->string('signature', 64)->unique();
                $table->string('exception_class')->index();
                $table->string('job_class')->nullable();
                $table->string('queue')->nullable();
                $table->text('message')->nullable();
                $table->unsignedInteger('occurrences_count')->default(0);
                $table->timestamp('first_occurred_at')->nullable();
                $table->timestamp('last_occurred_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->string('tenant_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_monitor_failure_groups');

        if (Schema::hasTable('queue_monitors')) {
            Schema::table('queue_monitors', function (Blueprint $table) {
                $table->dropColumn(['exception_class', 'exception', 'failure_signature']);
            });
        }
    }
};
