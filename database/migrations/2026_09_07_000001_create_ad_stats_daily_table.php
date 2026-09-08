<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day, per-dimension ad delivery counters.
 *
 * Deliberately pre-aggregated rather than one row per event. The site already
 * proves this pattern works at request volume in `visitor_daily`, which is
 * written by a single upsert with a DB::raw increment. Because aggregation here
 * is over identical dimension tuples, nothing analytical is lost versus raw
 * events — only per-event forensics (session paths, exact timestamps), which no
 * report needs. In exchange the table stays small enough to query directly.
 *
 * The lifetime `impressions_count`/`clicks_count` columns on video_ads and
 * sponsored_cards stay as they are; the Filament resources render them, and
 * they remain the cheap "all time" answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_stats_daily', function (Blueprint $table) {
            $table->id();

            $table->date('date');

            // Which subsystem served the ad. 'network' covers the settings-based
            // ad codes (banners, grid, rails, footer), which have no creative
            // row anywhere and were previously untracked entirely.
            $table->string('source', 20);

            // 0 for 'network' — a pasted ad code has no id.
            //
            // NOT nullable, and that is load-bearing: MySQL treats NULLs as
            // distinct inside a unique index, so a nullable ad_id would make
            // every network impression insert a fresh row instead of
            // incrementing the existing one. A sentinel 0 keeps the upsert
            // working.
            $table->unsignedBigInteger('ad_id')->default(0);

            $table->string('placement', 40);

            // ISO-3166-1 alpha-2 from Cloudflare's CF-IPCountry header.
            // 'XX' when absent (local dev, or a request that bypassed the CDN).
            $table->char('country', 2)->default('XX');

            $table->string('device', 10)->default('desktop');

            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            $table->timestamps();

            // The upsert target. Every dimension participates, so one row per
            // distinct combination per day.
            $table->unique(
                ['date', 'source', 'ad_id', 'placement', 'country', 'device'],
                'ad_stats_daily_dimensions_unique'
            );

            // Range scans by date drive every report.
            $table->index('date');
            $table->index(['date', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_stats_daily');
    }
};
