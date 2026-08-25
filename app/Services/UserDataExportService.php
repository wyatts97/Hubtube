<?php

namespace App\Services;

use App\Models\User;

/**
 * GDPR-style self-service data export.
 *
 * Builds a JSON snapshot of everything a user owns in the platform so they
 * can download their own data on request. Only includes metadata (no
 * binary files) — videos/images link back to the live URLs.
 */
class UserDataExportService
{
    public function build(User $user): array
    {
        $user->loadMissing([
            'channel',
            'videos',
            'comments',
            'likes',
            'playlists.videos:id,title,slug',
            'channelSubscriptions.channel:id,username',
            'watchHistory.video:id,title,slug',
            'walletTransactions',
            'appNotifications',
            'socialAccounts',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'bio' => $user->bio,
                'gender' => $user->gender,
                'country' => $user->country,
                'website' => $user->website,
                'is_verified' => (bool) $user->is_verified,
                'is_pro' => (bool) $user->is_pro,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'age_verified_at' => $user->age_verified_at?->toIso8601String(),
                'settings' => $user->settings ?? [],
            ],
            'channel' => $user->channel ? [
                'name' => $user->channel->name,
                'slug' => $user->channel->slug,
                'description' => $user->channel->description ?? null,
                'subscriber_count' => $user->channel->subscriber_count ?? 0,
            ] : null,
            'videos' => $user->videos->map(fn ($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'slug' => $v->slug,
                'description' => $v->description,
                'privacy' => $v->privacy,
                'views_count' => $v->views_count ?? 0,
                'created_at' => $v->created_at?->toIso8601String(),
            ])->values(),
            'comments' => $user->comments->map(fn ($c) => [
                'id' => $c->id,
                'video_id' => $c->video_id,
                'content' => $c->content,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values(),
            'likes' => $user->likes->map(fn ($l) => [
                'video_id' => $l->video_id,
                'type' => $l->type,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->values(),
            'playlists' => $user->playlists->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'privacy' => $p->privacy,
                'video_count' => $p->video_count,
                'videos' => $p->videos->map(fn ($v) => ['id' => $v->id, 'title' => $v->title, 'slug' => $v->slug])->values(),
            ])->values(),
            'subscriptions' => $user->channelSubscriptions->map(fn ($s) => [
                'channel_username' => $s->channel->username ?? null,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values(),
            'watch_history' => $user->watchHistory->map(fn ($h) => [
                'video_title' => $h->video->title ?? null,
                'video_slug' => $h->video->slug ?? null,
                'watched_seconds' => $h->watched_seconds,
                'completed' => (bool) $h->completed,
                'updated_at' => $h->updated_at?->toIso8601String(),
            ])->values(),
            'wallet_transactions' => $user->walletTransactions->map(fn ($t) => [
                'type' => $t->type,
                'amount' => (string) $t->amount,
                'status' => $t->status,
                'description' => $t->description,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
            'notifications' => $user->appNotifications->map(fn ($n) => [
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'social_accounts' => $user->socialAccounts->map(fn ($s) => [
                'provider' => $s->provider,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public function filename(User $user): string
    {
        return 'hubtube-data-export-' . $user->id . '-' . now()->format('Y-m-d_His') . '.json';
    }
}
