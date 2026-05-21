<x-filament-panels::page>
    <div class="ht-dashboard-stack">

        {{-- Moderation Alert Banner --}}
        @if($this->getPendingModerationCount() > 0)
            <a href="{{ route('filament.admin.resources.videos.index') }}?tableFilters[needs_moderation][isActive]=true" class="ht-moderation-banner hover:opacity-90 transition-opacity">
                <x-phosphor-warning class="w-5 h-5 shrink-0" />
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
                        <x-phosphor-exclamation-circle class="w-3.5 h-3.5" />
                        {{ $this->getFailedCount() }} failed — view
                    </a>
                @endif
            </div>
        @endif

        {{-- Widget Grid --}}
        <div class="ht-dashboard-grid">
            @foreach($this->getOrderedWidgets() as $widget)
                <div class="min-w-0 overflow-x-auto {{ $widget['span'] === 'full' ? 'md:col-span-2' : '' }}">
                    @livewire($widget['class'], [], key($widget['key']))
                </div>
            @endforeach
        </div>

    </div>
</x-filament-panels::page>
