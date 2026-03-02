<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Str;

class ChannelService
{
    /**
     * Create a channel for a user with a unique slug.
     */
    public static function createForUser(User $user, ?string $name = null): Channel
    {
        $channelName = $name ?? $user->username;
        $baseSlug = Str::slug($channelName) ?: 'channel';
        $slug = $baseSlug . '-' . $user->id;
        
        $suffix = 2;
        while (Channel::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $user->id . '-' . $suffix;
            $suffix++;
        }

        return Channel::create([
            'user_id' => $user->id,
            'name' => $channelName,
            'slug' => $slug,
        ]);
    }
}
