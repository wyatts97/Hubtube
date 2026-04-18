<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Scheduled and recently published videos on a calendar view. Click any event to edit the video.
        </p>

        @if(!class_exists(\Saade\FilamentFullCalendar\Widgets\FullCalendarWidget::class))
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-6 text-sm text-amber-700 dark:text-amber-400">
                FullCalendar package not installed. Run:
                <code class="block mt-2 font-mono text-xs bg-black/10 dark:bg-black/30 p-2 rounded">
                    composer require saade/filament-fullcalendar:"^3.2"
                </code>
            </div>
        @endif
    </div>
</x-filament-panels::page>
