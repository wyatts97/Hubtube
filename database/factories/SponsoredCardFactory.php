<?php

namespace Database\Factories;

use App\Models\SponsoredCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsoredCard>
 */
class SponsoredCardFactory extends Factory
{
    protected $model = SponsoredCard::class;

    public function definition(): array
    {
        return [
            'type' => SponsoredCard::TYPE_IMAGE,
            'title' => $this->faker->sentence(3),
            'thumbnail_url' => 'sponsored/example.jpg',
            'click_url' => 'https://example.com/offer',
            // Empty targeting means "match everything" on both models, which is
            // the default an operator gets from the admin form.
            'target_pages' => ['home'],
            'category_ids' => [],
            'target_roles' => [],
            'weight' => 1,
            'is_active' => true,
        ];
    }

    public function html(): static
    {
        return $this->state([
            'type' => SponsoredCard::TYPE_HTML,
            'thumbnail_url' => null,
            'click_url' => null,
            'html_code' => '<div class="ad">ad</div>',
        ]);
    }
}
