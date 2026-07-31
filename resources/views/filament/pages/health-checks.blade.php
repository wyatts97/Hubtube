<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $statusColors = [
                'ok' => 'success',
                'warning' => 'warning',
                'failed' => 'danger',
                'crashed' => 'danger',
                'error' => 'danger',
                'unknown' => 'gray',
            ];
            $statusIcons = [
                'ok' => 'phosphor-check-circle',
                'warning' => 'phosphor-warning',
                'failed' => 'phosphor-x-circle',
                'crashed' => 'phosphor-x-circle',
                'error' => 'phosphor-x-circle',
                'unknown' => 'phosphor-question',
            ];
        @endphp

        @if ($running)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::loading-indicator class="w-5 h-5" />
                    <span class="text-sm text-gray-600 dark:text-gray-400">Running health checks...</span>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="System Health" icon="phosphor-heartbeat" description="Monitor the health of database, Redis, Horizon, cache, disk space, and scheduled tasks.">
            @if (empty($checkResults))
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="phosphor-heartbeat" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>No health check results yet. Click "Run Health Checks" to start.</p>
                </div>
            @else
                <div class="mb-4">
                    <x-filament::badge color="{{ $statusColors[$overallStatus] ?? 'gray' }}" size="md">
                        <x-filament::icon icon="{{ $statusIcons[$overallStatus] ?? 'phosphor-question' }}" class="w-4 h-4 mr-1" />
                        {{ ucfirst($overallStatus) }}
                    </x-filament::badge>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">Check</th>
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">Message</th>
                                <th class="py-3 px-4 text-sm font-medium text-gray-500 dark:text-gray-400">Last Checked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkResults as $result)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-3 px-4 text-sm font-medium">{{ $result['label'] }}</td>
                                    <td class="py-3 px-4">
                                        <x-filament::badge color="{{ $statusColors[$result['status']] ?? 'gray' }}" size="sm">
                                            <x-filament::icon icon="{{ $statusIcons[$result['status']] ?? 'phosphor-question' }}" class="w-3 h-3 mr-1" />
                                            {{ ucfirst($result['status']) }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600 dark:text-gray-400">{{ $result['message'] }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-400">{{ $result['last_checked'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
