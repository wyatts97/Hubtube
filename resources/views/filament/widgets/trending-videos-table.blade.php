@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Video> $videos */
    /** @var string $heading */
    /** @var string $periodLabel */

    $periods = [
        'today' => 'Today',
        'week'  => 'This Week',
        'month' => 'This Month',
        'year'  => 'This Year',
        'all'   => 'All Time',
    ];
@endphp

<div class="ht-vidwidget">
    <div class="ht-vidwidget__header">
        <div class="flex items-center gap-2">
            <span class="ht-vidwidget__icon ht-vidwidget__icon--trending">
                <x-heroicon-m-fire class="w-4 h-4" />
            </span>
            <h3 class="ht-vidwidget__title">{{ $heading }}</h3>
        </div>

        <div class="ht-vidwidget__filter" x-data="{ open: false }">
            <button type="button"
                    @click="open = !open"
                    @click.away="open = false"
                    class="ht-vidwidget__filter-btn">
                <x-heroicon-m-funnel class="w-3.5 h-3.5" />
                <span>{{ $periodLabel }}</span>
                <x-heroicon-m-chevron-down class="w-3 h-3 opacity-60" />
            </button>
            <div x-show="open"
                 x-transition
                 x-cloak
                 class="ht-vidwidget__filter-menu">
                @foreach($periods as $key => $label)
                    <button type="button"
                            wire:click="setPeriod('{{ $key }}')"
                            @click="open = false"
                            class="ht-vidwidget__filter-item {{ $this->trendingPeriod === $key ? 'is-active' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="ht-vidwidget__body">
        @forelse($videos as $index => $video)
            <a href="{{ route('filament.admin.resources.videos.edit', $video) }}"
               class="ht-vidrow" wire:navigate>
                <span class="ht-vidrow__rank" data-rank="{{ $index + 1 }}">
                    {{ $index + 1 }}
                </span>

                <div class="ht-vidrow__thumb">
                    @if($video->thumbnail_url)
                        <img src="{{ $video->thumbnail_url }}"
                             alt=""
                             loading="lazy"
                             decoding="async">
                    @else
                        <div class="ht-vidrow__thumb-fallback">
                            <x-heroicon-o-film class="w-5 h-5" />
                        </div>
                    @endif
                    <span class="ht-vidrow__play">
                        <x-heroicon-s-play class="w-3.5 h-3.5" />
                    </span>
                </div>

                <div class="ht-vidrow__body">
                    <div class="ht-vidrow__title" title="{{ $video->title }}">
                        {{ $video->title }}
                    </div>
                    <div class="ht-vidrow__meta">
                        <span class="truncate">{{ $video->user?->username ?? 'Unknown' }}</span>
                    </div>
                </div>

                <div class="ht-vidrow__stats">
                    <span class="ht-vidstat ht-vidstat--views">
                        <x-heroicon-m-eye class="w-3 h-3" />
                        {{ number_format($video->views_count ?? 0) }}
                    </span>
                    <span class="ht-vidstat ht-vidstat--likes">
                        <x-heroicon-m-hand-thumb-up class="w-3 h-3" />
                        {{ number_format($video->likes_count ?? 0) }}
                    </span>
                </div>
            </a>
        @empty
            <div class="ht-vidwidget__empty">
                <x-heroicon-o-fire class="w-6 h-6 mx-auto mb-2 opacity-40" />
                No trending videos for this period yet.
            </div>
        @endforelse
    </div>
</div>
