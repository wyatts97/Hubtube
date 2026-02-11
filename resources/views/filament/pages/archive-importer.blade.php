<x-filament-panels::page>
    <div class="space-y-6" @if($shouldPoll) wire:poll.1s="importNext" @endif>

        {{-- Step 1: Configure Paths --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Step 1: Configure Paths</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Point to the archive directory containing the WordPress <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">wp-content/uploads/</code> files and the SQL dump.
            </p>

            @if(!$isScanned && !$isImporting && !$importComplete)
                <div class="grid grid-cols-1 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archive Directory Path</label>
                        <input
                            type="text"
                            wire:model="archivePath"
                            placeholder="/home/wybuntu/hubtube/WTARCHIVE"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm font-mono"
                        />
                        <p class="text-xs text-gray-400 mt-1">The directory containing YYYY/MM/ folders with video files, thumbnails, etc.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SQL File Path</label>
                        <input
                            type="text"
                            wire:model="sqlFilePath"
                            placeholder="/home/wybuntu/hubtube/wedgietu_wp_nnfpq.sql"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm font-mono"
                        />
                        <p class="text-xs text-gray-400 mt-1">Full path to the WordPress SQL dump file on this server.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <x-filament::button wire:click="scanArchive" wire:loading.attr="disabled" wire:target="scanArchive">
                        <span wire:loading.remove wire:target="scanArchive">
                            <x-heroicon-m-magnifying-glass class="w-4 h-4 mr-1.5" />
                            Scan &amp; Analyze
                        </span>
                        <span wire:loading wire:target="scanArchive" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-4 h-4" />
                            Scanning... (this may take a moment)
                        </span>
                    </x-filament::button>
                </div>
            @else
                <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                    <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                    <div class="text-sm text-green-700 dark:text-green-300">
                        <strong>Archive:</strong> <code class="text-xs">{{ $archivePath }}</code><br>
                        <strong>SQL:</strong> <code class="text-xs">{{ $sqlFilePath }}</code>
                    </div>
                </div>
            @endif
        </div>

        {{-- Step 2: Scan Results --}}
        @if($isScanned && !$importComplete)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 2: Analysis Results</h3>

                {{-- Archive Stats --}}
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Archive Directory</h4>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ $archiveStats['mp4_files'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Video Files</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ $archiveStats['image_files'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Images</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ $archiveStats['webp_files'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">WebP Previews</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ $archiveStats['gif_files'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">GIFs</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ $archiveStats['total_size_human'] ?? '0 B' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Size</p>
                    </div>
                </div>

                {{-- SQL Stats --}}
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">SQL Database</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ $parseStats['video_posts'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Video Posts</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $parseStats['local_video_posts'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Self-Hosted</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $parseStats['bunny_video_posts'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Bunny Stream</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ $parseStats['attachments'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Attachments</p>
                    </div>
                </div>

                {{-- File Matching --}}
                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">File Matching</h4>
                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 text-center border border-green-200 dark:border-green-800">
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $fileValidation['matched'] ?? 0 }}</p>
                        <p class="text-xs text-green-500 mt-0.5">Videos Found in Archive</p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 text-center border border-red-200 dark:border-red-800">
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $fileValidation['missing_video'] ?? 0 }}</p>
                        <p class="text-xs text-red-500 mt-0.5">Missing Video Files</p>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3 text-center border border-yellow-200 dark:border-yellow-800">
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $fileValidation['missing_thumb'] ?? 0 }}</p>
                        <p class="text-xs text-yellow-500 mt-0.5">Missing Thumbnails</p>
                    </div>
                </div>

                @if($alreadyImported > 0)
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 mb-6">
                        <div class="flex items-center gap-2">
                            <x-heroicon-m-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0" />
                            <span class="text-sm text-blue-700 dark:text-blue-300">
                                <strong>{{ $alreadyImported }}</strong> videos have already been imported from a previous run. Duplicates will be automatically skipped.
                            </span>
                        </div>
                    </div>
                @endif

                {{-- Preview Table --}}
                @if(!empty($previewVideos))
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Preview (first 15 videos):</h4>
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg mb-6">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">WP ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Title</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Duration</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Views</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Category</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Video</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400">Thumb</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">File Path</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($previewVideos as $video)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $video['wp_id'] }}</td>
                                        <td class="px-3 py-2 text-gray-900 dark:text-white max-w-xs truncate">{{ Str::limit($video['title'], 40) }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $video['duration_formatted'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ number_format($video['views_total']) }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $video['category'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if(!empty($video['video_found']))
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                                    <x-heroicon-m-check class="w-3 h-3 mr-0.5" /> Found
                                                </span>
                                            @elseif($video['video_rel_path'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
                                                    <x-heroicon-m-x-mark class="w-3 h-3 mr-0.5" /> Missing
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                                    N/A
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if(!empty($video['thumb_found']))
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                                    <x-heroicon-m-check class="w-3 h-3" />
                                                </span>
                                            @elseif($video['thumbnail_rel_path'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400">
                                                    <x-heroicon-m-x-mark class="w-3 h-3" />
                                                </span>
                                            @else
                                                <span class="text-gray-400 text-xs">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 font-mono text-xs max-w-[200px] truncate">
                                            {{ $video['video_rel_path'] ?? 'no local file' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Step 3: Import Settings --}}
            @if(!$isImporting)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 3: Import Settings</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign to User</label>
                            <select wire:model="importUserId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" required>
                                <option value="">-- Select a user --</option>
                                @foreach($this->users as $u)
                                    <option value="{{ $u['id'] }}">{{ $u['username'] }} ({{ trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username'] }})</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 mt-1">All imported videos will be owned by this user</p>
                        </div>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-yellow-700 dark:text-yellow-300">Before importing:</p>
                                <ul class="text-sm text-yellow-600 dark:text-yellow-400 mt-1 list-disc list-inside space-y-0.5">
                                    <li>Only videos with matching MP4 files in the archive will be imported</li>
                                    <li>Videos are imported as <strong>native videos</strong> (not embedded) — no re-encoding</li>
                                    <li>MP4 files + thumbnails are <strong>copied</strong> to HubTube's storage directory</li>
                                    <li>Duplicates are automatically skipped (safe to re-run)</li>
                                    <li>Categories, tags, view counts, and dates are preserved</li>
                                    <li>This will use ~{{ $archiveStats['total_size_human'] ?? '?' }} of additional disk space</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-filament::button
                            wire:click="startImport"
                            wire:loading.attr="disabled"
                            wire:target="startImport"
                            color="success"
                            size="lg"
                        >
                            <x-heroicon-m-arrow-down-tray class="w-5 h-5 mr-2" />
                            Import {{ $totalImportable }} Videos
                        </x-filament::button>

                        @if($alreadyImported > 0)
                            <x-filament::button
                                wire:click="fixSeekability"
                                wire:loading.attr="disabled"
                                wire:target="fixSeekability"
                                wire:confirm="This will run ffmpeg faststart on all {{ $alreadyImported }} imported videos to fix seeking. This may take a while. Continue?"
                                color="warning"
                                size="lg"
                            >
                                <span wire:loading.remove wire:target="fixSeekability">
                                    <x-heroicon-m-forward class="w-4 h-4 mr-1" />
                                    Fix Seekability ({{ $alreadyImported }})
                                </span>
                                <span wire:loading wire:target="fixSeekability" class="flex items-center gap-2">
                                    <x-filament::loading-indicator class="w-4 h-4" />
                                    Processing...
                                </span>
                            </x-filament::button>

                            <x-filament::button
                                wire:click="purgeImported"
                                wire:loading.attr="disabled"
                                wire:target="purgeImported"
                                wire:confirm="This will DELETE all {{ $alreadyImported }} previously imported archive videos and their files from storage. Are you sure?"
                                color="danger"
                                size="lg"
                            >
                                <x-heroicon-m-trash class="w-4 h-4 mr-1" />
                                Purge Previous Import ({{ $alreadyImported }})
                            </x-filament::button>
                        @endif

                        <x-filament::button wire:click="resetAll" color="gray" size="lg">
                            <x-heroicon-m-arrow-path class="w-4 h-4 mr-1" />
                            Start Over
                        </x-filament::button>
                    </div>
                </div>
            @endif
        @endif

        {{-- Import Progress --}}
        @if($isImporting)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Importing Videos...</h3>
                    <x-filament::button wire:click="stopImport" color="danger" size="sm">
                        <x-heroicon-m-stop class="w-4 h-4 mr-1" />
                        Stop
                    </x-filament::button>
                </div>

                {{-- Progress Bar --}}
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                        <span>Progress: {{ $processedCount }} / {{ $totalImportable }}</span>
                        <span>{{ $this->getProgressPercent() }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div
                            class="bg-primary-500 h-4 rounded-full transition-all duration-500"
                            style="width: {{ $this->getProgressPercent() }}%"
                        ></div>
                    </div>
                </div>

                {{-- Counters --}}
                <div class="grid grid-cols-3 gap-4 text-center mb-6">
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $importedCount }}</p>
                        <p class="text-xs text-green-500">Imported</p>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $skippedCount }}</p>
                        <p class="text-xs text-yellow-500">Skipped</p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $errorCount }}</p>
                        <p class="text-xs text-red-500">Errors</p>
                    </div>
                </div>

                {{-- Live Log --}}
                @if(!empty($importLog))
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recent Activity:</h4>
                    <div class="max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-900/50 p-3 space-y-1">
                        @foreach($importLog as $log)
                            <div class="flex items-center gap-2 text-xs">
                                @if($log['status'] === 'imported')
                                    <x-heroicon-m-check-circle class="w-3.5 h-3.5 text-green-500 flex-shrink-0" />
                                @elseif($log['status'] === 'skipped')
                                    <x-heroicon-m-minus-circle class="w-3.5 h-3.5 text-yellow-500 flex-shrink-0" />
                                @else
                                    <x-heroicon-m-exclamation-circle class="w-3.5 h-3.5 text-red-500 flex-shrink-0" />
                                @endif
                                <span class="text-gray-700 dark:text-gray-300 truncate">{{ $log['title'] }}</span>
                                <span class="text-gray-400 dark:text-gray-500 ml-auto flex-shrink-0">{{ $log['status'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
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
                        Processed {{ $processedCount }} videos from the archive.
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
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $errorCount }}</p>
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
                    <x-filament::button wire:click="resetAll" color="gray">
                        <x-heroicon-m-arrow-path class="w-4 h-4 mr-1.5" />
                        Import Another Archive
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Info Panel --}}
        @if(!$isScanned && !$isImporting && !$importComplete)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">How This Works</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">1</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Configure</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Point to the archive directory and SQL dump file on this server</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">2</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Scan &amp; Match</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SQL is parsed, video posts are matched to files in the archive directory</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">3</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Import</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Files are copied to HubTube storage and video records are created with all metadata</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-600 dark:text-primary-400">4</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Done</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Videos appear as native HubTube videos with direct MP4 playback — no re-encoding needed</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <p class="text-xs text-blue-600 dark:text-blue-400">
                        <x-heroicon-m-information-circle class="w-4 h-4 inline mr-1" />
                        <strong>For production:</strong> Upload the WTARCHIVE directory and SQL file to the server, then enter the server paths here.
                        Videos are imported as native MP4s — no video processing or encoding is performed.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
