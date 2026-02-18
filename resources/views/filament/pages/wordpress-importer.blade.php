<x-filament-panels::page>
    {{-- Live polling while downloading --}}
    @if($isDownloading || count($activeSlots) > 0)
        <div wire:poll.3s="pollProgress"></div>
    @endif

    <div class="space-y-6">

        {{-- ═══════════════════════════════════════════════
             Bunny Stream Connection Status
             ═══════════════════════════════════════════════ --}}
        <div class="bg-gray-900 rounded-xl shadow p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if($bunnyConnected)
                    <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                    <span class="text-sm text-green-400 font-medium">Bunny Stream Connected</span>
                @else
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <span class="text-sm text-red-400 font-medium">Bunny Stream Disconnected</span>
                    @if($bunnyError)
                        <span class="text-xs text-red-500">— {{ $bunnyError }}</span>
                    @endif
                @endif
            </div>
            <x-filament::button wire:click="testBunnyConnection" size="xs" color="gray">
                <x-heroicon-m-arrow-path class="w-3 h-3 mr-1" />
                Test
            </x-filament::button>
        </div>

        {{-- ═══════════════════════════════════════════════
             Pipeline Stats (always visible if any imported videos exist)
             ═══════════════════════════════════════════════ --}}
        @if($statPendingDownload + $statDownloading + $statDownloadFailed + $statPending + $statProcessing + $statProcessed > 0)
            <div class="bg-gray-900 rounded-xl shadow p-4">
                <h3 class="text-sm font-semibold text-gray-400 mb-3">Pipeline Status</h3>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    <div class="bg-gray-800 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-yellow-400">{{ $statPendingDownload }}</p>
                        <p class="text-xs text-gray-500">Pending DL</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-blue-400">{{ $statDownloading }}</p>
                        <p class="text-xs text-gray-500">Downloading</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-red-400">{{ $statDownloadFailed }}</p>
                        <p class="text-xs text-gray-500">DL Failed</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-orange-400">{{ $statPending }}</p>
                        <p class="text-xs text-gray-500">Pending FFmpeg</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-purple-400">{{ $statProcessing }}</p>
                        <p class="text-xs text-gray-500">Processing</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-green-400">{{ $statProcessed }}</p>
                        <p class="text-xs text-gray-500">Live</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             Step 1: Upload SQL File
             ═══════════════════════════════════════════════ --}}
        <div class="bg-gray-900 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-white mb-1">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-900/40 text-primary-400 text-sm font-bold mr-2">1</span>
                Upload WordPress SQL Dump
            </h3>
            <p class="text-sm text-gray-400 mb-4 ml-9">Upload the SQL file exported from phpMyAdmin.</p>

            @if(!$isParsed && !$isImporting && !$importComplete)
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-500 bg-primary-900/20' : 'border-gray-700'"
                    class="border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer"
                    x-on:click="$refs.fileInput.click()"
                >
                    <input type="file" accept=".sql" x-ref="fileInput" wire:model="sqlFile" class="hidden" />
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-14 h-14 rounded-full bg-gray-800 flex items-center justify-center">
                            <x-heroicon-o-arrow-up-tray class="w-7 h-7 text-gray-400" />
                        </div>
                        <div wire:loading.remove wire:target="sqlFile">
                            @if($sqlFile)
                                <p class="text-sm font-medium text-green-400">
                                    <x-heroicon-m-check-circle class="w-5 h-5 inline mr-1" />
                                    {{ $sqlFile->getClientOriginalName() }}
                                    <span class="text-gray-400">({{ number_format($sqlFile->getSize() / 1024 / 1024, 1) }} MB)</span>
                                </p>
                            @else
                                <p class="text-sm text-gray-300 font-medium">Drag & drop your SQL file here, or click to browse</p>
                                <p class="text-xs text-gray-500">Accepts .sql files</p>
                            @endif
                        </div>
                        <div wire:loading wire:target="sqlFile" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            <span class="text-sm text-gray-500">Uploading file...</span>
                        </div>
                    </div>
                </div>

                @if($sqlFile)
                    <div class="mt-4 flex justify-end">
                        <x-filament::button wire:click="parseSql" wire:loading.attr="disabled" wire:target="parseSql">
                            <span wire:loading.remove wire:target="parseSql">
                                <x-heroicon-m-magnifying-glass class="w-4 h-4 mr-1.5" />
                                Analyze SQL File
                            </span>
                            <span wire:loading wire:target="parseSql" class="flex items-center gap-2">
                                <x-filament::loading-indicator class="w-4 h-4" />
                                Parsing...
                            </span>
                        </x-filament::button>
                    </div>
                @endif
            @else
                <div class="flex items-center gap-3 p-3 bg-green-900/20 rounded-lg border border-green-800 ml-9">
                    <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                    <span class="text-sm text-green-300">SQL file loaded &mdash; {{ $totalVideos }} video posts found</span>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════
             Step 2: Analysis Results + Import Settings
             ═══════════════════════════════════════════════ --}}
        @if($isParsed && !$importComplete)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-900/40 text-primary-400 text-sm font-bold mr-2">2</span>
                    Configure & Import
                </h3>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-800 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-primary-400">{{ $totalVideos }}</p>
                        <p class="text-xs text-gray-500 mt-1">Video Posts</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-blue-400">{{ $parseStats['video_posts_with_bunny'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">With Bunny ID</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-gray-300">{{ $parseStats['terms'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 mt-1">Tags/Terms</p>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-gray-300">{{ count($parseStats['wp_authors'] ?? []) }}</p>
                        <p class="text-xs text-gray-500 mt-1">WP Authors</p>
                    </div>
                </div>

                {{-- Settings Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    {{-- WP Author Filter --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">WP Author</label>
                        <select wire:model.live="wpAuthor" class="w-full rounded-lg border-gray-700 bg-gray-800 text-white text-sm">
                            <option value="">All authors</option>
                            @foreach($this->wpAuthors as $author => $count)
                                <option value="{{ $author }}">{{ $author }} ({{ $count }} videos)</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Assign to User --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Assign to User</label>
                        <select wire:model="importUserId" class="w-full rounded-lg border-gray-700 bg-gray-800 text-white text-sm" required>
                            <option value="">-- Select user --</option>
                            @foreach($this->users as $u)
                                <option value="{{ $u['id'] }}">{{ $u['username'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Download Mode --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Download Mode</label>
                        <select wire:model="downloadMode" class="w-full rounded-lg border-gray-700 bg-gray-800 text-white text-sm">
                            <option value="light">Light (no FFmpeg, instant live)</option>
                            <option value="full">Full (FFmpeg transcoding + HLS)</option>
                        </select>
                    </div>

                    {{-- Batch Size --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Batch Size</label>
                        <select wire:model="batchSize" class="w-full rounded-lg border-gray-700 bg-gray-800 text-white text-sm">
                            <option value="10">10 (safest)</option>
                            <option value="25">25</option>
                            <option value="50">50 (recommended)</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                {{-- Preview Table --}}
                @if(!empty($previewVideos))
                    <details class="mb-6">
                        <summary class="text-sm font-medium text-gray-400 cursor-pointer hover:text-gray-300">
                            Preview first 10 videos...
                        </summary>
                        <div class="overflow-x-auto border border-gray-700 rounded-lg mt-2">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Title</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Bunny ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Duration</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Views</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Category</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach($previewVideos as $video)
                                        <tr class="bg-gray-900">
                                            <td class="px-3 py-2 text-white max-w-xs truncate">{{ Str::limit($video['title'], 50) }}</td>
                                            <td class="px-3 py-2 text-gray-400 font-mono text-xs">
                                                @if($video['bunny_video_id'])
                                                    <span class="text-green-400">{{ Str::limit($video['bunny_video_id'], 12) }}</span>
                                                @else
                                                    <span class="text-red-400">none</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-400">{{ $video['duration_formatted'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-400">{{ number_format($video['views_total']) }}</td>
                                            <td class="px-3 py-2 text-gray-400">{{ $video['category'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($totalVideos > 10)
                            <p class="text-xs text-gray-500 mt-1">...and {{ $totalVideos - 10 }} more</p>
                        @endif
                    </details>
                @endif

                {{-- Action Buttons --}}
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button
                        wire:click="runImport"
                        wire:loading.attr="disabled"
                        wire:target="runImport"
                        color="success"
                        size="lg"
                        :disabled="$isImporting"
                    >
                        <span wire:loading.remove wire:target="runImport">
                            <x-heroicon-m-arrow-down-tray class="w-5 h-5 mr-2" />
                            Import {{ $totalVideos }} Videos into DB
                        </span>
                        <span wire:loading wire:target="runImport" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            Importing...
                        </span>
                    </x-filament::button>

                    <x-filament::button
                        wire:click="purgeImported"
                        wire:confirm="This will DELETE all previously imported WP/Bunny videos. Are you sure?"
                        color="danger"
                    >
                        <x-heroicon-m-trash class="w-4 h-4 mr-1" />
                        Purge Previous
                    </x-filament::button>

                    <x-filament::button wire:click="resetImport" color="gray">
                        <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                        Start Over
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             Import Progress (during batch import)
             ═══════════════════════════════════════════════ --}}
        @if($isImporting)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Importing Records...</h3>
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-400 mb-2">
                        <span>{{ $processedVideos }} / {{ $totalVideos }}</span>
                        <span>{{ $this->getImportProgressPercent() }}%</span>
                    </div>
                    <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden">
                        <div class="bg-primary-500 h-3 rounded-full transition-all duration-300" style="width: {{ $this->getImportProgressPercent() }}%"></div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-green-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-green-400">{{ $importedCount }}</p>
                        <p class="text-xs text-green-500">Imported</p>
                    </div>
                    <div class="bg-yellow-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-yellow-400">{{ $skippedCount }}</p>
                        <p class="text-xs text-yellow-500">Skipped</p>
                    </div>
                    <div class="bg-red-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-red-400">{{ count($importErrors) }}</p>
                        <p class="text-xs text-red-500">Errors</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             Import Complete + Start Downloads
             ═══════════════════════════════════════════════ --}}
        @if($importComplete)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-full bg-green-900/30 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-m-check-circle class="w-8 h-8 text-green-500" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white">DB Import Complete</h3>
                        <p class="text-sm text-gray-400">
                            {{ $importedCount }} imported, {{ $skippedCount }} skipped, {{ count($importErrors) }} errors
                        </p>
                    </div>
                </div>

                {{-- Error Log --}}
                @if(!empty($importErrors))
                    <details class="mb-4">
                        <summary class="text-sm font-medium text-red-400 cursor-pointer">
                            <x-heroicon-m-exclamation-circle class="w-4 h-4 inline mr-1" />
                            {{ count($importErrors) }} import errors...
                        </summary>
                        <div class="max-h-48 overflow-y-auto border border-red-900 rounded-lg mt-2">
                            <table class="w-full text-sm">
                                <thead class="bg-red-900/20 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-500">WP ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-500">Title</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-500">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-900/50">
                                    @foreach($importErrors as $error)
                                        <tr class="bg-gray-900">
                                            <td class="px-3 py-2 text-gray-400 font-mono text-xs">{{ $error['wp_id'] }}</td>
                                            <td class="px-3 py-2 text-gray-300 max-w-xs truncate">{{ Str::limit($error['title'], 40) }}</td>
                                            <td class="px-3 py-2 text-red-400 text-xs">{{ $error['error'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif

                @if($statPendingDownload > 0 && !$isDownloading && !$downloadComplete)
                    <div class="bg-blue-900/20 border border-blue-800 rounded-lg p-4">
                        <p class="text-sm text-blue-300 mb-3">
                            <x-heroicon-m-arrow-down-circle class="w-5 h-5 inline mr-1" />
                            <strong>{{ $statPendingDownload }}</strong> videos are ready to download from Bunny Stream.
                        </p>
                        <x-filament::button wire:click="startDownloads" color="primary" size="lg">
                            <x-heroicon-m-play class="w-5 h-5 mr-2" />
                            Start Downloading {{ $statPendingDownload }} Videos
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             Step 3: Download Pipeline
             ═══════════════════════════════════════════════ --}}
        @if($isDownloading || $downloadComplete || $statPendingDownload > 0)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-900/40 text-primary-400 text-sm font-bold mr-2">3</span>
                    Bunny Stream Downloads
                </h3>

                {{-- Download Progress Bar --}}
                @if($isDownloading)
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-400 mb-2">
                            <span>
                                Downloaded: {{ $downloadedCount }}
                                @if($downloadFailedCount > 0)
                                    | Failed: {{ $downloadFailedCount }}
                                @endif
                                | Pending: {{ $statPendingDownload }}
                            </span>
                            <span>{{ $this->getDownloadProgressPercent() }}%</span>
                        </div>
                        <div class="w-full bg-gray-800 rounded-full h-3 overflow-hidden">
                            <div class="bg-green-500 h-3 rounded-full transition-all duration-500" style="width: {{ $this->getDownloadProgressPercent() }}%"></div>
                        </div>
                    </div>

                    {{-- Active Downloads --}}
                    @if(!empty($activeSlots))
                        <div class="mb-4 space-y-2">
                            @foreach($activeSlots as $videoId => $cacheKey)
                                <div class="flex items-center gap-3 p-2 bg-blue-900/20 rounded-lg border border-blue-800">
                                    <x-filament::loading-indicator class="w-4 h-4 text-blue-400" />
                                    <span class="text-sm text-blue-300">Downloading video #{{ $videoId }}...</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{-- Download Complete --}}
                @if($downloadComplete)
                    <div class="flex items-center gap-3 p-4 bg-green-900/20 rounded-lg border border-green-800 mb-4">
                        <x-heroicon-m-check-circle class="w-6 h-6 text-green-500" />
                        <div>
                            <p class="text-sm font-medium text-green-300">All downloads complete!</p>
                            <p class="text-xs text-green-400">{{ $downloadedCount }} downloaded, {{ $downloadFailedCount }} failed</p>
                        </div>
                    </div>
                @endif

                {{-- Controls --}}
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    @if(!$isDownloading && $statPendingDownload > 0)
                        <x-filament::button wire:click="startDownloads" color="success">
                            <x-heroicon-m-play class="w-4 h-4 mr-1" />
                            Start Downloads ({{ $statPendingDownload }})
                        </x-filament::button>
                    @endif

                    @if($isDownloading)
                        <x-filament::button wire:click="stopDownloads" color="danger">
                            <x-heroicon-m-stop class="w-4 h-4 mr-1" />
                            Stop
                        </x-filament::button>
                    @endif

                    @if($statDownloadFailed > 0)
                        <x-filament::button wire:click="retryFailed" color="warning">
                            <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                            Retry {{ $statDownloadFailed }} Failed
                        </x-filament::button>
                    @endif

                    <x-filament::button
                        wire:click="purgeImported"
                        wire:confirm="This will DELETE all imported WP/Bunny videos and their downloaded files. Are you sure?"
                        color="danger"
                        size="sm"
                    >
                        <x-heroicon-m-trash class="w-4 h-4 mr-1" />
                        Purge All
                    </x-filament::button>

                    {{-- Concurrent Slots --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <label class="text-xs text-gray-500">Concurrent:</label>
                        <select wire:model.live="maxConcurrent" class="rounded-lg border-gray-700 bg-gray-800 text-white text-xs py-1 px-2">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                </div>

                {{-- Download Queue --}}
                @if(!empty($this->downloadQueue))
                    <details class="mb-4" @if($isDownloading) open @endif>
                        <summary class="text-sm font-medium text-gray-400 cursor-pointer hover:text-gray-300">
                            Download Queue ({{ count($this->downloadQueue) }} videos)
                        </summary>
                        <div class="max-h-64 overflow-y-auto border border-gray-800 rounded-lg mt-2">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-800 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Title</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach($this->downloadQueue as $qv)
                                        <tr class="bg-gray-900">
                                            <td class="px-3 py-2 text-gray-500 font-mono text-xs">{{ $qv['id'] }}</td>
                                            <td class="px-3 py-2 text-gray-300 max-w-xs truncate">{{ Str::limit($qv['title'], 40) }}</td>
                                            <td class="px-3 py-2">
                                                @if($qv['status'] === 'downloading')
                                                    <span class="inline-flex items-center gap-1 text-xs text-blue-400">
                                                        <x-filament::loading-indicator class="w-3 h-3" /> Downloading
                                                    </span>
                                                @elseif($qv['status'] === 'download_failed')
                                                    <span class="text-xs text-red-400" title="{{ $qv['failure_reason'] ?? '' }}">Failed</span>
                                                @else
                                                    <span class="text-xs text-yellow-400">Pending</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                @if(in_array($qv['status'], ['pending_download', 'download_failed']))
                                                    <button wire:click="downloadSingle({{ $qv['id'] }})" class="text-xs text-primary-400 hover:text-primary-300">
                                                        Download
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif

                {{-- Session Log --}}
                @if(!empty($sessionLog))
                    <details open>
                        <summary class="text-sm font-medium text-gray-400 cursor-pointer hover:text-gray-300">
                            Session Log ({{ count($sessionLog) }} entries)
                        </summary>
                        <div class="max-h-64 overflow-y-auto border border-gray-800 rounded-lg mt-2">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-800 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Time</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Title</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Result</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach($sessionLog as $log)
                                        <tr class="bg-gray-900">
                                            <td class="px-3 py-2 text-gray-500 font-mono text-xs">{{ $log['time'] }}</td>
                                            <td class="px-3 py-2 text-gray-300 max-w-xs truncate">{{ Str::limit($log['title'], 40) }}</td>
                                            <td class="px-3 py-2">
                                                @if($log['status'] === 'success')
                                                    <span class="text-xs text-green-400">Downloaded</span>
                                                @else
                                                    <span class="text-xs text-red-400" title="{{ $log['error'] ?? '' }}">Failed: {{ Str::limit($log['error'] ?? 'Unknown', 50) }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             How This Works (shown when nothing is loaded)
             ═══════════════════════════════════════════════ --}}
        @if(!$isParsed && !$isImporting && !$importComplete && $statPendingDownload === 0)
            <div class="bg-gray-900 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold text-gray-400 mb-3">How This Works</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @foreach([
                        ['1', 'Upload SQL', 'Upload the WordPress database dump from phpMyAdmin'],
                        ['2', 'Analyze & Configure', 'Parser extracts video posts, Bunny IDs, tags, categories'],
                        ['3', 'Import to DB', 'Video records are created with status pending_download'],
                        ['4', 'Download from Bunny', 'Videos, thumbnails, and previews are downloaded from Bunny Stream CDN'],
                    ] as [$num, $title, $desc])
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="text-sm font-bold text-primary-400">{{ $num }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-300">{{ $title }}</p>
                                <p class="text-xs text-gray-500">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 p-3 bg-blue-900/20 rounded-lg border border-blue-800">
                    <p class="text-xs text-blue-400">
                        <x-heroicon-m-information-circle class="w-4 h-4 inline mr-1" />
                        <strong>Light mode</strong> downloads the original MP4 + Bunny thumbnail/preview and marks videos as live immediately (no FFmpeg).
                        <strong>Full mode</strong> also runs FFmpeg transcoding for multi-quality HLS streaming.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
