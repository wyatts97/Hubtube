<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Gates a Filament page on one permission.
 *
 * The page declares which one:
 *
 *     use RequiresPermission;
 *     protected static string $requiredPermission = 'update_video';
 *
 * Super-admins and role-less admins pass through the gate bypass in
 * AuthServiceProvider, so this only ever narrows things for a user who has
 * been given roles.
 */
trait RequiresPermission
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->is_admin) {
            return false;
        }

        return $user->can(static::$requiredPermission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
