<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RecentSignupsTable;
use App\Filament\Widgets\RecentUploadsTable;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TrendingVideosTable;
use App\Models\Setting;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';

    /**
     * All available widgets with metadata for the layout manager.
     */
    public static function allWidgetDefinitions(): array
    {
        return [
            ['key' => 'stats',           'label' => 'Stats Overview',    'class' => StatsOverview::class,      'span' => 'full'],
            ['key' => 'trending',        'label' => 'Trending Videos',   'class' => TrendingVideosTable::class, 'span' => '1'],
            ['key' => 'recent_uploads',  'label' => 'Recent Uploads',   'class' => RecentUploadsTable::class,  'span' => '1'],
            ['key' => 'recent_signups',  'label' => 'Recent Signups',   'class' => RecentSignupsTable::class,  'span' => 'full'],
        ];
    }

    /**
     * Get the saved layout for the current admin user.
     * Returns an array of widget keys in order, with visibility flags.
     */
    public function getSavedLayout(): array
    {
        $userId = auth()->id();
        $saved = Setting::get("dashboard_layout_user_{$userId}", null);

        if ($saved) {
            $layout = is_string($saved) ? json_decode($saved, true) : $saved;
            if (is_array($layout)) {
                return $layout;
            }
        }

        // Default: all widgets visible in default order
        return collect(static::allWidgetDefinitions())
            ->map(fn ($w) => ['key' => $w['key'], 'visible' => true])
            ->toArray();
    }

    /**
     * Save the layout for the current admin user (called via Livewire).
     */
    public function saveLayout(array $layout): void
    {
        $userId = auth()->id();
        Setting::set("dashboard_layout_user_{$userId}", json_encode($layout), 'dashboard', 'string');
    }

    /**
     * Reset layout to default (called via Livewire).
     */
    public function resetLayout(): void
    {
        $userId = auth()->id();
        Setting::set("dashboard_layout_user_{$userId}", '', 'dashboard', 'string');
    }

    /**
     * Toggle a widget's visibility (called via Livewire).
     */
    public function toggleWidget(string $key): void
    {
        $layout = $this->getSavedLayout();
        foreach ($layout as &$item) {
            if ($item['key'] === $key) {
                $item['visible'] = !$item['visible'];
                break;
            }
        }
        $this->saveLayout($layout);
    }

    /**
     * Reorder widgets (called via Livewire from SortableJS).
     */
    public function reorderWidgets(array $orderedKeys): void
    {
        $layout = $this->getSavedLayout();
        $indexed = collect($layout)->keyBy('key');

        $newLayout = [];
        foreach ($orderedKeys as $key) {
            if ($indexed->has($key)) {
                $newLayout[] = $indexed[$key];
            }
        }

        // Append any widgets not in the ordered list (new widgets added after layout was saved)
        foreach ($layout as $item) {
            if (!in_array($item['key'], $orderedKeys)) {
                $newLayout[] = $item;
            }
        }

        $this->saveLayout($newLayout);
    }

    /**
     * Get ordered, visible widgets for rendering.
     */
    public function getOrderedWidgets(): array
    {
        $layout = $this->getSavedLayout();
        $definitions = collect(static::allWidgetDefinitions())->keyBy('key');

        $result = [];
        foreach ($layout as $item) {
            if (!$item['visible']) continue;
            if (!$definitions->has($item['key'])) continue;
            $result[] = $definitions[$item['key']];
        }

        return $result;
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return collect($this->getOrderedWidgets())
            ->pluck('class')
            ->toArray();
    }
}
