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
        <div class="ht-panel mt-6">
            <div class="ht-panel__header">
                <h3>Ad Creative Performance</h3>
            </div>
            <div class="ht-panel__body-scroll">
                <table class="ht-panel__table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Placement</th>
                            <th>Type</th>
                            <th class="ht-right">Impressions</th>
                            <th class="ht-right">Clicks</th>
                            <th class="ht-right">CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adStats as $ad)
                            <tr>
                                <td class="ht-panel__name">{{ $ad['name'] }}</td>
                                <td>
                                    <span class="ht-pill ht-pill--{{ $ad['placement'] }}">
                                        {{ ucwords(str_replace('_', ' ', $ad['placement'])) }}
                                    </span>
                                </td>
                                <td class="ht-muted ht-uppercase">{{ $ad['type'] }}</td>
                                <td class="ht-right ht-num">{{ number_format($ad['impressions']) }}</td>
                                <td class="ht-right ht-num">{{ number_format($ad['clicks']) }}</td>
                                <td class="ht-right ht-num {{ $ad['ctr'] >= 1 ? 'ht-ctr-good' : 'ht-muted' }}">
                                    {{ $ad['ctr'] }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="ht-panel mt-6">
            <div class="ht-panel__empty">
                No ad creative data yet. Enable video ads and impressions will be tracked here.
            </div>
        </div>
    @endif
</x-filament-panels::page>
