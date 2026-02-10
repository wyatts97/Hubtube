<x-filament-panels::page>
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->failedJobs->count() }} failed {{ Str::plural('job', $this->failedJobs->count()) }}
            </p>
        </div>
        @if($this->failedJobs->count() > 0)
            <div class="flex gap-2">
                <x-filament::button color="warning" wire:click="retryAll" wire:confirm="Retry all failed jobs?">
                    Retry All
                </x-filament::button>
                <x-filament::button color="danger" wire:click="flushAll" wire:confirm="Delete ALL failed jobs? This cannot be undone.">
                    Delete All
                </x-filament::button>
            </div>
        @endif
    </div>

    @if($this->failedJobs->count() > 0)
        <div class="space-y-4">
            @foreach($this->failedJobs as $job)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Job Header --}}
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 shrink-0" />
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $job->job_class }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Queue: <span class="font-medium">{{ $job->queue }}</span>
                                    &middot; Failed: <span class="font-medium">{{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}</span>
                                    ({{ $job->failed_at }})
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::button size="sm" color="warning" wire:click="retryJob('{{ $job->uuid }}')">
                                Retry
                            </x-filament::button>
                            <x-filament::button size="sm" color="danger" wire:click="deleteJob({{ $job->id }})" wire:confirm="Delete this failed job?">
                                Delete
                            </x-filament::button>
                        </div>
                    </div>

                    {{-- Job Class --}}
                    <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Full class:</p>
                        <code class="text-xs text-gray-700 dark:text-gray-300">{{ $job->full_class }}</code>
                    </div>

                    {{-- Exception --}}
                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Error:</p>
                        <details>
                            <summary class="text-sm text-red-600 dark:text-red-400 cursor-pointer hover:underline">
                                {{ Str::limit(Str::before($job->exception, "\n"), 150) }}
                            </summary>
                            <pre class="mt-2 p-3 rounded-lg bg-gray-900 text-green-400 text-xs font-mono overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap">{{ $job->exception }}</pre>
                        </details>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12 text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-xl">
            <x-heroicon-o-check-circle class="w-12 h-12 mx-auto mb-3 text-green-500" />
            <p class="text-lg font-medium text-gray-900 dark:text-white">No failed jobs</p>
            <p class="text-sm mt-1">All queue jobs are running smoothly.</p>
        </div>
    @endif
</x-filament-panels::page>
