<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccbill_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->string('ccbill_subscription_id')->nullable()->index();
            // Deterministic fingerprint of (subscriptionId|eventType|timestamp) for idempotency.
            $table->string('fingerprint')->unique();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccbill_webhook_events');
    }
};
