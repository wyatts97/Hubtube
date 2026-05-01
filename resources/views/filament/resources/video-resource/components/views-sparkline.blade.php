@php
    /** @var \App\Models\Video $record */
    $record = $this->getRecord();

    $days = 14;
    $buckets = [];
    $end = now()->endOfDay();
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = $end->copy()->subDays($i)->startOfDay();
        $buckets[$day->toDateString()] = 0;
    }

    $hasViewsTable = \Illuminate\Support\Facades\Schema::hasTable('video_views');
    if ($hasViewsTable && $record?->id) {
        $rows = \App\Models\VideoView::where('video_id', $record->id)
            ->where('created_at', '>=', $end->copy()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd')
            ->toArray();
        foreach ($rows as $date => $count) {
            $key = (string) $date;
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] = (int) $count;
            }
        }
    }

    $values = array_values($buckets);
    $max = max($values) ?: 1;
    $total = array_sum($values);

    // Build SVG polyline points (width 280, height 40)
    $w = 280;
    $h = 40;
    $count = count($values);
    $step = $count > 1 ? ($w / ($count - 1)) : 0;
    $points = [];
    foreach ($values as $i => $v) {
        $x = round($i * $step, 2);
        $y = round($h - ($v / $max) * ($h - 4) - 2, 2);
        $points[] = "$x,$y";
    }
    $polyline = implode(' ', $points);
    $areaPoints = "0,$h " . $polyline . ",$w,$h";

    $totalViews = $record?->views_count ?? 0;
    $last7 = array_sum(array_slice($values, -7));
    $prev7 = array_sum(array_slice($values, -14, 7));
    $delta = $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100) : ($last7 > 0 ? 100 : 0);
@endphp

<div class="rounded-lg bg-gray-800 ring-1 ring-white/5 px-4 py-3 flex items-center gap-4">
    <div class="shrink-0">
        <div class="text-[11px] uppercase tracking-wide text-gray-500">Total views</div>
        <div class="text-xl font-bold text-white leading-tight">{{ number_format($totalViews) }}</div>
        <div class="text-[11px] text-gray-400 mt-0.5">
            {{ number_format($total) }} in last {{ $days }}d
            @if($delta !== 0)
                <span class="{{ $delta > 0 ? 'text-green-400' : 'text-red-400' }}">
                    {{ $delta > 0 ? '↑' : '↓' }} {{ abs($delta) }}%
                </span>
            @endif
        </div>
    </div>

    <div class="flex-1 min-w-0">
        @if($hasViewsTable)
            <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full h-10 block">
                <defs>
                    <linearGradient id="sparkGradient-{{ $record?->id }}" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="currentColor" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <polygon fill="url(#sparkGradient-{{ $record?->id }})" points="{{ $areaPoints }}" class="text-primary-500" />
                <polyline fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" points="{{ $polyline }}" class="text-primary-400" />
            </svg>
            <div class="flex justify-between text-[10px] text-gray-500 mt-1">
                <span>{{ $end->copy()->subDays($days - 1)->format('M j') }}</span>
                <span>Today</span>
            </div>
        @else
            <div class="text-xs text-gray-500 italic">No per-day view tracking available.</div>
        @endif
    </div>
</div>
