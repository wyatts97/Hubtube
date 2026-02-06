<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Step 1: Connection --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 1: Connect to Bunny Stream</h3>

            @if($isConnected)
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-3">
                        <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-medium text-green-700 dark:text-green-300">Connected to Bunny Stream</p>
                            <p class="text-sm text-green-600 dark:text-green-400">Library ID: {{ config('services.bunny_stream.library_id') }} — {{ $bunnyTotalVideos }} videos in library</p>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Set <code class="bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-xs">BUNNY_STREAM_API_KEY</code> in your <code class="bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-xs">.env</code> file, then test the connection.
                </p>
            @endif

            <x-filament::button wire:click="testConnection" wire:loading.attr="disabled" color="primary">
                <span wire:loading.remove wire:target="testConnection">
                    <x-heroicon-m-signal class="w-4 h-4 mr-2" />
                    {{ $isConnected ? 'Re-test Connection' : 'Test Connection' }}
                </span>
                <span wire:loading wire:target="testConnection" class="flex items-center gap-2">
                    <x-filament::loading-indicator class="w-4 h-4" />
                    Testing...
                </span>
            </x-filament::button>
        </div>

        {{-- Step 2: Stats Overview --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Step 2: Migration Status</h3>
                <x-filament::button wire:click="refreshStats" size="sm" color="gray">
                    <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                    Refresh
                </x-filament::button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $embeddedCount }}</p>
                    <p class="text-xs text-orange-500 dark:text-orange-300 mt-1">Embedded (awaiting download)</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $pendingCount }}</p>
                    <p class="text-xs text-blue-500 dark:text-blue-300 mt-1">Currently Downloading</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $nativeCount }}</p>
                    <p class="text-xs text-green-500 dark:text-green-300 mt-1">Native (downloaded)</p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $downloadFailedCount }}</p>
                    <p class="text-xs text-red-500 dark:text-red-300 mt-1">Failed</p>
                </div>
            </div>

            @if($embeddedCount + $nativeCount > 0)
                @php
                    $total = $embeddedCount + $nativeCount + $downloadFailedCount + $pendingCount;
                    $pct = $total > 0 ? round(($nativeCount / $total) * 100) : 0;
                @endphp
                <div class="mb-2">
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Migration Progress</span>
                        <span>{{ $pct }}% complete ({{ $nativeCount }} / {{ $total }})</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Step 3: Migration Controls --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 3: Start Migration</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Storage Destination</label>
                    <select wire:model="targetDisk" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($this->availableDisks as $disk => $label)
                            <option value="{{ $disk }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Where downloaded video files will be stored</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Queue Info</label>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        Downloads run on the <code class="bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-xs">downloads</code> queue.
                        Run with: <code class="bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-xs">php artisan queue:work --queue=downloads</code>
                    </p>
                </div>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-yellow-700 dark:text-yellow-300">Before starting:</p>
                        <ul class="text-sm text-yellow-600 dark:text-yellow-400 mt-1 list-disc list-inside space-y-0.5">
                            <li>Ensure you have enough disk space (videos can be 100MB–2GB+ each)</li>
                            <li>Make sure your Bunny Stream library has <strong>MP4 Fallback</strong> enabled and <strong>Direct URL Access</strong> allowed</li>
                            <li>Start a queue worker: <code class="text-xs">php artisan queue:work --queue=downloads --timeout=900</code></li>
                            <li>Videos will seamlessly switch from iframe embed to native <code class="text-xs">&lt;video&gt;</code> player as each download completes</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <x-filament::button
                    wire:click="startMigration"
                    wire:loading.attr="disabled"
                    wire:target="startMigration"
                    color="success"
                    size="lg"
                    :disabled="$embeddedCount === 0"
                >
                    <span wire:loading.remove wire:target="startMigration">
                        <x-heroicon-m-cloud-arrow-down class="w-5 h-5 mr-2" />
                        Download {{ $embeddedCount }} Embedded Videos
                    </span>
                    <span wire:loading wire:target="startMigration" class="flex items-center gap-2">
                        <x-filament::loading-indicator class="w-5 h-5" />
                        Queuing...
                    </span>
                </x-filament::button>

                @if($downloadFailedCount > 0)
                    <x-filament::button
                        wire:click="retryFailed"
                        wire:loading.attr="disabled"
                        color="warning"
                        size="lg"
                    >
                        <x-heroicon-m-arrow-path class="w-5 h-5 mr-2" />
                        Retry {{ $downloadFailedCount }} Failed
                    </x-filament::button>
                @endif
            </div>

            @if($queuedCount > 0)
                <div class="mt-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <p class="text-sm text-green-700 dark:text-green-300">
                        <x-heroicon-m-check-circle class="w-4 h-4 inline mr-1" />
                        {{ $queuedCount }} videos queued for download. They will process in the background.
                        Click <strong>Refresh</strong> above to see progress.
                    </p>
                </div>
            @endif
        </div>

        {{-- Embedded Videos Table --}}
        @if(count($this->embeddedVideos) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Embedded Videos ({{ $embeddedCount }} total)</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">ID</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Title</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Bunny ID</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Status</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Views</th>
                                <th class="text-right py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->embeddedVideos as $ev)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-300">{{ $ev['id'] }}</td>
                                    <td class="py-2 px-3 text-gray-900 dark:text-white max-w-xs truncate">{{ $ev['title'] }}</td>
                                    <td class="py-2 px-3">
                                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $ev['source_video_id'] ?? '—' }}</code>
                                    </td>
                                    <td class="py-2 px-3">
                                        @if($ev['status'] === 'downloading')
                                            <span class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 text-xs font-medium">
                                                <x-filament::loading-indicator class="w-3 h-3" /> Downloading
                                            </span>
                                        @elseif($ev['status'] === 'download_failed')
                                            <span class="text-red-600 dark:text-red-400 text-xs font-medium">Failed</span>
                                        @else
                                            <span class="text-orange-600 dark:text-orange-400 text-xs font-medium">Embedded</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-300">{{ number_format($ev['views_count'] ?? 0) }}</td>
                                    <td class="py-2 px-3 text-right">
                                        @if($ev['status'] !== 'downloading')
                                            <x-filament::button
                                                wire:click="downloadSingle({{ $ev['id'] }})"
                                                size="xs"
                                                color="primary"
                                            >
                                                Download
                                            </x-filament::button>
                                        @else
                                            <span class="text-xs text-gray-400">In queue</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Recent Downloads --}}
        @if(count($this->recentDownloads) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Downloads</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">ID</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Title</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Bunny ID</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Local Path</th>
                                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->recentDownloads as $dl)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-300">{{ $dl['id'] }}</td>
                                    <td class="py-2 px-3 text-gray-900 dark:text-white max-w-xs truncate">{{ $dl['title'] }}</td>
                                    <td class="py-2 px-3">
                                        <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $dl['source_video_id'] }}</code>
                                    </td>
                                    <td class="py-2 px-3">
                                        <code class="text-xs bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 px-1.5 py-0.5 rounded">{{ $dl['video_path'] }}</code>
                                    </td>
                                    <td class="py-2 px-3 text-gray-500 dark:text-gray-400 text-xs">
                                        {{ \Carbon\Carbon::parse($dl['updated_at'])->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
