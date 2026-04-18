<x-filament-panels::page>
    <div class="space-y-5">

        {{-- Welcome Banner + Quick Actions --}}
        <div class="ht-welcome-card">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->getGreeting() }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ now()->format('l, F j, Y') }}
                    <span class="hidden md:inline-flex items-center gap-1 ml-2 text-xs">
                        · Press
                        <kbd class="px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 font-mono text-[10px]">Ctrl</kbd>
                        +
                        <kbd class="px-1.5 py-0.5 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 font-mono text-[10px]">K</kbd>
                        to search
                    </span>
                </p>
            </div>
            <div class="ht-quick-actions">
                <x-filament::button
                    tag="a"
                    href="{{ route('filament.admin.resources.videos.create') }}"
                    icon="heroicon-o-arrow-up-tray"
                    size="sm"
                >
                    Upload Video
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    href="/"
                    target="_blank"
                    icon="heroicon-o-globe-alt"
                    color="gray"
                    size="sm"
                >
                    View Site
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    href="{{ route('filament.admin.pages.analytics') }}"
                    icon="heroicon-o-chart-bar"
                    color="gray"
                    size="sm"
                >
                    Analytics
                </x-filament::button>
            </div>
        </div>

        {{-- Moderation Alert Banner --}}
        @if($this->getPendingModerationCount() > 0)
            <a href="{{ route('filament.admin.resources.videos.index') }}?tableFilters[needs_moderation][isActive]=true" class="ht-moderation-banner hover:opacity-90 transition-opacity">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" />
                <span>
                    <strong>{{ $this->getPendingModerationCount() }} {{ Str::plural('video', $this->getPendingModerationCount()) }}</strong>
                    pending moderation — click to review
                </span>
            </a>
        @endif

        {{-- Processing / Failed alerts --}}
        @if($this->getProcessingCount() > 0 || $this->getFailedCount() > 0)
            <div class="flex gap-3 flex-wrap">
                @if($this->getProcessingCount() > 0)
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/15">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                        </span>
                        {{ $this->getProcessingCount() }} {{ Str::plural('video', $this->getProcessingCount()) }} encoding
                    </div>
                @endif
                @if($this->getFailedCount() > 0)
                    <a href="{{ route('filament.admin.resources.videos.index') }}?tableFilters[status][value]=failed" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/15 hover:opacity-80 transition-opacity">
                        <x-heroicon-m-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $this->getFailedCount() }} failed — view
                    </a>
                @endif
            </div>
        @endif

        {{-- Widget Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach($this->getOrderedWidgets() as $widget)
                <div class="min-w-0 overflow-x-auto {{ $widget['span'] === 'full' ? 'md:col-span-2' : '' }}">
                    @livewire($widget['class'], [], key($widget['key']))
                </div>
            @endforeach
        </div>

    </div>
</x-filament-panels::page>
