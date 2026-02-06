<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Step 1: Upload SQL File --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Step 1: Upload WordPress SQL Dump</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Upload the <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">wedgietu_wp_nnfpq.sql</code> file exported from phpMyAdmin.
            </p>

            @if(!$isParsed && !$isImporting && !$importComplete)
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'"
                    class="border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer"
                    x-on:click="$refs.fileInput.click()"
                >
                    <input
                        type="file"
                        accept=".sql"
                        x-ref="fileInput"
                        wire:model="sqlFile"
                        class="hidden"
                    />

                    <div class="flex flex-col items-center gap-3">
                        <div class="w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <x-heroicon-o-arrow-up-tray class="w-7 h-7 text-gray-400" />
                        </div>

                        <div wire:loading.remove wire:target="sqlFile">
                            @if($sqlFile)
                                <p class="text-sm font-medium text-green-600 dark:text-green-400">
                                    <x-heroicon-m-check-circle class="w-5 h-5 inline mr-1" />
                                    {{ $sqlFile->getClientOriginalName() }}
                                    <span class="text-gray-400">({{ number_format($sqlFile->getSize() / 1024 / 1024, 1) }} MB)</span>
                                </p>
                            @else
                                <p class="text-sm text-gray-600 dark:text-gray-300 font-medium">
                                    Drag & drop your SQL file here, or click to browse
                                </p>
                                <p class="text-xs text-gray-400">Accepts .sql files</p>
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
                                Parsing... (this may take a moment)
                            </span>
                        </x-filament::button>
                    </div>
                @endif
            @else
                <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                    <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                    <span class="text-sm text-green-700 dark:text-green-300">
                        SQL file loaded: <strong>{{ $sqlFile?->getClientOriginalName() ?? 'uploaded file' }}</strong>
                    </span>
                </div>
            @endif
        </div>

        {{-- Step 2: Parse Results --}}
        @if($isParsed && !$importComplete)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 2: Analysis Results</h3>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $totalVideos }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Video Posts</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ number_format($parseStats['postmeta_entries'] ?? 0) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Meta Entries</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $parseStats['terms'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tags/Terms</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $parseStats['term_relationships'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Relationships</p>
                    </div>
                </div>

                {{-- Preview Table --}}
                @if(!empty($previewVideos))
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Preview (first 10 videos):</h4>
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">WP ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Title</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Bunny ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Duration</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Views</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Category</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tags</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($previewVideos as $video)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $video['wp_id'] }}</td>
                                        <td class="px-3 py-2 text-gray-900 dark:text-white max-w-xs truncate">{{ Str::limit($video['title'], 50) }}</td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 font-mono text-xs">
                                            @if($video['bunny_video_id'])
                                                {{ Str::limit($video['bunny_video_id'], 12) }}
                                            @else
                                                <span class="text-yellow-500">none</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $video['duration_formatted'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ number_format($video['views_total']) }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $video['category'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs">
                                            @if(!empty($video['tags']))
                                                {{ Str::limit(implode(', ', $video['tags']), 40) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($totalVideos > 10)
                        <p class="text-xs text-gray-400 mt-2">...and {{ $totalVideos - 10 }} more videos</p>
                    @endif
                @endif
            </div>

            {{-- Step 3: Import Settings --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 3: Import Settings</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Batch Size</label>
                        <select wire:model="batchSize" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                            <option value="10">10 videos per batch (slowest, safest)</option>
                            <option value="25">25 videos per batch</option>
                            <option value="50">50 videos per batch (recommended)</option>
                            <option value="100">100 videos per batch</option>
                            <option value="200">200 videos per batch (fastest)</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Smaller batches = less memory usage, more granular progress</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delay Between Batches</label>
                        <select wire:model="delayMs" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                            <option value="0">No delay (fastest)</option>
                            <option value="100">100ms (recommended)</option>
                            <option value="250">250ms</option>
                            <option value="500">500ms</option>
                            <option value="1000">1 second (gentlest on DB)</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Add delay to reduce database load during import</p>
                    </div>
                </div>

                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-yellow-700 dark:text-yellow-300">Before importing:</p>
                            <ul class="text-sm text-yellow-600 dark:text-yellow-400 mt-1 list-disc list-inside space-y-0.5">
                                <li>Duplicates are automatically skipped (safe to re-run)</li>
                                <li>Videos import into the <strong>Embedded Videos</strong> section</li>
                                <li>Categories will be auto-created if they don't exist</li>
                                <li>Bunny Stream embed codes and thumbnails are preserved</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
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
                            Import {{ $totalVideos }} Videos
                        </span>
                        <span wire:loading wire:target="runImport" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            Importing...
                        </span>
                    </x-filament::button>

                    <x-filament::button wire:click="resetImport" color="gray" size="lg">
                        <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                        Start Over
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Progress Bar (during import) --}}
        @if($isImporting)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Importing...</h3>

                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                        <span>Progress: {{ $processedVideos }} / {{ $totalVideos }}</span>
                        <span>{{ $this->getProgressPercent() }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div
                            class="bg-primary-500 h-4 rounded-full transition-all duration-300"
                            style="width: {{ $this->getProgressPercent() }}%"
                        ></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $importedCount }}</p>
                        <p class="text-xs text-green-500">Imported</p>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $skippedCount }}</p>
                        <p class="text-xs text-yellow-500">Skipped (duplicates)</p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ count($importErrors) }}</p>
                        <p class="text-xs text-red-500">Errors</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Import Complete --}}
        @if($importComplete)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-m-check-circle class="w-10 h-10 text-green-500" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Import Complete!</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        All {{ $totalVideos }} videos have been processed.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center mb-6">
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $importedCount }}</p>
                        <p class="text-sm text-green-500">Imported</p>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                        <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $skippedCount }}</p>
                        <p class="text-sm text-yellow-500">Skipped</p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ count($importErrors) }}</p>
                        <p class="text-sm text-red-500">Errors</p>
                    </div>
                </div>

                {{-- Error Log --}}
                @if(!empty($importErrors))
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-red-600 dark:text-red-400 mb-2">
                            <x-heroicon-m-exclamation-circle class="w-4 h-4 inline mr-1" />
                            Error Log ({{ count($importErrors) }} errors):
                        </h4>
                        <div class="max-h-64 overflow-y-auto border border-red-200 dark:border-red-800 rounded-lg">
                            <table class="w-full text-sm">
                                <thead class="bg-red-50 dark:bg-red-900/20 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-500">WP ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-500">Title</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-500">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-100 dark:divide-red-900">
                                    @foreach($importErrors as $error)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $error['wp_id'] }}</td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300 max-w-xs truncate">{{ Str::limit($error['title'], 40) }}</td>
                                            <td class="px-3 py-2 text-red-600 dark:text-red-400 text-xs">{{ $error['error'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-4 justify-center">
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.admin.resources.embedded-videos.index') }}"
                        color="primary"
                    >
                        <x-heroicon-m-film class="w-4 h-4 mr-1.5" />
                        View Embedded Videos
                    </x-filament::button>

                    <x-filament::button wire:click="resetImport" color="gray">
                        <x-heroicon-m-arrow-path class="w-4 h-4 mr-1.5" />
                        Import Another File
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Info Panel --}}
        @if(!$isParsed && !$isImporting && !$importComplete)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">How This Works</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">1</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload SQL</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Upload the WordPress database dump exported from phpMyAdmin</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">2</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Analyze</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Parser extracts vidmov_video posts, Bunny Stream embeds, tags, categories, and view counts</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">3</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Import</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Videos are imported into HubTube's embedded_videos table with all metadata preserved</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="text-xs text-blue-600 dark:text-blue-400">
                        <x-heroicon-m-information-circle class="w-4 h-4 inline mr-1" />
                        <strong>Tailored for WedgieTube:</strong> This importer is specifically built to parse the <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">MKdOzH8c_</code> prefixed tables,
                        extract <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">vidmov_video</code> posts, and map Bunny Stream Library <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">250371</code> embed codes.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
