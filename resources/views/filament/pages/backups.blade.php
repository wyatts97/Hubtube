<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="saveBackupSettings">
            {{ $this->settingsForm }}
            <div class="flex justify-end mt-4">
                <x-filament::button type="submit" icon="phosphor-floppy-disk">
                    Save Settings
                </x-filament::button>
            </div>
        </form>

        @if ($running)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::loading-indicator class="w-5 h-5" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Processing... Please wait.</span>
                </div>
                @if ($status)
                    <pre class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg text-xs overflow-auto max-h-64">{{ $status }}</pre>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section heading="Existing Backups" icon="phosphor-archive" description="Backups stored on the local disk.">
            @if (empty($this->backups))
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="phosphor-archive" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>No backups found. Click "Create Backup" to generate one.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">File</th>
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">Size</th>
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">Date</th>
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->backups as $backup)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-3 px-4 text-sm font-mono">{{ $backup['name'] }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-400">{{ $backup['size'] }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-400">{{ $backup['modified'] }}</td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                icon="phosphor-download-simple"
                                                wire:click="downloadBackup('{{ $backup['path'] }}')"
                                            >
                                                Download
                                            </x-filament::button>
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                icon="phosphor-trash"
                                                wire:click="deleteBackup('{{ $backup['path'] }}')"
                                                wire:confirm="Are you sure you want to delete this backup?"
                                            >
                                                Delete
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
