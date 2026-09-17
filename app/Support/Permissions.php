<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The permission vocabulary, and the roles that ship with HubTube.
 *
 * Permission names follow Shield's convention as configured in
 * config/filament-shield.php ("view_any_video", "update_comment"), so the
 * Roles screen and the checks in code always refer to the same strings.
 *
 * Two boolean tiers still sit above all of this: users.is_admin is what lets
 * someone into the panel at all, and users.is_super_admin passes every check
 * (see AuthServiceProvider). Roles divide up what a plain admin can reach —
 * which is how a moderator can exist without being a super-admin.
 */
class Permissions
{
    /** Resource actions permissions are generated for. */
    public const ACTIONS = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any'];

    /** Coarse permissions that are not tied to an admin resource. */
    public const BYPASS_VIDEO_APPROVAL = 'bypass_video_approval';

    public const BYPASS_UPLOAD_LIMITS = 'bypass_upload_limits';

    public const CUSTOM = [self::BYPASS_VIDEO_APPROVAL, self::BYPASS_UPLOAD_LIMITS];

    /** Roles seeded by `php artisan hubtube:sync-roles`. */
    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_UPLOADER = 'uploader';

    public const ROLE_TRUSTED_UPLOADER = 'trusted_uploader';

    /**
     * Resources a moderator can reach, and with which actions.
     *
     * Moderation is about acting on what users submitted: approving or
     * removing videos and comments, and working through the report queues.
     * It deliberately excludes anything that shapes the site itself.
     */
    public const MODERATOR_RESOURCES = [
        'video' => ['view_any', 'view', 'update', 'delete'],
        'comment' => ['view_any', 'view', 'update', 'delete', 'delete_any'],
        'report' => ['view_any', 'view', 'update', 'delete'],
        'dmca_request' => ['view_any', 'view', 'update', 'delete'],
        'contact_message' => ['view_any', 'view', 'update', 'delete'],
        'image' => ['view_any', 'view', 'update', 'delete'],
        'gallery' => ['view_any', 'view', 'update', 'delete'],
    ];

    /**
     * Resources an editor can reach: everything a moderator can, plus the
     * shape of the site's content — taxonomy, channels and static pages.
     */
    public const EDITOR_RESOURCES = [
        'video' => self::ACTIONS,
        'comment' => self::ACTIONS,
        'report' => self::ACTIONS,
        'dmca_request' => self::ACTIONS,
        'contact_message' => self::ACTIONS,
        'image' => self::ACTIONS,
        'gallery' => self::ACTIONS,
        'category' => self::ACTIONS,
        'tag' => self::ACTIONS,
        'channel' => ['view_any', 'view', 'update'],
        'page' => self::ACTIONS,
        'menu_item' => self::ACTIONS,
    ];

    /** Permission name for one action on one resource key. */
    public static function forResource(string $resourceKey, string $action): string
    {
        return $action.'_'.$resourceKey;
    }

    /** 'video' from App\Models\Video, matching Shield's resource keys. */
    public static function resourceKey(string $model): string
    {
        return Str::snake(class_basename($model));
    }

    /** @return list<string> Every permission name a role should hold. */
    public static function expand(array $resources, array $custom = []): array
    {
        $permissions = [];

        foreach ($resources as $resourceKey => $actions) {
            foreach ($actions as $action) {
                $permissions[] = static::forResource($resourceKey, $action);
            }
        }

        return array_values(array_unique([...$permissions, ...$custom]));
    }

    /**
     * The seeded roles and their permissions.
     *
     * Uploader roles carry no panel permissions: they exist for the front end,
     * where trusted uploaders skip the moderation queue.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_MODERATOR => static::expand(self::MODERATOR_RESOURCES),
            self::ROLE_EDITOR => static::expand(self::EDITOR_RESOURCES),
            self::ROLE_UPLOADER => [],
            self::ROLE_TRUSTED_UPLOADER => [self::BYPASS_VIDEO_APPROVAL, self::BYPASS_UPLOAD_LIMITS],
        ];
    }

    /** Every permission name HubTube manages, for the seeder and the gate. */
    public static function all(): array
    {
        return array_values(array_unique([
            ...static::expand(self::EDITOR_RESOURCES, self::CUSTOM),
            ...static::expand(self::MODERATOR_RESOURCES),
        ]));
    }

    /**
     * Whether an ability name is one of ours.
     *
     * The gate bypass for super-admins and for legacy flag-only admins is
     * limited to these, so it can never quietly satisfy an unrelated gate
     * such as 'withdraw'.
     */
    public static function isManaged(string $ability): bool
    {
        if (in_array($ability, self::CUSTOM, true)) {
            return true;
        }

        foreach (self::ACTIONS as $action) {
            if (str_starts_with($ability, $action.'_')) {
                return true;
            }
        }

        return false;
    }
}
