<div class="flex items-center gap-4 text-xs">
    {{-- Storage --}}
    <div class="flex items-center gap-1.5" title="Disk: {{ $metrics['storage']['used'] }} / {{ $metrics['storage']['total'] }}">
        <x-heroicon-m-circle-stack class="w-4 h-4 text-gray-400" />
        <span class="text-gray-500 dark:text-gray-400">{{ $metrics['storage']['used'] }}</span>
        <span class="text-gray-400 dark:text-gray-500">/</span>
        <span class="text-gray-500 dark:text-gray-400">{{ $metrics['storage']['total'] }}</span>
        @if($metrics['storage']['percent'] > 90)
            <span class="inline-block w-2 h-2 rounded-full bg-red-500" title="Storage critical"></span>
        @elseif($metrics['storage']['percent'] > 75)
            <span class="inline-block w-2 h-2 rounded-full bg-yellow-500" title="Storage warning"></span>
        @else
            <span class="inline-block w-2 h-2 rounded-full bg-green-500" title="Storage OK"></span>
        @endif
    </div>

    <div class="w-px h-4 bg-gray-300 dark:bg-gray-600"></div>

    {{-- FFmpeg --}}
    <div class="flex items-center gap-1.5" title="FFmpeg: {{ $metrics['ffmpeg']['enabled'] ? 'Enabled' : 'Disabled' }}, {{ $metrics['ffmpeg']['available'] ? 'Installed' : 'Not found' }}">
        <x-heroicon-m-film class="w-4 h-4 text-gray-400" />
        <span class="text-gray-500 dark:text-gray-400">FFmpeg</span>
        @if($metrics['ffmpeg']['enabled'] && $metrics['ffmpeg']['available'])
            <span class="inline-block w-2 h-2 rounded-full bg-green-500" title="FFmpeg ready"></span>
        @elseif($metrics['ffmpeg']['enabled'])
            <span class="inline-block w-2 h-2 rounded-full bg-red-500" title="FFmpeg not found"></span>
        @else
            <span class="inline-block w-2 h-2 rounded-full bg-gray-400" title="FFmpeg disabled"></span>
        @endif
        @if($metrics['ffmpeg']['processing'] > 0)
            <span class="px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-600 dark:text-blue-400 font-medium">
                {{ $metrics['ffmpeg']['processing'] }} encoding
            </span>
        @endif
        @if($metrics['ffmpeg']['pending'] > 0)
            <span class="px-1.5 py-0.5 rounded bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 font-medium">
                {{ $metrics['ffmpeg']['pending'] }} queued
            </span>
        @endif
    </div>

    <div class="w-px h-4 bg-gray-300 dark:bg-gray-600"></div>

    {{-- Scraper --}}
    <div class="flex items-center gap-1.5" title="Scraper service: {{ $metrics['scraper']['online'] ? 'Online' : 'Offline' }}">
        <x-heroicon-m-globe-alt class="w-4 h-4 text-gray-400" />
        <span class="text-gray-500 dark:text-gray-400">Scraper</span>
        @if($metrics['scraper']['online'])
            <span class="inline-block w-2 h-2 rounded-full bg-green-500" title="Online"></span>
        @else
            <span class="inline-block w-2 h-2 rounded-full bg-red-500" title="Offline"></span>
        @endif
    </div>

    {{-- Failed Jobs --}}
    @if($metrics['queue']['failed'] > 0)
        <div class="w-px h-4 bg-gray-300 dark:bg-gray-600"></div>
        <div class="flex items-center gap-1.5" title="{{ $metrics['queue']['failed'] }} failed jobs">
            <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-red-500" />
            <span class="px-1.5 py-0.5 rounded bg-red-500/20 text-red-600 dark:text-red-400 font-medium">
                {{ $metrics['queue']['failed'] }} failed
            </span>
        </div>
    @endif
</div>
