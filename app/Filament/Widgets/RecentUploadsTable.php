<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use SecretNinjas\FilamentMasonry\Concerns\HasMasonryLayout;
use SecretNinjas\FilamentMasonry\Enums\WidgetSize;

/**
 * Custom recent-uploads widget — a visual card list with thumbnails,
 * status pills and relative timestamps.
 */
class RecentUploadsTable extends Widget
{
    use HasMasonryLayout;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.recent-uploads-table';

    protected static WidgetSize $size = WidgetSize::Medium;
    protected static int $order = 30;

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    public function getVideos(): Collection
    {
        return Video::query()
            ->with('user:id,username,avatar')
            ->latest()
            ->limit(8)
            ->get();
    }

    public function render(): View
    {
        return view($this->view, [
            'videos' => $this->getVideos(),
        ]);
    }
}
