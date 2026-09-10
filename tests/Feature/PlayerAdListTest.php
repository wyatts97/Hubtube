<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\PlayerAdListBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The player's ad break schedule.
 *
 * Fluid Player takes its adList at construction and cannot be given more breaks
 * afterwards, so mid-roll timing is decided here rather than re-evaluated on
 * every timeupdate the way the old Vue overlay did. That makes these rules worth
 * pinning: a mistake is not a visual glitch, it is either ads that never fire or
 * ads that fire on top of each other.
 */
class PlayerAdListTest extends TestCase
{
    use RefreshDatabase;

    private function builder(): PlayerAdListBuilder
    {
        return app(PlayerAdListBuilder::class);
    }

    private function video(int $duration): Video
    {
        return Video::factory()->create(['duration' => $duration]);
    }

    private function rolls(array $adList): array
    {
        return array_column($adList, 'roll');
    }

    public function test_nothing_is_scheduled_when_no_placement_is_enabled(): void
    {
        $this->assertSame([], $this->builder()->build($this->video(600)));
    }

    public function test_each_enabled_placement_contributes_a_break(): void
    {
        Setting::set('video_ad_pre_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_post_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_on_pause_roll_enabled', true, 'ads', 'boolean');

        $adList = $this->builder()->build($this->video(600));

        $this->assertSame(['preRoll', 'postRoll', 'onPauseRoll'], $this->rolls($adList));
        $this->assertStringContainsString('/api/vast/pre_roll', $adList[0]['vastTag']);
    }

    public function test_mid_rolls_are_spaced_by_the_configured_interval(): void
    {
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_mid_roll_interval', 300, 'ads', 'integer');
        Setting::set('video_ad_mid_roll_max_count', 3, 'ads', 'integer');

        // 20 minutes leaves room for breaks at 5, 10 and 15 minutes.
        $adList = $this->builder()->build($this->video(1200));

        $this->assertSame([300, 600, 900], array_column($adList, 'timer'));
    }

    public function test_mid_rolls_stop_at_the_configured_maximum(): void
    {
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_mid_roll_interval', 60, 'ads', 'integer');
        Setting::set('video_ad_mid_roll_max_count', 2, 'ads', 'integer');

        $adList = $this->builder()->build($this->video(3600));

        $this->assertCount(2, $adList);
    }

    public function test_no_mid_roll_lands_in_the_closing_seconds(): void
    {
        // A break here would interrupt the payoff and then hand straight to the
        // post-roll, which reads to the viewer as one long ad.
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_mid_roll_interval', 100, 'ads', 'integer');
        Setting::set('video_ad_mid_roll_max_count', 10, 'ads', 'integer');

        // 250s with a 30s guard leaves 220s of usable timeline: breaks at 100
        // and 200 fit, the one at 300 does not.
        $duration = 250;
        $adList = $this->builder()->build($this->video($duration));

        $this->assertSame([100, 200], array_column($adList, 'timer'));

        foreach ($adList as $break) {
            $this->assertLessThanOrEqual(
                $duration - PlayerAdListBuilder::MID_ROLL_END_GUARD,
                $break['timer']
            );
        }
    }

    public function test_a_video_shorter_than_one_interval_gets_no_mid_rolls(): void
    {
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_mid_roll_interval', 300, 'ads', 'integer');

        $this->assertSame([], $this->builder()->build($this->video(120)));
    }

    public function test_a_video_of_unknown_duration_gets_no_mid_rolls(): void
    {
        // Without a duration there is no way to know where the end guard falls,
        // and a break scheduled past the end simply never fires.
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');

        $this->assertSame([], $this->builder()->build($this->video(0)));
    }

    public function test_each_mid_roll_gets_a_distinct_tag_url(): void
    {
        // Identical URLs let an intermediate cache collapse every break onto one
        // response, which is how each mid-roll ended up playing the same
        // creative before selection moved to the server.
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_mid_roll_interval', 100, 'ads', 'integer');
        Setting::set('video_ad_mid_roll_max_count', 3, 'ads', 'integer');

        $tags = array_column($this->builder()->build($this->video(1000)), 'vastTag');

        $this->assertCount(3, array_unique($tags));
    }

    public function test_the_category_is_carried_into_every_tag_url(): void
    {
        Setting::set('video_ad_pre_roll_enabled', true, 'ads', 'boolean');

        $adList = $this->builder()->build($this->video(600), 42);

        $this->assertStringContainsString('category_id=42', $adList[0]['vastTag']);
    }

    public function test_an_ad_free_viewer_gets_an_empty_schedule(): void
    {
        Setting::set('video_ad_pre_roll_enabled', true, 'ads', 'boolean');
        Setting::set('video_ad_mid_roll_enabled', true, 'ads', 'boolean');
        Setting::set('pro_ad_free', true, 'pro', 'boolean');

        $this->actingAs(User::factory()->create(['is_pro' => true]));

        // Withholding the schedule, not just the creatives, is what keeps the
        // page payload free of ad URLs entirely.
        $this->assertSame([], $this->builder()->build($this->video(1200)));
    }
}
