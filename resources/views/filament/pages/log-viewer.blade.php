<x-filament-panels::page>
    {{-- Laravel Log Files Section --}}
    <x-filament::section>
        <x-slot name="heading">
            Laravel Log Files
        </x-slot>

        <x-slot name="description">
            Browse and search Laravel application log files.
        </x-slot>

        {{-- Log File Filters --}}
        {{ $this->form }}

        {{-- Log Entries Display --}}
        <div class="mt-6 space-y-4">
            @php
                $entries = $this->getLogEntries();
                $files = $this->getLogFiles();
            @endphp

            @if(count($files) === 0)
                <div class="text-center py-8 text-gray-500">
                    <x-heroicon-o-document class="w-12 h-12 mx-auto mb-4 opacity-50" />
                    <p>No log files found in storage/logs</p>
                </div>
            @else
                {{-- Log Stats --}}
                <div class="flex flex-wrap gap-2 text-sm">
                    <span class="text-gray-600">
                        {{ count($files) }} log file{{ count($files) !== 1 ? 's' : '' }}
                    </span>
                    @if($this->selectedLogFile)
                        <span class="text-gray-400">|</span>
                        <span class="text-gray-600">
                            Showing {{ count($entries) }} entr{{ count($entries) !== 1 ? 'ies' : 'y' }}
                        </span>
                    @endif
                </div>

                {{-- Log Entries Table --}}
                @if(count($entries) > 0)
                    <div class="overflow-x-auto rounded-lg border border-gray-700">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-800 text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Time</th>
                                    <th class="px-4 py-3 font-medium">Level</th>
                                    <th class="px-4 py-3 font-medium">Message</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @foreach($entries as $entry)
                                    <tr class="hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500 text-xs">
                                            {{ $entry['timestamp'] }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @php
                                                $levelClasses = match($entry['level_color']) {
                                                    'danger' => 'bg-red-500/20 text-red-400',
                                                    'warning' => 'bg-yellow-500/20 text-yellow-400',
                                                    'info' => 'bg-blue-500/20 text-blue-400',
                                                    default => 'bg-gray-500/20 text-gray-400',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $levelClasses }}">
                                                {{ strtoupper($entry['level']) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-gray-300">
                                                <p class="line-clamp-2">{{ Str::limit($entry['message'], 200) }}</p>
                                                @if(!empty($entry['context']))
                                                    <details class="mt-2">
                                                        <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-400">
                                                            Show context
                                                        </summary>
                                                        <pre class="mt-2 p-3 bg-gray-900 rounded text-xs text-gray-400 overflow-x-auto font-mono">{{ $entry['context'] }}</pre>
                                                    </details>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        @if($this->searchQuery)
                            <p>No entries found matching "{{ $this->searchQuery }}"</p>
                        @elseif($this->selectedLevel)
                            <p>No {{ $this->selectedLevel }} level entries found</p>
                        @else
                            <p>Select a log file to view entries</p>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </x-filament::section>

    {{-- Activity Logs Section --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Activity Logs (Database)
        </x-slot>

        <x-slot name="description">
            Recent application activity from the database.
        </x-slot>

        <x-slot name="headerActions">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}"
                size="sm"
                color="gray"
                icon="heroicon-o-arrow-top-right-on-square">
                Full Activity Logs
            </x-filament::button>
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

    {{-- Log Files List --}}
    @if(count($files) > 0)
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Available Log Files
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($files as $file)
                    <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-700">
                        <div class="flex items-center gap-3">
                            <x-heroicon-m-document-text class="w-5 h-5 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium text-gray-300">{{ $file['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $file['size'] }} · {{ $file['modified'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <x-filament::button
                                wire:click="$set('selected_log', '{{ $file['name'] }}')"
                                size="xs"
                                color="gray">
                                View
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
