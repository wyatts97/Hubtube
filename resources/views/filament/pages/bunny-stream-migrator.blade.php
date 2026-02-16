<x-filament-panels::page>
    {{-- Poll every 3s while migrating or downloads are active --}}
    @if($isMigrating || count($activeSlots) > 0)
        <div wire:poll.3s="pollProgress"></div>
    @endif

    <style>
        .ht-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
        @media (min-width: 640px) { .ht-stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1024px) { .ht-stats-grid { grid-template-columns: repeat(6, 1fr); } }
    </style>

    <div class="space-y-6">

        {{-- Connection --}}
        <div class="bg-gray-900 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Bunny Stream Connection</h3>

            @if($isConnected)
                <div class="bg-green-900/20 border border-green-800 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-3">
                        <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-green-300">Connected</p>
                            <p class="text-sm text-green-400">{{ $bunnyTotalVideos }} videos in Bunny Stream library</p>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-400 mb-4">
                    Configure API Key and Library ID in Admin &rarr; Integrations &rarr; Services, then test the connection.
                </p>
            @endif

            <x-filament::button wire:click="testConnection" wire:loading.attr="disabled" color="primary">
                <span wire:loading.remove wire:target="testConnection">
                    {{ $isConnected ? 'Re-test Connection' : 'Test Connection' }}
                </span>
                <span wire:loading wire:target="testConnection" class="flex items-center gap-2">
                    <x-filament::loading-indicator class="w-4 h-4" />
                    Testing...
                </span>
            </x-filament::button>
        </div>

        {{-- Pipeline Stats --}}
        <div class="bg-gray-900 rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-white">Pipeline Status</h3>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">{{ $diskFreeSpace }}</span>
                    @if(!$isMigrating)
                        <x-filament::button wire:click="refreshStats" size="xs" color="gray">Refresh</x-filament::button>
                    @endif
                </div>
            </div>

            <div class="ht-stats-grid mb-6">
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-orange-400">{{ $pendingDownloadCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Pending Download</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-blue-400">{{ $downloadingCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Downloading</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-red-400">{{ $downloadFailedCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Download Failed</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-yellow-400">{{ $processingCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Processing (FFmpeg)</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-green-400">{{ $processedCount }}</p>
                    <p class="text-xs text-gray-400 mt-1">Completed</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-gray-300">{{ $totalImported }}</p>
                    <p class="text-xs text-gray-400 mt-1">Total Imported</p>
                </div>
            </div>

            {{-- Progress bar --}}
            @if($totalImported > 0)
                <div class="mb-2">
                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                        <span>Overall Progress</span>
                        <span>{{ $this->progressPercent }}% ({{ $processedCount }} / {{ $totalImported }})</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full transition-all duration-500" style="width: {{ $this->progressPercent }}%"></div>
                    </div>
                </div>
            @endif

            {{-- Active downloads indicator --}}
            @if(count($activeSlots) > 0)
                <div class="mt-4 bg-blue-900/20 border border-blue-800 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <x-filament::loading-indicator class="w-5 h-5 text-blue-400" />
                        <div>
                            <p class="text-sm font-medium text-blue-300">
                                {{ count($activeSlots) }} download{{ count($activeSlots) > 1 ? 's' : '' }} in progress
                            </p>
                            <p class="text-xs text-blue-400 mt-0.5">
                                Session: {{ $completedThisSession }} downloaded, {{ $failedThisSession }} failed
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Controls --}}
        <div class="bg-gray-900 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Download Controls</h3>

            @if(!$isMigrating)
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-1">Concurrent Downloads</label>
                    <select wire:model.live="concurrency" class="rounded-lg border-gray-600 bg-gray-800 text-white text-sm" style="width: auto;">
                        <option value="1">1 (safe, low resource)</option>
                        <option value="2">2 (moderate)</option>
                        <option value="3">3 (fast, high resource)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">More concurrent downloads = faster migration but higher CPU/disk/bandwidth usage</p>
                </div>

                <div class="bg-yellow-900/20 border border-yellow-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-yellow-300">Before starting:</p>
                            <ul class="text-sm text-yellow-400 mt-1 list-disc list-inside space-y-0.5">
                                <li>Import videos via WP Import first (they'll appear as "Pending Download")</li>
                                <li>Ensure sufficient disk space — each video can be 100MB–2GB+</li>
                                <li>Downloads run as background processes — page can be reloaded safely</li>
                                <li>After download, videos are queued for FFmpeg processing (ultrafast preset)</li>
                                <li>Videos only go live on the site after full processing completes</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-4 flex-wrap">
                @if($isMigrating && !$shouldStop)
                    <x-filament::button wire:click="stopMigration" color="danger" size="lg">
                        Stop Migration
                    </x-filament::button>
                    <span class="text-sm text-gray-400">
                        {{ count($activeSlots) }} active, {{ $completedThisSession }} completed this session
                    </span>
                @elseif($shouldStop && count($activeSlots) > 0)
                    <span class="text-sm text-yellow-400 flex items-center gap-2">
                        <x-filament::loading-indicator class="w-4 h-4" />
                        Stopping... waiting for {{ count($activeSlots) }} download(s) to finish
                    </span>
                @else
                    <x-filament::button
                        wire:click="startMigration"
                        wire:loading.attr="disabled"
                        wire:target="startMigration"
                        color="success"
                        size="lg"
                        :disabled="$pendingDownloadCount === 0 && $downloadFailedCount === 0"
                    >
                        <span wire:loading.remove wire:target="startMigration">
                            Download {{ $pendingDownloadCount }} Pending Videos
                        </span>
                        <span wire:loading wire:target="startMigration" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            Starting...
                        </span>
                    </x-filament::button>

                    @if($downloadFailedCount > 0)
                        <x-filament::button wire:click="retryFailed" wire:loading.attr="disabled" color="warning" size="lg">
                            Retry {{ $downloadFailedCount }} Failed
                        </x-filament::button>
                    @endif
                @endif
            </div>
        </div>

        {{-- Session Log --}}
        @if(count($migrationLog) > 0)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Session Log</h3>

                <div class="overflow-x-auto max-h-80 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-900">
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Time</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">ID</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Title</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($migrationLog as $entry)
                                <tr class="border-b border-gray-800">
                                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $entry['time'] ?? '' }}</td>
                                    <td class="py-2 px-3 text-gray-300">{{ $entry['id'] }}</td>
                                    <td class="py-2 px-3 text-white max-w-xs truncate">{{ $entry['title'] }}</td>
                                    <td class="py-2 px-3">
                                        @if($entry['status'] === 'downloaded')
                                            <span class="text-green-400 text-xs font-medium">Downloaded &rarr; Processing</span>
                                        @else
                                            <span class="text-red-400 text-xs font-medium" title="{{ $entry['error'] ?? '' }}">Failed: {{ Str::limit($entry['error'] ?? 'Unknown', 60) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Download Queue Table --}}
        @if(count($this->queuedVideos) > 0)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Download Queue ({{ $pendingDownloadCount + $downloadingCount + $downloadFailedCount }})</h3>

                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-gray-900">
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">ID</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Title</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Bunny ID</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Status</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Views</th>
                                <th class="text-right py-2 px-3 text-gray-400 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->queuedVideos as $qv)
                                <tr class="border-b border-gray-800">
                                    <td class="py-2 px-3 text-gray-300">{{ $qv['id'] }}</td>
                                    <td class="py-2 px-3 text-white max-w-xs truncate">{{ $qv['title'] }}</td>
                                    <td class="py-2 px-3">
                                        <code class="text-xs bg-gray-800 px-1.5 py-0.5 rounded text-gray-400">{{ $qv['source_video_id'] ?? '-' }}</code>
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($qv['status'] === 'downloading')
                                            <span class="text-blue-400 text-xs font-medium flex items-center gap-1">
                                                <x-filament::loading-indicator class="w-3 h-3" /> Downloading
                                            </span>
                                        @elseif($qv['status'] === 'download_failed')
                                            <span class="text-red-400 text-xs font-medium" title="{{ $qv['failure_reason'] ?? '' }}">Failed</span>
                                        @else
                                            <span class="text-orange-400 text-xs font-medium">Pending</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-gray-300">{{ number_format($qv['views_count'] ?? 0) }}</td>
                                    <td class="py-2 px-3 text-right">
                                        @if($qv['status'] !== 'downloading')
                                            <x-filament::button
                                                wire:click="downloadSingle({{ $qv['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="downloadSingle({{ $qv['id'] }})"
                                                size="xs"
                                                color="primary"
                                            >
                                                <span wire:loading.remove wire:target="downloadSingle({{ $qv['id'] }})">Download</span>
                                                <span wire:loading wire:target="downloadSingle({{ $qv['id'] }})" class="flex items-center gap-1">
                                                    <x-filament::loading-indicator class="w-3 h-3" /> ...
                                                </span>
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Recent Downloads / Processed --}}
        @if(count($this->recentDownloads) > 0)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Recent Downloads</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">ID</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Title</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Status</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Path</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->recentDownloads as $dl)
                                <tr class="border-b border-gray-800">
                                    <td class="py-2 px-3 text-gray-300">{{ $dl['id'] }}</td>
                                    <td class="py-2 px-3 text-white max-w-xs truncate">{{ $dl['title'] }}</td>
                                    <td class="py-2 px-3">
                                        @if($dl['status'] === 'processed')
                                            <span class="text-green-400 text-xs font-medium">Processed</span>
                                        @elseif($dl['status'] === 'processing')
                                            <span class="text-yellow-400 text-xs font-medium flex items-center gap-1">
                                                <x-filament::loading-indicator class="w-3 h-3" /> Processing
                                            </span>
                                        @else
                                            <span class="text-blue-400 text-xs font-medium">Queued</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        <code class="text-xs bg-gray-800 text-green-300 px-1.5 py-0.5 rounded">{{ $dl['video_path'] ?? '-' }}</code>
                                    </td>
                                    <td class="py-2 px-3 text-gray-500 text-xs">{{ $dl['updated_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
