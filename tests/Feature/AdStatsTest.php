<?php

namespace Tests\Feature;

use App\Models\AdStatDaily;
use App\Models\Setting;
use App\Models\VideoAd;
use App\Support\DeviceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ad delivery statistics.
 *
 * The table is pre-aggregated: one row per (date, source, ad_id, placement,
 * country, device), incremented in place. These tests pin the two properties
 * that make that safe — that identical events collapse onto one row, and that
 * differing dimensions do not.
 */
class AdStatsTest extends TestCase
{
    use RefreshDatabase;

    private function ad(): VideoAd
    {
        return VideoAd::factory()->create(['placement' => 'pre_roll']);
    }

    public function test_an_impression_is_recorded_with_its_dimensions(): void
    {
        $ad = $this->ad();

        $this->withHeaders(['CF-IPCountry' => 'DE'])
            ->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])
            ->assertOk();

        $row = AdStatDaily::sole();

        $this->assertSame(AdStatDaily::SOURCE_VIDEO_AD, $row->source);
        $this->assertSame($ad->id, $row->ad_id);
        $this->assertSame('pre_roll', $row->placement);
        $this->assertSame('DE', $row->country);
        $this->assertSame(1, $row->impressions);
        $this->assertSame(0, $row->clicks);

        // The lifetime counter the Filament resources render still moves.
        $this->assertSame(1, $ad->fresh()->impressions_count);
    }

    public function test_a_repeat_impression_within_the_dedupe_window_is_ignored(): void
    {
        $ad = $this->ad();
        $payload = ['ad_id' => $ad->id, 'placement' => 'pre_roll'];

        $this->postJson('/api/ad-impression', $payload)->assertOk();
        $this->postJson('/api/ad-impression', $payload)->assertOk();

        $this->assertSame(1, AdStatDaily::count());
        $this->assertSame(1, AdStatDaily::sole()->impressions);
    }

    public function test_differing_dimensions_produce_separate_rows(): void
    {
        $ad = $this->ad();

        // Two different viewers, in two countries. The dedupe key intentionally
        // does not include country or device — one viewer has exactly one of
        // each, and folding them in would let a forged header buy a second
        // impression — so distinguishing these requires distinct viewers.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->withHeaders(['CF-IPCountry' => 'DE'])
            ->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])
            ->withHeaders(['CF-IPCountry' => 'FR'])
            ->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();

        // Same viewer as the first, but a different break, so a different key.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->withHeaders(['CF-IPCountry' => 'DE'])
            ->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'mid_roll'])->assertOk();

        $this->assertSame(3, AdStatDaily::count());
    }

    public function test_one_viewer_cannot_forge_extra_impressions_with_a_country_header(): void
    {
        $ad = $this->ad();

        foreach (['DE', 'FR', 'US', 'GB'] as $country) {
            $this->withHeaders(['CF-IPCountry' => $country])
                ->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();
        }

        // Rotating the header must not multiply the count.
        $this->assertSame(1, AdStatDaily::count());
        $this->assertSame(1, AdStatDaily::sole()->impressions);
    }

    public function test_a_missing_country_header_buckets_as_xx(): void
    {
        $ad = $this->ad();

        $this->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();

        $this->assertSame('XX', AdStatDaily::sole()->country);
    }

    public function test_a_spoofed_country_header_is_rejected(): void
    {
        $ad = $this->ad();

        $this->withHeaders(['CF-IPCountry' => 'not-a-country'])
            ->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();

        $this->assertSame('XX', AdStatDaily::sole()->country);
    }

    public function test_clicks_and_impressions_accumulate_on_the_same_row(): void
    {
        $ad = $this->ad();

        $this->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();
        $this->postJson('/api/ad-click', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();

        // Same dimension tuple, so one row carrying both counters.
        $row = AdStatDaily::sole();
        $this->assertSame(1, $row->impressions);
        $this->assertSame(1, $row->clicks);
    }

    public function test_network_slots_are_recorded_without_a_creative_id(): void
    {
        $this->postJson('/api/ad-slot-impression', ['placement' => 'footer'])->assertOk();

        $row = AdStatDaily::sole();
        $this->assertSame(AdStatDaily::SOURCE_NETWORK, $row->source);
        // 0, not null: a nullable column cannot be de-duplicated by a unique
        // index, so every network impression would insert a new row.
        $this->assertSame(0, $row->ad_id);
        $this->assertSame('footer', $row->placement);
    }

    public function test_two_network_slots_on_one_page_collapse_onto_one_row(): void
    {
        $this->postJson('/api/ad-slot-impression', ['placement' => 'footer'])->assertOk();
        $this->postJson('/api/ad-slot-impression', ['placement' => 'grid'])->assertOk();

        $this->assertSame(2, AdStatDaily::count());
        $this->assertSame(
            ['footer', 'grid'],
            AdStatDaily::orderBy('placement')->pluck('placement')->all()
        );
    }

    public function test_a_slot_impression_without_a_placement_is_dropped(): void
    {
        $this->postJson('/api/ad-slot-impression', [])->assertOk();

        $this->assertSame(0, AdStatDaily::count());
    }

    public function test_the_reporting_date_follows_the_site_timezone_setting(): void
    {
        // A timezone far enough ahead of UTC that its calendar date differs for
        // part of the day. Asserting equality against the setting's own "now"
        // is what pins the behaviour without being clock-dependent.
        Setting::set('site_timezone', 'Pacific/Kiritimati', 'general', 'string');

        $ad = $this->ad();
        $this->postJson('/api/ad-impression', ['ad_id' => $ad->id, 'placement' => 'pre_roll'])->assertOk();

        $this->assertSame(
            now('Pacific/Kiritimati')->toDateString(),
            AdStatDaily::sole()->date->toDateString()
        );
    }
}
