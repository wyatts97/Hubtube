<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

// Only register this widget if the FullCalendar package is installed.
// We alias the parent class so the `extends` below always resolves.
if (class_exists(\Saade\FilamentFullCalendar\Widgets\FullCalendarWidget::class)) {
    class_alias(\Saade\FilamentFullCalendar\Widgets\FullCalendarWidget::class, VideoScheduleCalendarWidgetBase::class);
} else {
    // Stub: Widget subclass with a protected hidden property so Filament ignores it.
    abstract class VideoScheduleCalendarWidgetBase extends Widget
    {
        protected static bool $isDiscovered = false;
    }
}

class VideoScheduleCalendarWidget extends VideoScheduleCalendarWidgetBase
{
    public Model|string|null $model = Video::class;

    /**
     * Fetch scheduled and published videos as calendar events.
     *
     * @param  array<string, mixed>  $fetchInfo
     * @return array<int, array<string, mixed>>
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $start = $fetchInfo['start'] ?? now()->subMonth();
        $end   = $fetchInfo['end']   ?? now()->addMonth();

        return Video::query()
            ->where(function ($q) {
                $q->whereNotNull('scheduled_at')
                  ->orWhereNotNull('published_at');
            })
            ->whereBetween(
                \DB::raw('COALESCE(scheduled_at, published_at)'),
                [$start, $end]
            )
            ->limit(500)
            ->get()
            ->map(function (Video $video) {
                $when    = $video->scheduled_at ?? $video->published_at ?? $video->created_at;
                $isFuture = $when && $when->isFuture();

                return [
                    'id'    => (string) $video->id,
                    'title' => \Illuminate\Support\Str::limit($video->title, 40),
                    'start' => $when?->toIso8601String(),
                    'end'   => $when?->toIso8601String(),
                    'url'   => route('filament.admin.resources.videos.edit', $video),
                    'backgroundColor' => $isFuture ? '#6366f1' : '#10b981',
                    'borderColor'     => $isFuture ? '#4f46e5' : '#059669',
                    'allDay' => false,
                ];
            })
            ->toArray();
    }

    public function config(): array
    {
        return [
            'initialView'  => 'dayGridMonth',
            'headerToolbar' => [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => 'dayGridMonth,timeGridWeek,listWeek',
            ],
            'editable'     => false,
            'selectable'   => false,
            'navLinks'     => true,
            'dayMaxEvents' => 3,
        ];
    }
}
