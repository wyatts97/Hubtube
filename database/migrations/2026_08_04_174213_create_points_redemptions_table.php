<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('points_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('points_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();
            $table->unsignedInteger('points_spent');
            $table->unsignedInteger('days_granted');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('active'); // active, expired, revoked
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_redemptions');
    }
};
