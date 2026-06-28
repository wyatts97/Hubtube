<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventColumnToActivityLogTable extends Migration
{
    public function up()
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        if (Schema::connection($connection)->hasColumn($table, 'event')) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->string('event')->nullable()->after('subject_type');
        });
    }

    public function down()
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        if (! Schema::connection($connection)->hasColumn($table, 'event')) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->dropColumn('event');
        });
    }
}
