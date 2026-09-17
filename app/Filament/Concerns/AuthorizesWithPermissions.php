<?php

namespace App\Filament\Concerns;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Gates a Filament resource on role permissions.
 *
 * Permission names follow Shield's convention ("view_any_video"), so the
 * checkboxes on Admin → System → Roles drive exactly these checks.
 *
 * Two bypasses are applied in AuthServiceProvider's gate, not here: a
 * super-admin passes everything, and so does an admin who holds no roles at
 * all — without which this would take the panel away from every existing
 * admin the moment roles were introduced.
 */
trait AuthorizesWithPermissions
{
    /** Shield's key for this resource's model, e.g. 'dmca_request'. */
    public static function permissionKey(): string
    {
        return Permissions::resourceKey(static::getModel());
    }

    protected static function allows(string $action): bool
    {
        $user = Auth::user();

        // Panel access itself is still the is_admin tier.
        if (! $user || ! $user->is_admin) {
            return false;
        }

        return $user->can(Permissions::forResource(static::permissionKey(), $action));
    }

    public static function canAccess(): bool
    {
        return static::allows('view_any');
    }

    public static function canViewAny(): bool
    {
        return static::allows('view_any');
    }

    public static function canView(Model $record): bool
    {
        return static::allows('view');
    }

    public static function canCreate(): bool
    {
        return static::allows('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::allows('delete_any');
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canForceDeleteAny(): bool
    {
        return static::allows('delete_any');
    }

    public static function canRestore(Model $record): bool
    {
        return static::allows('update');
    }

    public static function canRestoreAny(): bool
    {
        return static::allows('update');
    }
}
