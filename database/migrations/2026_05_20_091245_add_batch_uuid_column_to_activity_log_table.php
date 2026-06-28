<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBatchUuidColumnToActivityLogTable extends Migration
{
    public function up()
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        if (Schema::connection($connection)->hasColumn($table, 'batch_uuid')) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down()
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        if (! Schema::connection($connection)->hasColumn($table, 'batch_uuid')) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
}
