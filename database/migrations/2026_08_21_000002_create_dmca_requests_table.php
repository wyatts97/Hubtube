<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dmca_requests', function (Blueprint $table) {
            $table->id();

            // Complainant details — usually not a registered site user (rights holders,
            // agencies, or their legal representatives), so no user_id relationship.
            $table->string('complainant_name');
            $table->string('complainant_email');
            $table->string('complainant_company')->nullable();

            $table->text('copyrighted_work_description');
            $table->text('infringing_urls');

            // Best-effort link to the reported video, resolved from the submitted
            // URL(s) at submission time so admins can jump straight to the content.
            $table->foreignId('video_id')->nullable()->constrained()->nullOnDelete();

            // Standard DMCA good-faith and accuracy/perjury statements — both must be
            // affirmatively checked by the submitter, plus a typed signature.
            $table->boolean('good_faith_statement')->default(false);
            $table->boolean('accuracy_statement')->default(false);
            $table->string('signature');

            $table->enum('status', ['pending', 'actioned', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['video_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dmca_requests');
    }
};
