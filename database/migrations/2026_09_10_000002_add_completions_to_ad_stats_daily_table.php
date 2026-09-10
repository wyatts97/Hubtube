<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad completions, newly measurable now that creatives are served as VAST.
 *
 * A VAST document carries its own `<Tracking event="complete">` URL, which the
 * player fires when the creative plays to the end. That is a signal the custom
 * overlay never produced — it knew when an ad *started* (the impression) and
 * nothing after — so completion rate per creative was previously unanswerable.
 *
 * Only `complete` is collected, not the full quartile set: quartiles multiply
 * the beacon volume by five for a curve nothing in the admin currently plots,
 * and the column can be joined by a later migration if that changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_stats_daily', function (Blueprint $table) {
            $table->unsignedBigInteger('completions')->default(0)->after('clicks');
        });
    }

    public function down(): void
    {
        Schema::table('ad_stats_daily', function (Blueprint $table) {
            $table->dropColumn('completions');
        });
    }
};
