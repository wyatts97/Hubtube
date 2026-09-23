<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SponsoredCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sponsored cards: image, video and HTML creatives in the grid, which replaced
 * the old settings-based "Video Grid Ads".
 */
class SponsoredCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_type_only_ships_the_fields_it_needs(): void
    {
        $html = SponsoredCard::factory()->html()->create(['description' => 'hidden']);
        $image = SponsoredCard::factory()->create();
        $video = SponsoredCard::factory()->create([
            'type' => SponsoredCard::TYPE_VIDEO,
            'video_path' => 'sponsored/video/ad.mp4',
        ]);

        $payloads = collect(SponsoredCard::getForPage('home'))->keyBy('id');

        $this->assertSame('<div class="ad">ad</div>', $payloads[$html->id]['html_code']);
        $this->assertSame('<div class="ad">ad</div>', $payloads[$html->id]['mobile_html_code']);
        $this->assertArrayNotHasKey('description', $payloads[$html->id]);
        $this->assertArrayNotHasKey('click_url', $payloads[$html->id]);

        $this->assertArrayNotHasKey('html_code', $payloads[$image->id]);
        $this->assertSame('/storage/sponsored/example.jpg', $payloads[$image->id]['thumbnail_url']);

        $this->assertSame('/storage/sponsored/video/ad.mp4', $payloads[$video->id]['video_src']);
    }

    public function test_empty_targeting_is_stored_as_null(): void
    {
        $card = SponsoredCard::factory()->create([
            'target_pages' => [],
            'target_roles' => [],
            'category_ids' => ['3'],
        ]);

        $card->refresh();
        $this->assertNull($card->target_pages);
        $this->assertNull($card->target_roles);
        $this->assertSame([3], $card->category_ids);
    }

    public function test_listing_pages_receive_the_shared_frequency(): void
    {
        Setting::set('sponsored_card_frequency', 5, 'ads', 'integer');

        $this->get('/trending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sponsoredFrequency', 5)->etc());
    }

    public function test_the_images_page_only_gets_html_cards(): void
    {
        SponsoredCard::factory()->create(['target_pages' => null]);
        $html = SponsoredCard::factory()->html()->create(['target_pages' => null]);

        $this->get('/images')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sponsoredCards', 1)
                ->where('sponsoredCards.0.id', $html->id)
                ->etc());
    }

    public function test_repeat_impressions_do_not_inflate_the_card_counter(): void
    {
        $card = SponsoredCard::factory()->create();

        $this->postJson("/api/sponsored/{$card->id}/impression")->assertOk();
        $this->postJson("/api/sponsored/{$card->id}/impression")->assertOk();

        $this->assertSame(1, (int) $card->fresh()->impressions_count);
    }

    public function test_tracking_an_unknown_card_records_nothing(): void
    {
        $this->postJson('/api/sponsored/999999/click')->assertOk();

        $this->assertSame(0, DB::table('ad_stats_daily')->count());
    }

    public function test_old_grid_ad_settings_become_html_cards(): void
    {
        $migration = require database_path('migrations/2026_09_23_000001_add_creative_types_to_sponsored_cards_table.php');
        $migration->down();

        foreach ([
            'video_grid_ad_enabled' => '1',
            'video_grid_ad_frequency' => '6',
            'video_grid_ad_count' => '2',
            'video_grid_ad_code' => '<b>legacy</b>',
            'video_grid_ad_2_code' => '<b>second</b>',
            'video_grid_ad_2_mobile_code' => '<b>second mobile</b>',
            'video_grid_ad_2_categories' => '["4"]',
        ] as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value, 'group' => 'ads', 'type' => 'string']);
        }

        $migration->up();

        $cards = SponsoredCard::orderBy('id')->get();
        $this->assertCount(2, $cards);
        $this->assertSame(['html', 'html'], $cards->pluck('type')->all());
        $this->assertSame('<b>legacy</b>', $cards[0]->html_code);
        $this->assertSame('<b>second mobile</b>', $cards[1]->mobile_html_code);
        $this->assertSame([4], $cards[1]->category_ids);
        $this->assertTrue($cards[0]->is_active);
        $this->assertSame(6, (int) Setting::get('sponsored_card_frequency'));
    }
}
