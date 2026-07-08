<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // CCBill FlexForms dynamic-pricing fields.
            // Periods are expressed in DAYS (CCBill semantics), not month/year.
            $table->decimal('ccbill_initial_price', 8, 2)->nullable()->after('stripe_price_id');
            $table->unsignedInteger('ccbill_initial_period')->nullable()->after('ccbill_initial_price');
            $table->decimal('ccbill_recurring_price', 8, 2)->nullable()->after('ccbill_initial_period');
            $table->unsignedInteger('ccbill_recurring_period')->nullable()->after('ccbill_recurring_price');
            $table->unsignedInteger('ccbill_num_rebills')->nullable()->after('ccbill_recurring_period');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'ccbill_initial_price',
                'ccbill_initial_period',
                'ccbill_recurring_price',
                'ccbill_recurring_period',
                'ccbill_num_rebills',
            ]);
        });
    }
};
