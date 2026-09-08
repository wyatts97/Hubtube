<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SponsoredCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the two ad bugs that were invisible from the outside.
 *
 * The footer slot and the interstitial were shared with every viewer and only
 * *rendered* conditionally, so a Pro user's page still carried the ad code —
 * asserting on rendering would have passed the whole time. These assert on the
 * Inertia payload instead.
 *
 * Role targeting was worse: controllers read `auth()->user()?->role`, which is
 * always null because User has no such column, so every viewer resolved to
 * 'guest' and role-targeted cards could never be delivered.
 */
class AdGatingTest extends TestCase
{
    use RefreshDatabase;

    private function proUser(): User
    {
        Setting::set('pro_ad_free', true, 'pro', 'boolean');

        return User::factory()->create(['is_pro' => true]);
    }

    private function enableFooterAndInterstitial(): void
    {
        Setting::set('footer_ad_enabled', true, 'ads', 'boolean');
        Setting::set('footer_ad_code', '<script>FOOTER_AD_CODE</script>', 'ads', 'string');
        Setting::set('footer_ad_mobile_code', '<script>FOOTER_MOBILE_AD</script>', 'ads', 'string');
        Setting::set('custom_interstitial_enabled', true, 'ads', 'boolean');
        Setting::set('custom_interstitial_code', '<script>INTERSTITIAL_AD_CODE</script>', 'ads', 'string');
    }

    public function test_pro_users_do_not_receive_footer_or_interstitial_ad_code(): void
    {
        $this->enableFooterAndInterstitial();

        $response = $this->actingAs($this->proUser())->get('/');

        $response->assertOk();
        // Absent from the payload, not merely unrendered.
        $response->assertDontSee('FOOTER_AD_CODE', false);
        $response->assertDontSee('FOOTER_MOBILE_AD', false);
        $response->assertDontSee('INTERSTITIAL_AD_CODE', false);
    }

    public function test_non_pro_users_still_receive_footer_and_interstitial_ad_code(): void
    {
        $this->enableFooterAndInterstitial();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('FOOTER_AD_CODE', false);
        $response->assertSee('INTERSTITIAL_AD_CODE', false);
    }

    public function test_pro_targeted_sponsored_card_reaches_a_pro_user(): void
    {
        Setting::set('pro_ad_free', false, 'pro', 'boolean');

        SponsoredCard::factory()->create([
            'title' => 'ProOnlyOffer',
            'target_roles' => ['pro'],
        ]);

        $user = User::factory()->create(['is_pro' => true]);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('ProOnlyOffer', false);
    }

    public function test_pro_targeted_sponsored_card_is_withheld_from_a_guest(): void
    {
        SponsoredCard::factory()->create([
            'title' => 'ProOnlyOffer',
            'target_roles' => ['pro'],
        ]);

        $this->get('/')->assertOk()->assertDontSee('ProOnlyOffer', false);
    }

    public function test_untargeted_sponsored_card_reaches_everyone(): void
    {
        SponsoredCard::factory()->create([
            'title' => 'OpenToAll',
            'target_roles' => [],
        ]);

        $this->get('/')->assertOk()->assertSee('OpenToAll', false);
    }
}
