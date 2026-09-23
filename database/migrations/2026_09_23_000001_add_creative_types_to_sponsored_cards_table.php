<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsored cards gain video and HTML creatives, and absorb the old
 * settings-based "Video Grid Ads".
 *
 * The grid ad codes are copied into HTML cards; their setting rows are left in
 * place (unused) so nothing is lost if this is ever rolled back by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->string('type', 10)->default('image')->after('external_id');
            $table->text('html_code')->nullable()->after('click_url');
            $table->text('mobile_html_code')->nullable()->after('html_code');
            $table->string('video_path')->nullable()->after('mobile_html_code');
            $table->string('video_url', 2048)->nullable()->after('video_path');
            $table->string('thumbnail_url')->nullable()->change();
            $table->string('click_url', 2048)->nullable()->change();
        });

        $settings = Schema::hasTable('settings')
            ? DB::table('settings')->pluck('value', 'key')->all()
            : [];

        // One frequency for all cards. The old per-card value was only ever read
        // from whichever card happened to come first, so the grid-ad frequency
        // is the one visitors actually saw most consistently.
        $frequency = (int) ($settings['video_grid_ad_frequency'] ?? 0)
            ?: (int) (DB::table('sponsored_cards')->value('frequency') ?? 8);

        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'sponsored_card_frequency'],
                ['value' => (string) max(2, min(50, $frequency)), 'group' => 'ads', 'type' => 'integer'],
            );
        }

        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });

        $this->copyGridAds($settings);

        Cache::forget('settings:all');
        Cache::forget('settings:group:ads');
    }

    /**
     * Copy each configured grid ad variant into an HTML card.
     *
     * Mirrors the old Controller::buildGridAdVariants(): variant 1 falls back to
     * the pre-multi-variant keys.
     */
    private function copyGridAds(array $settings): void
    {
        $enabled = filter_var($settings['video_grid_ad_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $count = max(1, (int) ($settings['video_grid_ad_count'] ?? 1));
        $now = now();

        for ($n = 1; $n <= $count; $n++) {
            $code = (string) ($settings["video_grid_ad_{$n}_code"] ?? '');
            $mobile = (string) ($settings["video_grid_ad_{$n}_mobile_code"] ?? '');

            if ($n === 1) {
                $code = $code ?: (string) ($settings['video_grid_ad_code'] ?? '');
                $mobile = $mobile ?: (string) ($settings['video_grid_ad_mobile_code'] ?? '');
            }

            if (trim($code) === '') {
                continue;
            }

            $categories = json_decode((string) ($settings["video_grid_ad_{$n}_categories"] ?? '[]'), true) ?: [];
            $categories = array_values(array_map('intval', $categories));

            DB::table('sponsored_cards')->insert([
                'type' => 'html',
                'title' => "Grid ad {$n}",
                'html_code' => $code,
                'mobile_html_code' => $mobile !== '' ? $mobile : null,
                'category_ids' => $categories ? json_encode($categories) : null,
                'weight' => 1,
                'is_active' => $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sponsored_cards')->where('type', 'html')->delete();

        Schema::table('sponsored_cards', function (Blueprint $table) {
            $table->integer('frequency')->default(8)->after('target_pages');
            $table->dropColumn(['type', 'html_code', 'mobile_html_code', 'video_path', 'video_url']);
        });
    }
};
