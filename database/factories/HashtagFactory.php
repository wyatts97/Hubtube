<?php

namespace Database\Factories;

use App\Models\Hashtag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HashtagFactory extends Factory
{
    protected $model = Hashtag::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            // Tags are matched by slug throughout (see App\Rules\KnownTags), so
            // the two must agree or a factory-made tag looks unknown.
            'slug' => Str::slug($name),
            'usage_count' => 0,
        ];
    }
}
