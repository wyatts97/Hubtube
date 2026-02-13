<x-filament-panels::page>
    <div wire:poll.10s>
        {{-- Filter Tabs --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div class="flex flex-wrap gap-2">
                @php
                    $logCounts = $this->logCounts;
                    $tabs = [
                        'all' => ['label' => 'All', 'icon' => 'heroicon-o-bars-3', 'color' => 'gray'],
                        'admin' => ['label' => 'Admin', 'icon' => 'heroicon-o-shield-check', 'color' => 'primary'],
                        'auth' => ['label' => 'Auth', 'icon' => 'heroicon-o-key', 'color' => 'warning'],
                        'error' => ['label' => 'Errors', 'icon' => 'heroicon-o-exclamation-triangle', 'color' => 'danger'],
                        'system' => ['label' => 'System', 'icon' => 'heroicon-o-cog-6-tooth', 'color' => 'info'],
                    ];
                @endphp

                @foreach($tabs as $key => $tab)
                    <button
                        wire:click="setFilter('{{ $key }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                            {{ $filterLog === $key
                                ? 'bg-primary-600 text-white shadow-sm'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}"
                    >
                        <x-dynamic-component :component="$tab['icon']" class="w-3.5 h-3.5" />
                        {{ $tab['label'] }}
                        @if(($logCounts[$key] ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full
                                {{ $filterLog === $key
                                    ? 'bg-white/20 text-white'
                                    : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ $logCounts[$key] ?? 0 }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                {{-- Search --}}
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        wire:model.live.debounce.300ms="filterSearch"
                        type="text"
                        placeholder="Search logs..."
                        class="pl-9 pr-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-primary-500 focus:border-primary-500 w-48"
                    />
                </div>

                {{-- Clear Logs --}}
                <x-filament::button
                    size="sm"
                    color="danger"
                    icon="heroicon-o-trash"
                    wire:click="clearLog('{{ $filterLog }}')"
                    wire:confirm="Clear {{ $filterLog === 'all' ? 'ALL' : $filterLog }} logs? This cannot be undone."
                >
                    Clear
                </x-filament::button>
            </div>
        </div>

        {{-- Log Entries --}}
        @php $activities = $this->activities; @endphp

        @if($activities->count() > 0)
            <div class="space-y-2">
                @foreach($activities as $entry)
                    @php
                        $logColors = [
                            'admin' => ['bg' => 'bg-blue-500', 'text' => 'text-blue-400', 'badge' => 'bg-blue-900/30 text-blue-400 border-blue-800'],
                            'auth' => ['bg' => 'bg-amber-500', 'text' => 'text-amber-400', 'badge' => 'bg-amber-900/30 text-amber-400 border-amber-800'],
                            'error' => ['bg' => 'bg-red-500', 'text' => 'text-red-400', 'badge' => 'bg-red-900/30 text-red-400 border-red-800'],
                            'system' => ['bg' => 'bg-cyan-500', 'text' => 'text-cyan-400', 'badge' => 'bg-cyan-900/30 text-cyan-400 border-cyan-800'],
                        ];
                        $colors = $logColors[$entry->log_name] ?? ['bg' => 'bg-gray-500', 'text' => 'text-gray-400', 'badge' => 'bg-gray-900/30 text-gray-400 border-gray-800'];
                        $properties = json_decode($entry->properties, true) ?? [];
                        $causerName = $this->getCauserName($entry->causer_id, $entry->causer_type);
                        $subjectLabel = $this->getSubjectLabel($entry->subject_id, $entry->subject_type);
                        $timeAgo = \Carbon\Carbon::parse($entry->created_at)->diffForHumans();
                        $timeExact = \Carbon\Carbon::parse($entry->created_at)->format('M d, Y H:i:s');

                        $eventIcons = [
                            'created' => 'heroicon-o-plus-circle',
                            'updated' => 'heroicon-o-pencil-square',
                            'deleted' => 'heroicon-o-trash',
                        ];
                        $eventIcon = $eventIcons[$entry->event ?? ''] ?? null;
                    @endphp

                    <div class="group rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600 transition-colors">
                        <div class="flex items-start gap-3 px-4 py-3">
                            {{-- Color indicator --}}
                            <div class="shrink-0 mt-1">
                                <div class="w-2 h-2 rounded-full {{ $colors['bg'] }}"></div>
                            </div>

                            {{-- Main content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        {{-- Description --}}
                                        <p class="text-sm text-gray-900 dark:text-gray-100 font-medium">
                                            @if($eventIcon)
                                                <x-dynamic-component :component="$eventIcon" class="w-4 h-4 inline mr-1 {{ $colors['text'] }}" />
                                            @endif
                                            {{ $entry->description }}
                                        </p>

                                        {{-- Meta row --}}
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                                            {{-- Log badge --}}
                                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded border {{ $colors['badge'] }}">
                                                {{ $entry->log_name }}
                                            </span>

                                            {{-- Causer --}}
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                <x-heroicon-o-user class="w-3 h-3 inline mr-0.5" />
                                                {{ $causerName }}
                                            </span>

                                            {{-- Subject --}}
                                            @if($subjectLabel)
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    <x-heroicon-o-cube class="w-3 h-3 inline mr-0.5" />
                                                    {{ $subjectLabel }}
                                                </span>
                                            @endif

                                            {{-- Time --}}
                                            <span class="text-xs text-gray-400 dark:text-gray-500" title="{{ $timeExact }}">
                                                {{ $timeAgo }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Delete button (visible on hover) --}}
                                    <button
                                        wire:click="deleteEntry({{ $entry->id }})"
                                        wire:confirm="Delete this log entry?"
                                        class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30"
                                        title="Delete entry"
                                    >
                                        <x-heroicon-o-x-mark class="w-3.5 h-3.5 text-red-400" />
                                    </button>
                                </div>

                                {{-- Properties (expandable) --}}
                                @if(!empty($properties))
                                    <details class="mt-2">
                                        <summary class="text-xs text-gray-400 dark:text-gray-500 cursor-pointer hover:text-gray-600 dark:hover:text-gray-300 select-none">
                                            <x-heroicon-o-code-bracket class="w-3 h-3 inline mr-0.5" />
                                            Details
                                        </summary>
                                        <div class="mt-1.5 p-2.5 rounded-md bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                                            @if(isset($properties['attributes']) || isset($properties['old']))
                                                {{-- Model change diff --}}
                                                <div class="space-y-1">
                                                    @foreach($properties['attributes'] ?? [] as $field => $newVal)
                                                        @php $oldVal = $properties['old'][$field] ?? '—'; @endphp
                                                        <div class="flex items-center gap-2 text-xs">
                                                            <span class="font-medium text-gray-600 dark:text-gray-300 min-w-[80px]">{{ $field }}</span>
                                                            <span class="text-red-400 line-through">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                            <x-heroicon-o-arrow-right class="w-3 h-3 text-gray-400 shrink-0" />
                                                            <span class="text-green-400">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                {{-- Generic properties --}}
                                                <div class="space-y-1">
                                                    @foreach($properties as $key => $val)
                                                        <div class="flex items-start gap-2 text-xs">
                                                            <span class="font-medium text-gray-600 dark:text-gray-300 min-w-[80px] shrink-0">{{ $key }}</span>
                                                            @if(is_array($val))
                                                                <code class="text-gray-500 dark:text-gray-400 break-all">{{ json_encode($val, JSON_PRETTY_PRINT) }}</code>
                                                            @else
                                                                <span class="text-gray-500 dark:text-gray-400 break-all">{{ $val }}</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($activities->hasPages())
                <div class="mt-6">
                    {{ $activities->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-16 border border-dashed border-gray-300 dark:border-gray-600 rounded-xl">
                <x-heroicon-o-clipboard-document-list class="w-12 h-12 mx-auto mb-3 text-gray-400 dark:text-gray-500" />
                <p class="text-lg font-medium text-gray-900 dark:text-white">No log entries</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    @if($filterLog !== 'all' || !empty($filterSearch))
                        No entries match your current filters.
                    @else
                        Activity will appear here as admin actions and site events occur.
                    @endif
                </p>
            </div>
        @endif

        {{-- Info Panel --}}
        <div class="mt-8 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                <x-heroicon-o-information-circle class="w-4 h-4 inline mr-1" />
                What gets logged
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="flex items-start gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Admin Actions</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Settings changes, user role updates, video moderation, imports</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-2 h-2 rounded-full bg-amber-500 mt-1.5 shrink-0"></div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Authentication</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Admin logins, WP password migrations, suspicious auth events</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-2 h-2 rounded-full bg-red-500 mt-1.5 shrink-0"></div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Errors</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Server errors (500), job failures, unhandled exceptions</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <div class="w-2 h-2 rounded-full bg-cyan-500 mt-1.5 shrink-0"></div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300">System</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Model changes (user roles, video approvals, wallet balance)</p>
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-3">
                <x-heroicon-o-arrow-path class="w-3 h-3 inline mr-0.5" />
                This page auto-refreshes every 10 seconds. User/Video model changes are logged automatically via the activity trait.
            </p>
        </div>
    </div>
</x-filament-panels::page>
