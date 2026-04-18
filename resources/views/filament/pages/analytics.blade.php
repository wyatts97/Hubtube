<x-filament-panels::page>
    @php
        $summary    = $this->getSummaryStats();
        $adStats    = $this->getAdPerformance();
        $overallCtr = $summary['total_impressions'] > 0
            ? round(($summary['total_clicks'] / $summary['total_impressions']) * 100, 2)
            : 0;
    @endphp

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['label' => 'Total Videos',   'value' => number_format($summary['total_videos']),      'sub' => '+'.number_format($summary['videos_this_week']).' this week',                         'icon' => 'heroicon-o-film',              'tint' => 'indigo'],
            ['label' => 'Total Users',    'value' => number_format($summary['total_users']),       'sub' => '+'.number_format($summary['users_this_week']).' this week',                          'icon' => 'heroicon-o-users',             'tint' => 'emerald'],
            ['label' => 'Total Views',    'value' => number_format($summary['total_views']),       'sub' => 'All time',                                                                           'icon' => 'heroicon-o-eye',               'tint' => 'sky'],
            ['label' => 'Ad Impressions', 'value' => number_format($summary['total_impressions']), 'sub' => number_format($summary['total_clicks']).' clicks · '.$overallCtr.'% CTR',             'icon' => 'heroicon-o-cursor-arrow-rays', 'tint' => 'amber'],
        ] as $card)
        <div class="ht-summary-card" data-tint="{{ $card['tint'] }}">
            <div class="ht-summary-card__icon ht-tint-{{ $card['tint'] }}">
                <x-filament::icon :icon="$card['icon']" class="w-5 h-5" />
            </div>
            <div class="min-w-0">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums">{{ $card['value'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 truncate">{{ $card['sub'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Chart Widgets (2×2 grid, rendered via getHeaderWidgets()) --}}
    <x-filament-widgets::widgets
        :widgets="$this->getVisibleHeaderWidgets()"
        :columns="$this->getHeaderWidgetsColumns()"
        :data="$this->getWidgetData()"
    />

    {{-- Ad Performance Table --}}
    @if(count($adStats) > 0)
        <div class="mt-6 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900/40 shadow-sm">
            <div class="px-5 py-3 border-b border-gray-200 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ad Creative Performance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Placement</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold text-right">Impressions</th>
                            <th class="px-4 py-3 font-semibold text-right">Clicks</th>
                            <th class="px-4 py-3 font-semibold text-right">CTR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($adStats as $ad)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $ad['name'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                        {{ $ad['placement'] === 'pre_roll'  ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : '' }}
                                        {{ $ad['placement'] === 'mid_roll'  ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'       : '' }}
                                        {{ $ad['placement'] === 'post_roll' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400'          : '' }}
                                        {{ $ad['placement'] === 'outstream' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400'             : '' }}">
                                        {{ ucwords(str_replace('_', ' ', $ad['placement'])) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 uppercase text-[11px] tracking-wide">{{ $ad['type'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($ad['impressions']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ number_format($ad['clicks']) }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $ad['ctr'] >= 1 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $ad['ctr'] }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="mt-6 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900/40 p-8 text-center text-sm text-gray-400">
            No ad creative data yet. Enable video ads and impressions will be tracked here.
        </div>
    @endif
</x-filament-panels::page>
