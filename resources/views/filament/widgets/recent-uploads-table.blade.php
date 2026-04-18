@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Video> $videos */

    $statusMap = function (\App\Models\Video $video): array {
        return match (true) {
            $video->status === 'processed' && $video->is_approved     => ['Published',        'emerald'],
            $video->status === 'processed' && !$video->is_approved    => ['Needs Moderation', 'amber'],
            $video->status === 'processing'                           => ['Processing',       'sky'],
            $video->status === 'pending'                              => ['Pending',          'gray'],
            $video->status === 'failed'                               => ['Failed',           'rose'],
            default                                                   => [ucfirst((string) $video->status), 'gray'],
        };
    };
@endphp

<div class="ht-vidwidget">
    <div class="ht-vidwidget__header">
        <div class="flex items-center gap-2">
            <span class="ht-vidwidget__icon ht-vidwidget__icon--recent">
                <x-heroicon-m-arrow-up-tray class="w-4 h-4" />
            </span>
            <h3 class="ht-vidwidget__title">Recent Uploads</h3>
        </div>

        <a href="{{ route('filament.admin.resources.videos.index') }}"
           class="ht-vidwidget__link" wire:navigate>
            View all
            <x-heroicon-m-arrow-right class="w-3 h-3" />
        </a>
    </div>

    <div class="ht-vidwidget__body">
        @forelse($videos as $video)
            @php([$statusLabel, $statusTone] = $statusMap($video))
            <a href="{{ route('filament.admin.resources.videos.edit', $video) }}"
               class="ht-vidrow" wire:navigate>
                <div class="ht-vidrow__thumb ht-vidrow__thumb--no-rank">
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
                </div>

                <div class="ht-vidrow__body">
                    <div class="ht-vidrow__title" title="{{ $video->title }}">
                        {{ $video->title }}
                    </div>
                    <div class="ht-vidrow__meta">
                        <span class="truncate">{{ $video->user?->username ?? 'Unknown' }}</span>
                        <span class="ht-vidrow__dot">·</span>
                        <span>{{ $video->created_at?->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="ht-vidrow__stats">
                    <span class="ht-vidpill ht-vidpill--{{ $statusTone }}">{{ $statusLabel }}</span>
                    @if(($video->views_count ?? 0) > 0)
                        <span class="ht-vidstat ht-vidstat--views">
                            <x-heroicon-m-eye class="w-3 h-3" />
                            {{ number_format($video->views_count) }}
                        </span>
                    @endif
                </div>
            </a>
        @empty
            <div class="ht-vidwidget__empty">
                <x-heroicon-o-arrow-up-tray class="w-6 h-6 mx-auto mb-2 opacity-40" />
                No uploads yet.
            </div>
        @endforelse
    </div>
</div>
