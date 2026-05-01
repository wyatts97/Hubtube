@php
    /** @var \App\Models\Video $record */
    $record = $this->getRecord();

    $reportCount = 0;
    $recentReports = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('reports')) {
        $reportCount = \App\Models\Report::where('reportable_type', \App\Models\Video::class)
            ->where('reportable_id', $record->id)
            ->count();
        $recentReports = \App\Models\Report::where('reportable_type', \App\Models\Video::class)
            ->where('reportable_id', $record->id)
            ->latest()
            ->limit(10)
            ->get();
    }

    $events = collect();

    $events->push([
        'icon'  => 'heroicon-m-arrow-up-tray',
        'tone'  => 'info',
        'title' => 'Uploaded',
        'meta'  => optional($record->user)->username ? "by {$record->user->username}" : null,
        'at'    => $record->created_at,
    ]);

    if ($record->published_at) {
        $events->push([
            'icon'  => 'heroicon-m-globe-alt',
            'tone'  => 'success',
            'title' => 'Published',
            'meta'  => null,
            'at'    => $record->published_at,
        ]);
    }

    if ($record->is_approved) {
        $events->push([
            'icon'  => 'heroicon-m-check-circle',
            'tone'  => 'success',
            'title' => 'Approved',
            'meta'  => null,
            'at'    => $record->updated_at,
        ]);
    } elseif ($record->status === 'processed') {
        $events->push([
            'icon'  => 'heroicon-m-clock',
            'tone'  => 'warning',
            'title' => 'Awaiting moderation',
            'meta'  => null,
            'at'    => $record->updated_at,
        ]);
    }

    if ($record->failure_reason) {
        $events->push([
            'icon'  => 'heroicon-m-x-circle',
            'tone'  => 'danger',
            'title' => $record->status === 'failed' ? 'Processing failed' : 'Rejected',
            'meta'  => \Illuminate\Support\Str::limit($record->failure_reason, 120),
            'at'    => $record->updated_at,
        ]);
    }

    if ($record->scheduled_at) {
        $events->push([
            'icon'  => 'heroicon-m-calendar',
            'tone'  => 'info',
            'title' => 'Scheduled for ' . $record->scheduled_at->format('M j, Y g:i A'),
            'meta'  => null,
            'at'    => $record->scheduled_at,
        ]);
    }

    $events->push([
        'icon'  => 'heroicon-m-pencil-square',
        'tone'  => 'gray',
        'title' => 'Last updated',
        'meta'  => null,
        'at'    => $record->updated_at,
    ]);

    $events = $events->sortByDesc('at')->values();

    // Filament color tokens
    $toneClasses = [
        'success' => 'text-success-600 dark:text-success-400 bg-success-50 ring-success-600/20 dark:bg-success-400/10 dark:ring-success-400/30',
        'danger'  => 'text-danger-600 dark:text-danger-400 bg-danger-50 ring-danger-600/20 dark:bg-danger-400/10 dark:ring-danger-400/30',
        'warning' => 'text-warning-600 dark:text-warning-400 bg-warning-50 ring-warning-600/20 dark:bg-warning-400/10 dark:ring-warning-400/30',
        'info'    => 'text-info-600 dark:text-info-400 bg-info-50 ring-info-600/20 dark:bg-info-400/10 dark:ring-info-400/30',
        'gray'    => 'text-gray-500 dark:text-gray-400 bg-gray-100 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10',
    ];

    // Compact stats (replaces the 14-day sparkline)
    $lastView = null;
    if (\Illuminate\Support\Facades\Schema::hasTable('video_views')) {
        $lastView = \App\Models\VideoView::where('video_id', $record->id)->latest('created_at')->value('created_at');
    }
@endphp

<div class="space-y-4">
    {{-- Top stats strip (replaces the sparkline) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 px-3 py-2">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Total views</div>
            <div class="mt-0.5 text-base font-semibold text-gray-950 dark:text-white leading-tight">{{ number_format($record->views_count ?? 0) }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 px-3 py-2">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Likes</div>
            <div class="mt-0.5 text-base font-semibold text-gray-950 dark:text-white leading-tight">{{ number_format($record->likes_count ?? 0) }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 px-3 py-2">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Reports</div>
            <div class="mt-0.5 text-base font-semibold leading-tight {{ $reportCount > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">{{ number_format($reportCount) }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 px-3 py-2">
            <div class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Last rejection</div>
            <div class="mt-0.5 text-xs text-gray-700 dark:text-gray-300 truncate" title="{{ $record->failure_reason }}">
                {{ $record->failure_reason ? \Illuminate\Support\Str::limit($record->failure_reason, 28) : '—' }}
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div>
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">History</h4>
        <ol class="relative border-l border-gray-200 dark:border-white/10 ml-2">
            @foreach($events as $event)
                <li class="mb-3 ml-4">
                    <span class="absolute -left-2 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-white dark:ring-gray-900 {{ $toneClasses[$event['tone']] ?? $toneClasses['gray'] }}">
                        <x-dynamic-component :component="$event['icon']" class="h-2.5 w-2.5" />
                    </span>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $event['title'] }}</div>
                            @if(!empty($event['meta']))
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $event['meta'] }}</div>
                            @endif
                        </div>
                        <time class="text-[11px] text-gray-500 dark:text-gray-400 whitespace-nowrap shrink-0" title="{{ optional($event['at'])->format('M j, Y g:i A') }}">
                            {{ optional($event['at'])->diffForHumans() ?? '—' }}
                        </time>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Recent reports --}}
    @if($recentReports->count() > 0)
    <div>
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Recent Reports</h4>
        <div class="space-y-2">
            @foreach($recentReports as $report)
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 px-3 py-2 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs">
                        @php
                            $statusTone = match($report->status) {
                                'pending'    => 'warning',
                                'resolved'   => 'success',
                                'dismissed'  => 'gray',
                                default      => 'info',
                            };
                        @endphp
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase ring-1 {{ $toneClasses[$statusTone] }}">
                            {{ $report->status }}
                        </span>
                        <span class="text-gray-900 dark:text-gray-100 font-medium">{{ ucfirst($report->reason) }}</span>
                    </div>
                    @if($report->description)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">{{ $report->description }}</p>
                    @endif
                </div>
                <time class="text-[11px] text-gray-500 dark:text-gray-400 shrink-0">{{ $report->created_at->diffForHumans() }}</time>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
