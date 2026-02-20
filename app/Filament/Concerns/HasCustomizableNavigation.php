<?php

namespace App\Filament\Concerns;

use App\Services\AdminNavConfig;

trait HasCustomizableNavigation
{
    public static function isHiddenByNavCustomizer(): bool
    {
        try {
            $cfg = app(AdminNavConfig::class);
            if ($cfg->isItemHidden(static::class)) {
                return true;
            }
            $group = static::$navigationGroup ?? null;
            if ($group && $cfg->isGroupHidden($group)) {
                return true;
            }
        } catch (\Throwable) {}

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (static::isHiddenByNavCustomizer()) {
            return false;
        }

        $parent = get_parent_class(static::class);
        if ($parent && method_exists($parent, 'shouldRegisterNavigation')) {
            return $parent::shouldRegisterNavigation();
        }

        return true;
    }

    public static function getNavigationSort(): ?int
    {
        try {
            $sort = app(AdminNavConfig::class)->itemSort(static::class);
            if ($sort !== null) return $sort;
        } catch (\Throwable) {}

        return static::$navigationSort ?? null;
    }
}
