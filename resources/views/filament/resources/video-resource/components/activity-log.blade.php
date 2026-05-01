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

    // Upload
    $events->push([
        'icon'  => 'heroicon-m-arrow-up-tray',
        'color' => 'info',
        'title' => 'Uploaded',
        'meta'  => optional($record->user)->username ? "by {$record->user->username}" : null,
        'at'    => $record->created_at,
    ]);

    // Published
    if ($record->published_at) {
        $events->push([
            'icon'  => 'heroicon-m-globe-alt',
            'color' => 'success',
            'title' => 'Published',
            'meta'  => null,
            'at'    => $record->published_at,
        ]);
    }

    // Approval (inferred)
    if ($record->is_approved) {
        $events->push([
            'icon'  => 'heroicon-m-check-circle',
            'color' => 'success',
            'title' => 'Approved',
            'meta'  => null,
            'at'    => $record->updated_at,
        ]);
    } elseif ($record->status === 'processed') {
        $events->push([
            'icon'  => 'heroicon-m-clock',
            'color' => 'warning',
            'title' => 'Awaiting moderation',
            'meta'  => null,
            'at'    => $record->updated_at,
        ]);
    }

    // Rejection / failure
    if ($record->failure_reason) {
        $events->push([
            'icon'  => 'heroicon-m-x-circle',
            'color' => 'danger',
            'title' => $record->status === 'failed' ? 'Processing failed' : 'Rejected',
            'meta'  => \Illuminate\Support\Str::limit($record->failure_reason, 120),
            'at'    => $record->updated_at,
        ]);
    }

    // Scheduled
    if ($record->scheduled_at) {
        $events->push([
            'icon'  => 'heroicon-m-calendar',
            'color' => 'info',
            'title' => 'Scheduled for ' . $record->scheduled_at->format('M j, Y g:i A'),
            'meta'  => null,
            'at'    => $record->scheduled_at,
        ]);
    }

    // Last updated
    $events->push([
        'icon'  => 'heroicon-m-pencil-square',
        'color' => 'gray',
        'title' => 'Last updated',
        'meta'  => null,
        'at'    => $record->updated_at,
    ]);

    $events = $events->sortByDesc('at')->values();

    $colorMap = [
        'success' => 'text-green-400 bg-green-500/10 ring-green-500/20',
        'danger'  => 'text-red-400 bg-red-500/10 ring-red-500/20',
        'warning' => 'text-amber-400 bg-amber-500/10 ring-amber-500/20',
        'info'    => 'text-sky-400 bg-sky-500/10 ring-sky-500/20',
        'gray'    => 'text-gray-400 bg-gray-500/10 ring-gray-500/20',
    ];
@endphp

<div class="space-y-4">
    {{-- Report summary --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-lg bg-gray-800 ring-1 ring-white/5 px-3 py-2">
            <div class="text-[11px] uppercase tracking-wide text-gray-500">Reports</div>
            <div class="mt-0.5 text-lg font-semibold {{ $reportCount > 0 ? 'text-red-400' : 'text-gray-300' }}">
                {{ number_format($reportCount) }}
            </div>
        </div>
        <div class="rounded-lg bg-gray-800 ring-1 ring-white/5 px-3 py-2">
            <div class="text-[11px] uppercase tracking-wide text-gray-500">Last rejection</div>
            <div class="mt-0.5 text-xs text-gray-300 truncate" title="{{ $record->failure_reason }}">
                {{ $record->failure_reason ? \Illuminate\Support\Str::limit($record->failure_reason, 40) : '—' }}
            </div>
        </div>
    </div>

    {{-- Timeline --}}
    <div>
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">History</h4>
        <ol class="relative border-l border-gray-700 ml-2">
            @foreach($events as $event)
                <li class="mb-3 ml-4">
                    <span class="absolute -left-2 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-gray-900 {{ $colorMap[$event['color']] ?? $colorMap['gray'] }}">
                        <x-dynamic-component :component="$event['icon']" class="h-2.5 w-2.5" />
                    </span>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm text-gray-200">{{ $event['title'] }}</div>
                            @if(!empty($event['meta']))
                                <div class="text-xs text-gray-500">{{ $event['meta'] }}</div>
                            @endif
                        </div>
                        <time class="text-[11px] text-gray-500 whitespace-nowrap shrink-0" title="{{ optional($event['at'])->format('M j, Y g:i A') }}">
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
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Recent Reports</h4>
        <div class="space-y-2">
            @foreach($recentReports as $report)
            <div class="rounded-lg bg-gray-800 ring-1 ring-white/5 px-3 py-2 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase
                            @if($report->status === 'pending') bg-amber-500/10 text-amber-400
                            @elseif($report->status === 'resolved') bg-green-500/10 text-green-400
                            @elseif($report->status === 'dismissed') bg-gray-500/10 text-gray-400
                            @else bg-sky-500/10 text-sky-400 @endif">
                            {{ $report->status }}
                        </span>
                        <span class="text-gray-300 font-medium">{{ ucfirst($report->reason) }}</span>
                    </div>
                    @if($report->description)
                        <p class="mt-1 text-xs text-gray-400 truncate">{{ $report->description }}</p>
                    @endif
                </div>
                <time class="text-[11px] text-gray-500 shrink-0">{{ $report->created_at->diffForHumans() }}</time>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
