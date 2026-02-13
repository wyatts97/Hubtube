<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Current Stats --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-800 rounded-xl shadow p-4 text-center">
                <p class="text-2xl font-bold text-primary-400">{{ $this->totalHubtubeUsers }}</p>
                <p class="text-xs text-gray-400 mt-1">Total HubTube Users</p>
            </div>
            <div class="bg-gray-800 rounded-xl shadow p-4 text-center">
                <p class="text-2xl font-bold text-yellow-400">{{ $this->previouslyImportedCount }}</p>
                <p class="text-xs text-gray-400 mt-1">Previously Imported from WP</p>
            </div>
        </div>

        {{-- Step 1: Upload SQL File --}}
        <div class="bg-gray-800 rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-white mb-1">Step 1: Upload WordPress Users SQL Dump</h3>
            <p class="text-sm text-gray-400 mb-4">
                Upload the <code class="text-xs bg-gray-700 px-1.5 py-0.5 rounded text-gray-300">MKdOzH8c_users.sql</code> file exported from phpMyAdmin.
            </p>

            @if(!$isParsed && !$isImporting && !$importComplete)
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-500 bg-primary-900/20' : 'border-gray-600'"
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
                        <div class="w-14 h-14 rounded-full bg-gray-700 flex items-center justify-center">
                            <x-heroicon-o-arrow-up-tray class="w-7 h-7 text-gray-400" />
                        </div>

                        <div wire:loading.remove wire:target="sqlFile">
                            @if($sqlFile)
                                <p class="text-sm font-medium text-green-400">
                                    <x-heroicon-m-check-circle class="w-5 h-5 inline mr-1" />
                                    {{ $sqlFile->getClientOriginalName() }}
                                    <span class="text-gray-400">({{ number_format($sqlFile->getSize() / 1024, 1) }} KB)</span>
                                </p>
                            @else
                                <p class="text-sm text-gray-300 font-medium">
                                    Drag & drop your SQL file here, or click to browse
                                </p>
                                <p class="text-xs text-gray-500">Accepts .sql files</p>
                            @endif
                        </div>

                        <div wire:loading wire:target="sqlFile" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            <span class="text-sm text-gray-400">Uploading file...</span>
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
                <div class="flex items-center gap-3 p-3 bg-green-900/20 rounded-lg border border-green-800">
                    <x-heroicon-m-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                    <span class="text-sm text-green-300">
                        SQL file loaded: <strong>{{ $sqlFile?->getClientOriginalName() ?? 'uploaded file' }}</strong>
                    </span>
                </div>
            @endif
        </div>

        {{-- Step 2: Parse Results --}}
        @if($isParsed && !$importComplete)
            <div class="bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Step 2: Analysis Results</h3>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-primary-400">{{ $totalUsers }}</p>
                        <p class="text-xs text-gray-400 mt-1">Total Users</p>
                    </div>
                    <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-2xl font-bold text-gray-300">{{ number_format($parseStats['with_email'] ?? 0) }}</p>
                        <p class="text-xs text-gray-400 mt-1">With Email</p>
                    </div>
                    <div class="bg-gray-700/50 rounded-lg p-4 text-center">
                        <p class="text-sm font-medium text-gray-300">
                            {{ $parseStats['date_range']['earliest'] ?? 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">Earliest Registration</p>
                    </div>
                </div>

                {{-- Preview Table --}}
                @if(!empty($previewUsers))
                    <h4 class="text-sm font-medium text-gray-300 mb-2">Preview (first 20 users):</h4>
                    <div class="overflow-x-auto border border-gray-700 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">WP ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Login</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Display Name</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Email</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Registered</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-400">Pass Hash</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @foreach($previewUsers as $user)
                                    <tr class="hover:bg-gray-700/30">
                                        <td class="px-3 py-2 text-gray-400 font-mono text-xs">{{ $user['ID'] }}</td>
                                        <td class="px-3 py-2 text-white font-medium">{{ $user['user_login'] }}</td>
                                        <td class="px-3 py-2 text-gray-300">{{ $user['display_name'] ?: '-' }}</td>
                                        <td class="px-3 py-2 text-gray-400 text-xs">{{ $user['user_email'] }}</td>
                                        <td class="px-3 py-2 text-gray-400 text-xs">{{ $user['user_registered'] }}</td>
                                        <td class="px-3 py-2 text-gray-500 font-mono text-xs">
                                            {{ Str::limit($user['user_pass'], 20) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($totalUsers > 20)
                        <p class="text-xs text-gray-500 mt-2">...and {{ $totalUsers - 20 }} more users</p>
                    @endif
                @endif
            </div>

            {{-- Step 3: Import Settings --}}
            <div class="bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Step 3: Import Settings</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Batch Size</label>
                        <select wire:model="batchSize" class="w-full rounded-lg border-gray-600 bg-gray-700 text-white text-sm">
                            <option value="10">10 users per batch (safest)</option>
                            <option value="25">25 users per batch (recommended)</option>
                            <option value="50">50 users per batch</option>
                            <option value="100">100 users per batch (fastest)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Each batch runs in a separate request to avoid timeouts</p>
                    </div>
                </div>

                <div class="bg-yellow-900/20 border border-yellow-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-yellow-300">Before importing:</p>
                            <ul class="text-sm text-yellow-400 mt-1 list-disc list-inside space-y-0.5">
                                <li>Users with duplicate emails are automatically skipped (safe to re-run)</li>
                                <li>Usernames are sanitized to match HubTube rules (alphanumeric + underscore, 5-32 chars)</li>
                                <li><strong>WordPress passwords are preserved</strong> — users can log in with their existing WP password (auto-upgraded to Laravel bcrypt on first login)</li>
                                <li>A channel is automatically created for each imported user</li>
                                <li>Original registration dates are preserved</li>
                                <li>Imported users are tagged with <code class="bg-yellow-800 px-1 rounded text-xs">wp_imported</code> in settings for easy identification/purge</li>
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
                            <x-heroicon-m-user-plus class="w-5 h-5 mr-2" />
                            Import {{ $totalUsers }} Users
                        </span>
                        <span wire:loading wire:target="runImport" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-5 h-5" />
                            Importing...
                        </span>
                    </x-filament::button>

                    <x-filament::button
                        wire:click="purgeImported"
                        wire:loading.attr="disabled"
                        wire:target="purgeImported"
                        wire:confirm="This will DELETE all previously imported WP users and their channels from the database. Are you sure?"
                        color="danger"
                        size="lg"
                    >
                        <span wire:loading.remove wire:target="purgeImported">
                            <x-heroicon-m-trash class="w-4 h-4 mr-1" />
                            Purge Previous Import
                        </span>
                        <span wire:loading wire:target="purgeImported" class="flex items-center gap-2">
                            <x-filament::loading-indicator class="w-4 h-4" />
                            Purging...
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
            <div class="bg-gray-800 rounded-xl shadow p-6" wire:poll.1s="importNextBatch">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-white">Importing Users...</h3>
                    <x-filament::button wire:click="stopImport" color="danger" size="sm">
                        <x-heroicon-m-stop class="w-4 h-4 mr-1" />
                        Stop Import
                    </x-filament::button>
                </div>

                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-400 mb-2">
                        <span>Progress: {{ $processedUsers }} / {{ $totalUsers }} (Batch {{ $currentBatchIndex }} / {{ $totalBatches }})</span>
                        <span>{{ $this->getProgressPercent() }}%</span>
                    </div>
                    <div class="w-full bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div
                            class="bg-primary-500 h-4 rounded-full transition-all duration-300"
                            style="width: {{ $this->getProgressPercent() }}%"
                        ></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-green-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-green-400">{{ $importedCount }}</p>
                        <p class="text-xs text-green-500">Imported</p>
                    </div>
                    <div class="bg-yellow-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-yellow-400">{{ $skippedCount }}</p>
                        <p class="text-xs text-yellow-500">Skipped (duplicates)</p>
                    </div>
                    <div class="bg-red-900/20 rounded-lg p-3">
                        <p class="text-xl font-bold text-red-400">{{ count($importErrors) }}</p>
                        <p class="text-xs text-red-500">Errors</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Import Complete --}}
        @if($importComplete)
            <div class="bg-gray-800 rounded-xl shadow p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 rounded-full bg-green-900/30 flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-m-check-circle class="w-10 h-10 text-green-500" />
                    </div>
                    <h3 class="text-xl font-bold text-white">User Import Complete!</h3>
                    <p class="text-sm text-gray-400 mt-1">
                        All {{ $totalUsers }} users have been processed.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center mb-6">
                    <div class="bg-green-900/20 rounded-lg p-4">
                        <p class="text-3xl font-bold text-green-400">{{ $importedCount }}</p>
                        <p class="text-sm text-green-500">Imported</p>
                    </div>
                    <div class="bg-yellow-900/20 rounded-lg p-4">
                        <p class="text-3xl font-bold text-yellow-400">{{ $skippedCount }}</p>
                        <p class="text-sm text-yellow-500">Skipped</p>
                    </div>
                    <div class="bg-red-900/20 rounded-lg p-4">
                        <p class="text-3xl font-bold text-red-400">{{ count($importErrors) }}</p>
                        <p class="text-sm text-red-500">Errors</p>
                    </div>
                </div>

                <div class="bg-blue-900/20 border border-blue-800 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <x-heroicon-m-information-circle class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-300">
                            <p class="font-medium mb-1">Important Notes:</p>
                            <ul class="list-disc list-inside space-y-0.5 text-blue-400">
                                <li>Users can log in with their <strong>existing WordPress password</strong> — it will be auto-upgraded to native Laravel bcrypt on first login</li>
                                <li>Original WP registration dates have been preserved</li>
                                <li>Each user has a channel created automatically</li>
                                <li>Users are marked with <code class="bg-blue-800 px-1 rounded text-xs">wp_imported: true</code> in their settings JSON</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Error Log --}}
                @if(!empty($importErrors))
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-red-400 mb-2">
                            <x-heroicon-m-exclamation-circle class="w-4 h-4 inline mr-1" />
                            Error Log ({{ count($importErrors) }} errors):
                        </h4>
                        <div class="max-h-64 overflow-y-auto border border-red-800 rounded-lg">
                            <table class="w-full text-sm">
                                <thead class="bg-red-900/20 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-400">WP ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-400">Login</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-400">Email</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-red-400">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-900">
                                    @foreach($importErrors as $error)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-400 font-mono text-xs">{{ $error['wp_id'] }}</td>
                                            <td class="px-3 py-2 text-gray-300">{{ $error['login'] }}</td>
                                            <td class="px-3 py-2 text-gray-400 text-xs">{{ $error['email'] }}</td>
                                            <td class="px-3 py-2 text-red-400 text-xs">{{ $error['error'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-4 justify-center">
                    <x-filament::button wire:click="resetImport" color="gray">
                        <x-heroicon-m-arrow-path class="w-4 h-4 mr-1.5" />
                        Import Another File
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Info Panel --}}
        @if(!$isParsed && !$isImporting && !$importComplete)
            <div class="bg-gray-800 rounded-xl shadow p-6">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">How This Works</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-400">1</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-300">Upload SQL</p>
                            <p class="text-xs text-gray-500">Upload the <code class="bg-gray-700 px-1 rounded">MKdOzH8c_users</code> table SQL dump from phpMyAdmin</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-400">2</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-300">Analyze</p>
                            <p class="text-xs text-gray-500">Parser extracts usernames, emails, display names, and registration dates</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-primary-400">3</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-300">Import</p>
                            <p class="text-xs text-gray-500">Users are created in HubTube with sanitized usernames, channels, and preserved registration dates</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-green-900/20 rounded-lg border border-green-800">
                    <p class="text-xs text-green-400">
                        <x-heroicon-m-check-circle class="w-4 h-4 inline mr-1" />
                        <strong>Password Preservation:</strong> WordPress password hashes (both phpass <code class="bg-green-800 px-1 rounded">$P$B...</code> and bcrypt <code class="bg-green-800 px-1 rounded">$wp$2y$...</code>)
                        are stored directly. Users can log in with their existing WP password — it is automatically upgraded to native Laravel bcrypt on first successful login.
                    </p>
                </div>

                <div class="mt-3 p-3 bg-gray-700/50 rounded-lg border border-gray-600">
                    <p class="text-xs text-gray-400">
                        <x-heroicon-m-table-cells class="w-4 h-4 inline mr-1" />
                        <strong>Expected columns:</strong>
                        <code class="bg-gray-700 px-1 rounded">ID</code>,
                        <code class="bg-gray-700 px-1 rounded">user_login</code>,
                        <code class="bg-gray-700 px-1 rounded">user_pass</code>,
                        <code class="bg-gray-700 px-1 rounded">user_nicename</code>,
                        <code class="bg-gray-700 px-1 rounded">user_email</code>,
                        <code class="bg-gray-700 px-1 rounded">user_url</code>,
                        <code class="bg-gray-700 px-1 rounded">user_registered</code>,
                        <code class="bg-gray-700 px-1 rounded">user_activation_key</code>,
                        <code class="bg-gray-700 px-1 rounded">user_status</code>,
                        <code class="bg-gray-700 px-1 rounded">display_name</code>
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
