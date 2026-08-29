<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Restrict a Filament page or resource to super-admins.
 *
 * Applied to the parts of the panel that can escalate to full server or
 * financial control — the settings pages that assemble ffmpeg commands, payment
 * credentials, user management (which can grant is_admin), and the importer.
 *
 * Filament hides navigation entries whose canAccess() is false, and returning
 * false here also produces a 403 on direct URL access.
 */
trait RequiresSuperAdmin
{
    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
