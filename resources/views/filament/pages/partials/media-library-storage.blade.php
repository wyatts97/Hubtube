{{--
    Where the disk is going.

    On the page you act from rather than behind its own nav item, because the
    question "what is eating my drive" is what sends anyone to the media
    library in the first place. Every figure comes from the folder rollups or
    an indexed aggregate — see MediaStorageReport — so this costs a handful of
    cheap queries, cached.
--}}
@php
    $report = $this->getStorageReportProperty();
    $videos = $report['videos'];
@endphp

<div class="ht-ml-panel ht-ml-storage">
    <button type="button" class="ht-ml-storage__head" wire:click="toggleStorageReport" aria-expanded="{{ $showStorageReport ? 'true' : 'false' }}">
        <x-phosphor-hard-drives class="ht-ml-icon" />
        <span class="ht-ml-storage__title">Storage</span>
        <span class="ht-ml-storage__total">{{ \App\Support\Bytes::format($report['total_bytes']) }} across {{ number_format($report['total_files']) }} files</span>
        <span class="ht-ml-spacer"></span>
        @if ($report['indexed_at'])
            <span class="ht-ml-storage__stamp">indexed {{ \Illuminate\Support\Carbon::parse($report['indexed_at'])->diffForHumans() }}</span>
        @endif
        @if ($showStorageReport)
            <x-phosphor-caret-up class="ht-ml-icon-sm" />
        @else
            <x-phosphor-caret-down class="ht-ml-icon-sm" />
        @endif
    </button>

    @if ($showStorageReport)
        <div class="ht-ml-storage__body">
            {{-- Per root, biggest first. --}}
            <div class="ht-ml-storage__group">
                <p class="ht-ml-storage__label">By folder</p>
                <ul class="ht-ml-storage__list">
                    @foreach ($report['roots'] as $root)
                        <li>
                            <button type="button" class="ht-ml-storage__row" wire:click="openDirectory(@js($root['path']))">
                                <span class="ht-ml-storage__rowName">{{ ucfirst($root['path']) }}</span>
                                <span class="ht-ml-storage__rowMeta">{{ number_format($root['files']) }} files</span>
                                <span class="ht-ml-storage__rowBytes">{{ $root['formatted'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- What the videos root is made of. This is the breakdown that
                 explains where a video library's disk actually goes. --}}
            <div class="ht-ml-storage__group">
                <p class="ht-ml-storage__label">Videos breakdown</p>
                <ul class="ht-ml-storage__list">
                    <li class="ht-ml-storage__row">
                        <span class="ht-ml-storage__rowName">Original uploads</span>
                        <span class="ht-ml-storage__rowBytes">{{ \App\Support\Bytes::format($videos['originals']) }}</span>
                    </li>
                    <li class="ht-ml-storage__row">
                        <span class="ht-ml-storage__rowName">Encoded renditions</span>
                        <span class="ht-ml-storage__rowBytes">{{ \App\Support\Bytes::format($videos['renditions']) }}</span>
                    </li>
                    <li class="ht-ml-storage__row">
                        <span class="ht-ml-storage__rowName">Posters &amp; sprites</span>
                        <span class="ht-ml-storage__rowBytes">{{ \App\Support\Bytes::format($videos['artwork']) }}</span>
                    </li>
                </ul>
            </div>

            {{-- The one figure here that can be reclaimed with no
                 re-encoding: generate_hls defaults to on, so processed/hls
                 holds a complete second copy of every rendition. --}}
            <div class="ht-ml-storage__group">
                <p class="ht-ml-storage__label">Duplicate HLS copies</p>
                <p class="ht-ml-storage__big">{{ \App\Support\Bytes::format($report['reclaimable_hls']) }}</p>
                @if ($report['reclaimable_hls'] > 0)
                    <p class="ht-ml-storage__hint">
                        A second copy of every rendition, as HLS segments. Reclaimable without re-encoding anything.
                    </p>
                @else
                    <p class="ht-ml-storage__hint">No HLS duplicates on disk.</p>
                @endif
            </div>

            {{-- A doorway into the real grid, not a parallel UI. --}}
            <div class="ht-ml-storage__group">
                <p class="ht-ml-storage__label">Biggest files</p>
                <ul class="ht-ml-storage__list">
                    @foreach (array_slice($this->getBiggestFilesProperty(), 0, 5) as $file)
                        <li class="ht-ml-storage__row">
                            <span class="ht-ml-storage__rowName" title="{{ $file['path'] }}">{{ $file['name'] }}</span>
                            <span class="ht-ml-storage__rowBytes">{{ $file['formatted'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="ht-ml-storage__actions">
                    <x-filament::button wire:click="showBiggest" size="xs" color="gray" icon="phosphor-sort-descending">
                        Show all by size
                    </x-filament::button>
                    <x-filament::button wire:click="showBiggest('video')" size="xs" color="gray" icon="phosphor-film-strip">
                        Biggest videos
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</div>
