<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_index_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('engine', 32)->default('indexnow');
            $table->string('url', 1024);
            $table->string('url_hash', 64)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['engine', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index_submissions');
    }
};
