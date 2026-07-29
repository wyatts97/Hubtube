@php
    $data = $this->getVisitorData();
    $chart = $data['chart'];
    $max = max($chart) ?: 1;
    $range = $visitorRange;
@endphp

<div class="fi-widget rounded-xl bg-white dark:bg-gray-900 shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden h-full flex flex-col">
    <div class="p-4 flex-1">
        {{-- Header row: label + toggle buttons --}}
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Visitors</span>
            </div>

            {{-- 1D / 7D / 14D toggle buttons --}}
            <div class="inline-flex rounded-md shadow-sm">
                @foreach (['1d' => '1D', '7d' => '7D', '14d' => '14D'] as $key => $label)
                    <button
                        type="button"
                        wire:click="setVisitorRange('{{ $key }}')"
                        @class([
                            'px-2 py-0.5 text-xs font-medium border transition-colors',
                            'bg-primary-600 text-white border-primary-600' => $range === $key,
                            'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' => $range !== $key,
                            'rounded-l-md' => $key === '1d',
                            'rounded-r-md' => $key === '14d',
                            '-ml-px' => $key !== '1d',
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Main value --}}
        <div class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ number_format($data['count']) }}
        </div>

        {{-- Description --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Unique visitors · {{ $data['label'] }}
            @if ($data['total_visits'] > $data['count'])
                · {{ number_format($data['total_visits']) }} total visits
            @endif
        </div>
    </div>

    {{-- Sparkline --}}
    @if (!empty($chart) && max($chart) > 0)
        @php
            $width = 100;
            $height = 20;
            $count = count($chart);
            $step = $count > 1 ? $width / ($count - 1) : $width;
            $points = [];
            foreach ($chart as $i => $val) {
                $x = $i * $step;
                $y = $height - (($val / $max) * $height);
                $points[] = "{$x},{$y}";
            }
            $polyline = implode(' ', $points);
        @endphp
        <div class="px-4 pb-3">
            <svg class="w-full h-5" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none">
                <polyline
                    points="{{ $polyline }}"
                    fill="none"
                    stroke="rgb(99, 102, 241)"
                    stroke-width="1.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
            </svg>
        </div>
    @endif
</div>
