<x-filament-panels::page>
    @php
        $summary = $this->getSummaryStats();

        $overallCtr = $summary['total_impressions'] > 0
            ? round(($summary['total_clicks'] / $summary['total_impressions']) * 100, 2)
            : 0;

        // Build 30-day date spine so gaps show as 0
        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }

        $uploadsMap  = collect($uploadsPerDay)->pluck('count', 'date');
        $signupsMap  = collect($signupsPerDay)->pluck('count', 'date');
        $revenueMap  = collect($revenuePerDay)->pluck('total', 'date');

        $uploadSeries  = $dates->map(fn($d) => (int)  ($uploadsMap[$d]  ?? 0))->values()->toArray();
        $signupSeries  = $dates->map(fn($d) => (int)  ($signupsMap[$d]  ?? 0))->values()->toArray();
        $revenueSeries = $dates->map(fn($d) => (float)($revenueMap[$d]  ?? 0))->values()->toArray();
        $dateLabels    = $dates->map(fn($d) => \Carbon\Carbon::parse($d)->format('M j'))->toArray();

        $catLabels = collect($viewsByCategory)->pluck('category')->toArray();
        $catValues = collect($viewsByCategory)->pluck('views')->toArray();
    @endphp

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['label' => 'Total Videos',     'value' => number_format($summary['total_videos']),     'sub' => '+'.number_format($summary['videos_this_week']).' this week', 'icon' => 'heroicon-o-film'],
            ['label' => 'Total Users',      'value' => number_format($summary['total_users']),      'sub' => '+'.number_format($summary['users_this_week']).' this week',  'icon' => 'heroicon-o-users'],
            ['label' => 'Total Views',      'value' => number_format($summary['total_views']),      'sub' => 'All time',                                                   'icon' => 'heroicon-o-eye'],
            ['label' => 'Ad Impressions',   'value' => number_format($summary['total_impressions']),'sub' => number_format($summary['total_clicks']).' clicks · '.$overallCtr.'% CTR', 'icon' => 'heroicon-o-cursor-arrow-rays'],
        ] as $card)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-primary-50 dark:bg-primary-900/30 p-2">
                    <x-filament::icon :icon="$card['icon']" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $card['sub'] }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Line Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Video Uploads — Last 30 Days</h3>
            <canvas id="uploadsChart" height="120"></canvas>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">New Registrations — Last 30 Days</h3>
            <canvas id="signupsChart" height="120"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Views by Category (Top 10)</h3>
            <canvas id="categoryChart" height="160"></canvas>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Deposit Revenue — Last 30 Days</h3>
            @if(count($revenuePerDay) > 0)
                <canvas id="revenueChart" height="120"></canvas>
            @else
                <div class="flex items-center justify-center h-32 text-gray-400 text-sm">No revenue data yet</div>
            @endif
        </div>
    </div>

    {{-- Ad Performance Table --}}
    @if(count($adStats) > 0)
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm mb-4">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Ad Creative Performance</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Placement</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium text-right">Impressions</th>
                        <th class="px-4 py-3 font-medium text-right">Clicks</th>
                        <th class="px-4 py-3 font-medium text-right">CTR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($adStats as $ad)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $ad['name'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ $ad['placement'] === 'pre_roll' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                {{ $ad['placement'] === 'mid_roll' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : '' }}
                                {{ $ad['placement'] === 'post_roll' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                {{ $ad['placement'] === 'outstream' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                            ">{{ ucwords(str_replace('_', ' ', $ad['placement'])) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 uppercase text-xs">{{ $ad['type'] }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($ad['impressions']) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($ad['clicks']) }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $ad['ctr'] >= 1 ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $ad['ctr'] }}%
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 text-center text-sm text-gray-400 mb-4">
        No ad creative data yet. Enable video ads and impressions will be tracked here.
    </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        const isDark = document.documentElement.classList.contains('dark');
        const gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const textColor  = isDark ? '#9ca3af' : '#6b7280';
        const labels     = @json($dateLabels);

        Chart.defaults.color = textColor;
        Chart.defaults.borderColor = gridColor;
        Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
        Chart.defaults.font.size   = 11;

        function lineChart(id, label, data, color) {
            const ctx = document.getElementById(id);
            if (!ctx) return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data,
                        borderColor: color,
                        backgroundColor: color + '22',
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 0,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        lineChart('uploadsChart', 'Uploads', @json($uploadSeries),  '#6366f1');
        lineChart('signupsChart', 'Signups', @json($signupSeries),  '#10b981');

        @if(count($revenuePerDay) > 0)
        lineChart('revenueChart', 'Revenue ($)', @json($revenueSeries), '#f59e0b');
        @endif

        // Category bar chart
        const catCtx = document.getElementById('categoryChart');
        if (catCtx) {
            new Chart(catCtx, {
                type: 'bar',
                data: {
                    labels: @json($catLabels),
                    datasets: [{
                        label: 'Total Views',
                        data: @json($catValues),
                        backgroundColor: '#6366f1cc',
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 } },
                        y: { grid: { display: false } }
                    }
                }
            });
        }
    })();
    </script>
</x-filament-panels::page>
