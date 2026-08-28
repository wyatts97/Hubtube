<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public projection of a User when it is being rendered as a channel.
 *
 * Named ChannelProfileResource rather than ChannelResource to stay
 * unambiguous alongside App\Filament\Resources\ChannelResource.
 *
 * This is an explicit allowlist and must stay one. The channel page is
 * public, so anything added here is visible to anonymous visitors. Never
 * call parent::toArray() here — that serialises the whole User model and
 * would leak email, settings, wallet_balance and points_balance, which is
 * exactly the regression tests/Feature/ChannelProfileLeakTest.php guards.
 */
class ChannelProfileResource extends JsonResource
{
    /**
     * The Eloquent model backing this resource is a User, not a Channel.
     * Routes bind {user:username} and subscriptions.channel_id is a foreign
     * key to users.id — the Channel model is only a side-table of profile
     * fields hanging off it.
     */
    public function toArray(Request $request): array
    {
        $channel = $this->channel;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $channel?->name ?: $this->username,
            'avatar_url' => $this->avatar_url,
            'banner_url' => $channel?->banner_image,
            // users.bio is the legacy home for this text; Phase 3 migrates it
            // into channels.description and drops the fallback.
            'description' => $channel?->description ?: $this->bio,
            'is_verified' => (bool) $this->is_verified,
            'is_pro' => (bool) $this->is_pro,
            'custom_url' => $channel?->custom_url,
            'social_links' => [],
            'created_at' => $this->created_at?->toIso8601String(),
            'stats' => [
                'subscribers' => (int) $this->subscriber_count,
                // Model::shouldBeStrict() is enabled outside production, so
                // reading an unset attribute throws rather than returning
                // null. Check the raw attribute bag, and fall back to a live
                // count for callers that did not preset it.
                'videos' => (int) (
                    $this->resource->getAttributes()['public_video_count']
                        ?? $this->resource->publicVideoCount()
                ),
                'views' => (int) ($channel?->total_views ?? 0),
            ],
            'is_owner' => $request->user()?->id === $this->id,
        ];
    }
}
