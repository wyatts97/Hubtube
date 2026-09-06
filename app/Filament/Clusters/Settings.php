<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * Groups the site's configuration pages behind one sidebar entry.
 *
 * There were thirteen settings pages spread across four unrelated navigation
 * groups (System, Appearance, Monetization, Users & Email), so nothing in the
 * panel represented "configuration" and the sidebar read as a pile.
 *
 * A cluster is used rather than merging them into one tabbed page: each page
 * stays its own Livewire component, which keeps LanguageSettings' and
 * PointsSettings' HasTable implementations working (a component can only
 * implement it once), avoids rendering ~400 form fields into a single DOM,
 * keeps the eight pages that already use top-level Tabs from nesting tabs in
 * tabs, and leaves the duplicated ffmpeg_* keys writing to their own groups.
 *
 * Per-page access control is preserved: Cluster::canAccessClusteredComponents()
 * calls each component's canAccess(), so the RequiresSuperAdmin trait on Site,
 * Payment, Storage and Integration settings still gates those pages alone.
 */
class Settings extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'phosphor-gear';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;
}
