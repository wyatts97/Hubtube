<x-filament-panels::page>
    {{-- Tab navigation at the top of the page content --}}
    <div class="ht-analytics-tabs" role="tablist">
        <button
            type="button"
            class="ht-analytics-tabs__tab {{ $activeTab === 'local' ? 'is-active' : '' }}"
            wire:click="$set('activeTab', 'local')"
            role="tab"
            aria-selected="{{ $activeTab === 'local' ? 'true' : 'false' }}"
        >
            <x-filament::icon icon="phosphor-chart-bar" class="w-4 h-4" />
            Local Analytics
        </button>
        <button
            type="button"
            class="ht-analytics-tabs__tab {{ $activeTab === 'google' ? 'is-active' : '' }}"
            wire:click="$set('activeTab', 'google')"
            role="tab"
            aria-selected="{{ $activeTab === 'google' ? 'true' : 'false' }}"
        >
            <x-filament::icon icon="phosphor-globe" class="w-4 h-4" />
            Google Analytics
        </button>
    </div>

    @if($activeTab === 'local')
        @php
            $summary    = $this->getSummaryStats();
            $adStats    = $this->getAdPerformance();
            $overallCtr = $summary['total_impressions'] > 0
                ? round(($summary['total_clicks'] / $summary['total_impressions']) * 100, 2)
                : 0;
            $localWidgets = $this->getLocalWidgets();
        @endphp

        {{-- Summary Cards --}}
        <div class="ht-summary-grid">
            @foreach ([
                ['label' => 'Total Videos',   'value' => number_format($summary['total_videos']),      'sub' => '+'.number_format($summary['videos_this_week']).' this week',                         'icon' => 'phosphor-film-strip',              'tint' => 'indigo'],
                ['label' => 'Total Users',    'value' => number_format($summary['total_users']),       'sub' => '+'.number_format($summary['users_this_week']).' this week',                          'icon' => 'phosphor-users',             'tint' => 'emerald'],
                ['label' => 'Total Views',    'value' => number_format($summary['total_views']),       'sub' => 'All time',                                                                           'icon' => 'phosphor-eye',               'tint' => 'sky'],
                ['label' => 'Ad Impressions', 'value' => number_format($summary['total_impressions']), 'sub' => number_format($summary['total_clicks']).' clicks · '.$overallCtr.'% CTR',             'icon' => 'phosphor-cursor', 'tint' => 'amber'],
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

        {{-- Ad Performance Table --}}
        @if(count($adStats) > 0)
            <div class="ht-panel mb-6">
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
            <div class="ht-panel mb-6">
                <div class="ht-panel__empty">
                    No ad creative data yet. Enable video ads and impressions will be tracked here.
                </div>
            </div>
        @endif

        {{-- Local analytics widgets --}}
        @if(count($localWidgets))
            <x-filament-widgets::widgets :widgets="$localWidgets" :columns="2" />
        @endif
    @else
        @php
            $googleWidgets = $this->getGoogleWidgets();
            $googleEnabled = (bool) \App\Models\Setting::get('google_analytics_enabled', false);
            $googleTestStatus = \App\Models\Setting::get('google_analytics_last_test_status', '');
            $googleTestMessage = \App\Models\Setting::get('google_analytics_last_test_message', '');
        @endphp

        {{-- Google Analytics configuration form --}}
        <form wire:submit="save" class="mb-6">
            {{ $this->form }}
        </form>

        {{-- Connection test result --}}
        @if($googleEnabled && $googleTestStatus === 'error' && $googleTestMessage)
            <div class="rounded-lg border border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/20 p-4 mb-6 text-sm text-red-700 dark:text-red-300">
                <div class="font-semibold mb-1">Google Analytics connection failed</div>
                <div class="font-mono text-xs">{{ $googleTestMessage }}</div>
                <div class="mt-2 text-xs">Fix the issue, then re-save the settings above to test again.</div>
            </div>
        @endif

        {{-- Google Analytics widgets --}}
        @if($googleEnabled && count($googleWidgets))
            <x-filament-widgets::widgets :widgets="$googleWidgets" :columns="2" />
        @elseif($googleEnabled)
            <div class="ht-panel">
                <div class="ht-panel__empty">
                    Save the settings above to test the connection. Once the connection succeeds, the analytics widgets will appear here.
                </div>
            </div>
        @else
            <div class="ht-panel">
                <div class="ht-panel__empty">
                    Enable Google Analytics above and save the settings to display the analytics widgets.
                </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>
