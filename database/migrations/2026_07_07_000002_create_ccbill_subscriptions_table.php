<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccbill_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // CCBill subscription identifier (the source-of-truth key across webhooks).
            $table->string('ccbill_subscription_id')->index();
            // active | cancelled | expired | past_due | refunded | chargeback
            $table->string('status')->default('active')->index();
            $table->string('subscription_type')->default('recurring'); // recurring | single
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ccbill_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccbill_subscriptions');
    }
};
