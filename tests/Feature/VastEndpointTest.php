<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\VideoAd;
use App\Services\VastBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers /api/vast/{placement}, which serves the site's own ad inventory as
 * VAST so the player has a single ad path for every creative type.
 *
 * The recurring assertion here is that the endpoint *always* answers with
 * well-formed VAST, including when it has nothing to serve. A player that
 * receives a 404 or a truncated document waits out its VAST timeout before
 * falling back to content, so "no ad booked" would otherwise cost the viewer
 * several seconds of black screen.
 */
class VastEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string $placement = 'pre_roll'): void
    {
        Setting::set("video_ad_{$placement}_enabled", true, 'ads', 'boolean');
    }

    private function xml(string $body): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($parsed, 'Response was not well-formed XML: '.$body);

        return $parsed;
    }

    public function test_an_mp4_creative_is_served_as_an_inline_linear_ad(): void
    {
        $this->enable();

        $ad = VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/promo.mp4',
            'duration' => 42,
            'width' => 1280,
            'height' => 720,
            'click_url' => 'https://example.com/landing',
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->assertOk()->getContent());

        $this->assertSame('3.0', (string) $xml['version']);
        $this->assertSame((string) $ad->id, (string) $xml->Ad['id']);

        $linear = $xml->Ad->InLine->Creatives->Creative->Linear;
        $this->assertSame('00:00:42', (string) $linear->Duration);
        $this->assertStringContainsString('promo.mp4', (string) $linear->MediaFiles->MediaFile);
        $this->assertSame('1280', (string) $linear->MediaFiles->MediaFile['width']);
        $this->assertSame('720', (string) $linear->MediaFiles->MediaFile['height']);
        $this->assertSame(
            'https://example.com/landing',
            (string) $linear->VideoClicks->ClickThrough
        );
    }

    public function test_only_the_progressive_mp4_is_advertised_even_when_hls_is_ready(): void
    {
        // Fluid Player validates an ad by fetching only the FIRST MediaFile and
        // discards the whole ad unless that response looks like playable video.
        // An HLS playlist fails that test outside Safari, so advertising one --
        // in any position -- costs the ad entirely. Regression test for
        // pre-rolls silently never playing.
        $this->enable();

        $ad = VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/promo.mp4',
        ]);

        // Set the HLS fields *after* creation: the model's created hook
        // dispatches ProcessAdCreativeJob, which runs inline on the sync queue
        // and writes hls_status itself, clobbering anything set at create time.
        $ad->update([
            'hls_path' => 'media/ads/hls/1/playlist.m3u8',
            'hls_status' => 'ready',
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());
        $files = $xml->Ad->InLine->Creatives->Creative->Linear->MediaFiles->MediaFile;

        $this->assertCount(1, $files);
        $this->assertSame('video/mp4', (string) $files[0]['type']);
        $this->assertSame('progressive', (string) $files[0]['delivery']);
        $this->assertStringNotContainsString('.m3u8', (string) $files[0]);
    }

    public function test_an_unconverted_mp4_offers_the_progressive_file(): void
    {
        $this->enable();

        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/promo.mp4',
            'hls_status' => 'pending',
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());
        $files = $xml->Ad->InLine->Creatives->Creative->Linear->MediaFiles->MediaFile;

        $this->assertCount(1, $files);
        $this->assertSame('video/mp4', (string) $files[0]['type']);
    }

    public function test_an_unprobed_creative_falls_back_to_nominal_metadata(): void
    {
        $this->enable();

        // Creatives uploaded before the probe existed have no duration; VAST
        // still requires one, and an invalid document means no ad at all.
        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/legacy.mp4',
            'duration' => null,
            'width' => null,
            'height' => null,
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());
        $linear = $xml->Ad->InLine->Creatives->Creative->Linear;

        $this->assertSame('00:00:'.VastBuilder::FALLBACK_DURATION, (string) $linear->Duration);
        $this->assertSame(
            (string) VastBuilder::FALLBACK_WIDTH,
            (string) $linear->MediaFiles->MediaFile['width']
        );
    }

    public function test_a_third_party_tag_is_served_as_a_wrapper(): void
    {
        $this->enable();

        VideoAd::factory()->create([
            'type' => 'vast',
            'placement' => 'pre_roll',
            'content' => 'https://ads.example.net/tag.xml',
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());

        $this->assertSame(
            'https://ads.example.net/tag.xml',
            (string) $xml->Ad->Wrapper->VASTAdTagURI
        );
        // Our own beacons ride along so network creatives land in ad_stats_daily
        // on the same footing as local ones.
        $this->assertStringContainsString('/api/vast/track/impression', (string) $xml->Ad->Wrapper->Impression);
    }

    public function test_an_html_creative_is_served_as_a_non_linear_ad(): void
    {
        $this->enable();

        VideoAd::factory()->create([
            'type' => 'html',
            'placement' => 'pre_roll',
            'content' => '<div class="promo">Buy things</div>',
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());
        $nonLinear = $xml->Ad->InLine->Creatives->Creative->NonLinearAds->NonLinear;

        $this->assertStringContainsString('Buy things', (string) $nonLinear->HTMLResource);
        $this->assertSame((string) VastBuilder::NONLINEAR_WIDTH, (string) $nonLinear['width']);
    }

    public function test_the_skip_offset_follows_the_configured_delay(): void
    {
        $this->enable();
        Setting::set('video_ad_pre_roll_skip_after', 7, 'ads', 'integer');

        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/promo.mp4',
            'duration' => 30,
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());

        $this->assertSame(
            '00:00:07',
            (string) $xml->Ad->InLine->Creatives->Creative->Linear['skipoffset']
        );
    }

    public function test_a_skip_offset_past_the_end_of_the_creative_is_omitted(): void
    {
        $this->enable();
        Setting::set('video_ad_pre_roll_skip_after', 30, 'ads', 'integer');

        // A skip button that never becomes clickable is worse than no skip
        // button, so the offset is dropped rather than advertised.
        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/short.mp4',
            'duration' => 6,
        ]);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->getContent());

        $this->assertNull($xml->Ad->InLine->Creatives->Creative->Linear['skipoffset']);
    }

    public function test_a_disabled_placement_returns_the_empty_document(): void
    {
        VideoAd::factory()->create(['type' => 'mp4', 'placement' => 'pre_roll', 'file_path' => 'a.mp4']);

        $xml = $this->xml($this->get('/api/vast/pre_roll')->assertOk()->getContent());

        $this->assertCount(0, $xml->Ad);
    }

    public function test_an_empty_pool_returns_the_empty_document(): void
    {
        $this->enable();

        $xml = $this->xml($this->get('/api/vast/pre_roll')->assertOk()->getContent());

        $this->assertCount(0, $xml->Ad);
    }

    public function test_an_unknown_placement_returns_the_empty_document(): void
    {
        // Notably includes outstream and shorts: they are rendered by their own
        // Vue components from /api/video-ads, not by the player.
        $xml = $this->xml($this->get('/api/vast/outstream')->assertOk()->getContent());

        $this->assertCount(0, $xml->Ad);
    }

    public function test_a_pro_user_receives_the_empty_document(): void
    {
        // The endpoint is a fresh public ad surface, so it is a fresh way to
        // leak ads past the paywall if the gate is ever dropped.
        $this->enable();
        Setting::set('pro_ad_free', true, 'pro', 'boolean');

        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/promo.mp4',
        ]);

        $xml = $this->xml(
            $this->actingAs(User::factory()->create(['is_pro' => true]))
                ->get('/api/vast/pre_roll')
                ->assertOk()
                ->getContent()
        );

        $this->assertCount(0, $xml->Ad);
    }

    public function test_a_cdata_terminator_in_a_creative_name_cannot_break_the_document(): void
    {
        $this->enable();

        // Ad names are admin input. A literal ]]> would close the CDATA section
        // early and produce a document that fails silently in the player.
        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/promo.mp4',
            'name' => 'Evil ]]> name',
            'click_url' => 'https://example.com/?x=]]>',
        ]);

        $body = $this->get('/api/vast/pre_roll')->getContent();
        $xml = $this->xml($body);

        $this->assertSame((string) VideoAd::first()->id, (string) $xml->Ad['id']);
    }

    public function test_the_response_is_never_cached(): void
    {
        // Selection is weighted-random per request; a cached document would pin
        // every viewer to whichever creative was drawn first.
        $this->enable();

        $response = $this->get('/api/vast/pre_roll')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        // Assert on the directive, not the whole header: Symfony normalises and
        // reorders Cache-Control, so an exact-string match is brittle.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_role_targeting_is_honoured(): void
    {
        $this->enable();

        VideoAd::factory()->create([
            'type' => 'mp4',
            'placement' => 'pre_roll',
            'file_path' => 'media/ads/pro-only.mp4',
            'target_roles' => ['default'],
        ]);

        $guest = $this->xml($this->get('/api/vast/pre_roll')->getContent());
        $this->assertCount(0, $guest->Ad, 'A default-targeted creative reached a guest.');

        $member = $this->xml(
            $this->actingAs(User::factory()->create())->get('/api/vast/pre_roll')->getContent()
        );
        $this->assertCount(1, $member->Ad);
    }
}
