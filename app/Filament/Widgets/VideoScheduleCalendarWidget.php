<?php

namespace App\Filament\Widgets;

use App\Models\Video;
use Illuminate\Support\Str;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class VideoScheduleCalendarWidget extends FullCalendarWidget
{
    protected static bool $isDiscovered = false;

    /**
     * Fetch scheduled and published videos as FullCalendar events.
     *
     * @param  array{start: string, end: string, timezone: string}  $info
     * @return array<int, array<string, mixed>>
     */
    public function fetchEvents(array $info): array
    {
        $start = $info['start'] ?? now()->subMonth()->toIso8601String();
        $end   = $info['end']   ?? now()->addMonth()->toIso8601String();

        return Video::query()
            ->select(['id', 'title', 'scheduled_at', 'published_at', 'created_at', 'status'])
            ->where(function ($q) {
                $q->whereNotNull('scheduled_at')
                  ->orWhereNotNull('published_at');
            })
            ->whereRaw('COALESCE(scheduled_at, published_at) BETWEEN ? AND ?', [$start, $end])
            ->limit(500)
            ->get()
            ->map(function (Video $video) {
                $when    = $video->scheduled_at ?? $video->published_at ?? $video->created_at;
                $isFuture = $when && $when->isFuture();

                return [
                    'id'              => (string) $video->id,
                    'title'           => Str::limit((string) $video->title, 40),
                    'start'           => $when?->toIso8601String(),
                    'end'             => $when?->toIso8601String(),
                    'url'             => route('filament.admin.resources.videos.edit', $video),
                    'backgroundColor' => $isFuture ? '#6366f1' : '#10b981',
                    'borderColor'     => $isFuture ? '#4f46e5' : '#059669',
                    'allDay'          => false,
                ];
            })
            ->toArray();
    }

    public function config(): array
    {
        return [
            'initialView'   => 'dayGridMonth',
            'headerToolbar' => [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => 'dayGridMonth,timeGridWeek,listWeek',
            ],
            'editable'     => false,
            'selectable'   => false,
            'navLinks'     => true,
            'dayMaxEvents' => 3,
            'height'       => 'auto',
        ];
    }

    /**
     * Disable built-in create/edit/delete actions — we link events to video edit pages.
     */
    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [];
    }
}
