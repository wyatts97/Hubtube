<x-filament-panels::page>
    <div class="space-y-6" wire:poll.10s>

        {{-- Stats Row --}}
        @php $logCounts = $this->logCounts; @endphp
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @php
                $tabs = [
                    'all'    => ['label' => 'All Logs',  'icon' => 'heroicon-o-bars-3',                'color' => 'primary'],
                    'admin'  => ['label' => 'Admin',     'icon' => 'heroicon-o-shield-check',          'color' => 'primary'],
                    'auth'   => ['label' => 'Auth',      'icon' => 'heroicon-o-key',                   'color' => 'yellow'],
                    'error'  => ['label' => 'Errors',    'icon' => 'heroicon-o-exclamation-triangle',  'color' => 'red'],
                    'system' => ['label' => 'System',    'icon' => 'heroicon-o-cog-6-tooth',           'color' => 'cyan'],
                ];
            @endphp

            @foreach($tabs as $key => $tab)
                <button
                    wire:click="setFilter('{{ $key }}')"
                    class="bg-gray-800 rounded-xl shadow p-4 text-center transition-all hover:bg-gray-700/80
                        {{ $filterLog === $key ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-gray-900' : '' }}"
                >
                    <p class="text-2xl font-bold {{ $filterLog === $key ? 'text-primary-400' : 'text-gray-300' }}">
                        {{ number_format($logCounts[$key] ?? 0) }}
                    </p>
                    <p class="text-xs text-gray-400 mt-1 flex items-center justify-center gap-1">
                        <x-dynamic-component :component="$tab['icon']" class="w-3.5 h-3.5" />
                        {{ $tab['label'] }}
                    </p>
                </button>
            @endforeach
        </div>

        {{-- Toolbar --}}
        <div class="bg-gray-800 rounded-xl shadow p-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="relative flex-1 max-w-sm">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
                    <input
                        wire:model.live.debounce.300ms="filterSearch"
                        type="text"
                        placeholder="Search logs..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border-gray-600 bg-gray-700 text-white placeholder-gray-500 focus:ring-primary-500 focus:border-primary-500"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <p class="text-xs text-gray-500">
                        <x-heroicon-o-arrow-path class="w-3.5 h-3.5 inline mr-0.5 animate-spin" style="animation-duration: 10s" />
                        Auto-refresh
                    </p>
                    <x-filament::button
                        size="sm"
                        color="danger"
                        icon="heroicon-o-trash"
                        wire:click="clearLog('{{ $filterLog }}')"
                        wire:confirm="Clear {{ $filterLog === 'all' ? 'ALL' : $filterLog }} logs? This cannot be undone."
                    >
                        Clear {{ $filterLog === 'all' ? 'All' : ucfirst($filterLog) }}
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Log Table --}}
        @php $activities = $this->activities; @endphp

        @if($activities->count() > 0)
            <div class="bg-gray-800 rounded-xl shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Time</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach($activities as $entry)
                                @php
                                    $badgeColors = [
                                        'admin'  => 'bg-blue-900/40 text-blue-400 border-blue-800',
                                        'auth'   => 'bg-yellow-900/40 text-yellow-400 border-yellow-800',
                                        'error'  => 'bg-red-900/40 text-red-400 border-red-800',
                                        'system' => 'bg-cyan-900/40 text-cyan-400 border-cyan-800',
                                    ];
                                    $badge = $badgeColors[$entry->log_name] ?? 'bg-gray-700 text-gray-400 border-gray-600';
                                    $properties = json_decode($entry->properties, true) ?? [];
                                    $causerName = $this->resolveCauserName($entry->causer_id, $entry->causer_type);
                                    $subjectLabel = $this->resolveSubjectLabel($entry->subject_id, $entry->subject_type);
                                    $timeAgo = \Carbon\Carbon::parse($entry->created_at)->diffForHumans();
                                    $timeExact = \Carbon\Carbon::parse($entry->created_at)->format('M d, Y H:i:s');
                                @endphp
                                <tr class="hover:bg-gray-700/30 group">
                                    {{-- Type Badge --}}
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded border {{ $badge }}">
                                            {{ $entry->log_name }}
                                        </span>
                                    </td>

                                    {{-- Description + Details --}}
                                    <td class="px-4 py-3 max-w-md">
                                        <p class="text-sm text-white font-medium truncate">{{ $entry->description }}</p>
                                        @if(!empty($properties))
                                            <details class="mt-1">
                                                <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-300 select-none">
                                                    Show details
                                                </summary>
                                                <div class="mt-1.5 p-2 rounded-md bg-gray-900 border border-gray-700 text-xs">
                                                    @if(isset($properties['attributes']) || isset($properties['old']))
                                                        @foreach($properties['attributes'] ?? [] as $field => $newVal)
                                                            @php $oldVal = $properties['old'][$field] ?? '—'; @endphp
                                                            <div class="flex items-center gap-2 py-0.5">
                                                                <span class="font-medium text-gray-300 min-w-[80px]">{{ $field }}</span>
                                                                <span class="text-red-400 line-through">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</span>
                                                                <span class="text-gray-600">&rarr;</span>
                                                                <span class="text-green-400">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</span>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        @foreach($properties as $pKey => $pVal)
                                                            <div class="flex items-start gap-2 py-0.5">
                                                                <span class="font-medium text-gray-300 min-w-[80px] shrink-0">{{ $pKey }}</span>
                                                                <span class="text-gray-400 break-all">{{ is_array($pVal) ? json_encode($pVal) : $pVal }}</span>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </details>
                                        @endif
                                    </td>

                                    {{-- Causer --}}
                                    <td class="px-4 py-3 text-gray-400 text-xs">
                                        {{ $causerName }}
                                    </td>

                                    {{-- Subject --}}
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        {{ $subjectLabel ?: '—' }}
                                    </td>

                                    {{-- Time --}}
                                    <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap" title="{{ $timeExact }}">
                                        {{ $timeAgo }}
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            wire:click="deleteEntry({{ $entry->id }})"
                                            wire:confirm="Delete this log entry?"
                                            class="opacity-0 group-hover:opacity-100 transition-opacity p-1 rounded hover:bg-red-900/30"
                                            title="Delete"
                                        >
                                            <x-heroicon-o-x-mark class="w-4 h-4 text-red-400" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($activities->hasPages())
                    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-700">
                        <p class="text-xs text-gray-500">
                            Showing {{ $activities->firstItem() }}–{{ $activities->lastItem() }} of {{ number_format($activities->total()) }}
                        </p>
                        <div class="flex items-center gap-2">
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="previousPage"
                                :disabled="$page <= 1"
                            >
                                Previous
                            </x-filament::button>
                            <span class="text-xs text-gray-400">Page {{ $page }}</span>
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="nextPage"
                                :disabled="!$activities->hasMorePages()"
                            >
                                Next
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="bg-gray-800 rounded-xl shadow">
                <div class="text-center py-16">
                    <x-heroicon-o-clipboard-document-list class="w-12 h-12 mx-auto mb-3 text-gray-600" />
                    <p class="text-lg font-medium text-white">No log entries</p>
                    <p class="text-sm text-gray-400 mt-1">
                        @if($filterLog !== 'all' || !empty($filterSearch))
                            No entries match your current filters.
                        @else
                            Activity will appear here as admin actions and site events occur.
                        @endif
                    </p>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
