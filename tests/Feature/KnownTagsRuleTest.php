<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\User;
use App\Models\Video;
use App\Rules\KnownTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Uploaders pick from the existing tag vocabulary rather than extending it.
 *
 * The gate has to be server-side: TagSyncService::syncVideo() calls
 * Hashtag::firstOrCreate() for whatever it is handed, so a request that reaches
 * it with an unknown tag mints a row regardless of what the upload form allows.
 */
class KnownTagsRuleTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $tags): \Illuminate\Validation\Validator
    {
        return Validator::make(['tags' => $tags], ['tags' => [new KnownTags()]]);
    }

    private function hashtag(string $name, string $slug): Hashtag
    {
        return Hashtag::create(['name' => $name, 'slug' => $slug, 'usage_count' => 0]);
    }

    public function test_existing_tags_pass(): void
    {
        $this->hashtag('Ebony', 'ebony');
        $this->hashtag('Public', 'public');

        $this->assertTrue($this->validate(['Ebony', 'Public'])->passes());
    }

    public function test_an_unknown_tag_is_rejected(): void
    {
        $this->hashtag('Ebony', 'ebony');

        $validator = $this->validate(['Ebony', 'Totally New Tag']);

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('Totally New Tag', $validator->errors()->first('tags'));
    }

    public function test_matching_is_by_slug_not_literal_name(): void
    {
        // TagSyncService looks hashtags up by slug, so validation has to agree:
        // rejecting a casing or spacing variant of a tag that already exists
        // would block a perfectly valid pick.
        $this->hashtag('Tighty Whities', 'tighty-whities');

        $this->assertTrue($this->validate(['tighty whities'])->passes());
        $this->assertTrue($this->validate(['TIGHTY WHITIES'])->passes());
        $this->assertTrue($this->validate(['#Tighty Whities'])->passes());
    }

    public function test_every_unknown_tag_is_named_in_the_message(): void
    {
        $this->hashtag('Ebony', 'ebony');

        $message = $this->validate(['Ebony', 'Nope One', 'Nope Two'])->errors()->first('tags');

        $this->assertStringContainsString('Nope One', $message);
        $this->assertStringContainsString('Nope Two', $message);
    }

    public function test_an_empty_list_is_left_to_the_other_rules(): void
    {
        // "at least 3 tags" is the required/min rule's job; this one only
        // decides whether the tags that *are* present exist.
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_tags_that_slug_to_nothing_are_ignored(): void
    {
        // A punctuation-only entry has no slug to look up. normalizeTagsInput
        // strips these before validation runs; failing on them here would
        // produce an error naming a tag the user cannot see.
        $this->assertTrue($this->validate(['###'])->passes());
    }

    public function test_the_edit_endpoint_rejects_an_invented_tag(): void
    {
        // End-to-end through a real request class, which is what proves the rule
        // is wired up rather than merely written.
        $this->hashtag('Ebony', 'ebony');

        $owner = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put("/videos/{$video->id}", [
                'title' => 'A perfectly fine title',
                'tags' => ['Ebony', 'Invented Tag'],
            ])
            ->assertSessionHasErrors('tags');

        // The point of the rule: nothing new reached the tag table.
        $this->assertDatabaseMissing('hashtags', ['slug' => 'invented-tag']);
    }

    public function test_the_edit_endpoint_accepts_known_tags(): void
    {
        $this->hashtag('Ebony', 'ebony');
        $this->hashtag('Public', 'public');

        $owner = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->put("/videos/{$video->id}", [
                'title' => 'A perfectly fine title',
                'tags' => ['Ebony', 'Public'],
            ])
            ->assertSessionHasNoErrors();
    }
}
