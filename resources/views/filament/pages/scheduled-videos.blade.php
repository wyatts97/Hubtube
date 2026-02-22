<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Header Actions --}}
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button
                wire:click="openTemplateForm"
                icon="heroicon-o-plus"
                size="sm"
            >
                New Template
            </x-filament::button>

            <x-filament::button
                wire:click="shuffleScheduled"
                icon="heroicon-o-arrow-path"
                color="warning"
                size="sm"
            >
                Shuffle Order
            </x-filament::button>

            @foreach ($this->templates as $template)
                @if ($template->is_active)
                    <x-filament::button
                        wire:click="applyTemplate({{ $template->id }})"
                        icon="heroicon-o-calendar-days"
                        color="gray"
                        size="sm"
                    >
                        Apply: {{ $template->name }}
                    </x-filament::button>
                @endif
            @endforeach
        </div>

        {{-- Schedule Templates Section --}}
        <x-filament::section collapsible collapsed heading="Schedule Templates" icon="heroicon-o-calendar-days">
            @if ($this->templates->isEmpty())
                <div class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                    No schedule templates yet. Create one to auto-assign publish times to your videos.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($this->templates as $template)
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $template->name }}</h3>
                                    @if ($template->is_active)
                                        <x-filament::badge color="success" size="sm">Active</x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray" size="sm">Inactive</x-filament::badge>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-filament::icon-button
                                        wire:click="openTemplateForm({{ $template->id }})"
                                        icon="heroicon-o-pencil-square"
                                        color="gray"
                                        size="sm"
                                        tooltip="Edit"
                                    />
                                    <x-filament::icon-button
                                        wire:click="deleteTemplate({{ $template->id }})"
                                        wire:confirm="Delete this template?"
                                        icon="heroicon-o-trash"
                                        color="danger"
                                        size="sm"
                                        tooltip="Delete"
                                    />
                                </div>
                            </div>
                            <div class="space-y-1">
                                @foreach ($template->slots ?? [] as $slot)
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
                                        <span class="capitalize">{{ $slot['day'] }}</span>
                                        <span>at</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($slot['time'])->format('g:i A') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Template Form Modal --}}
        @if ($showTemplateForm)
            <x-filament::section heading="{{ $editingTemplateId ? 'Edit Template' : 'Create Template' }}">
                <form wire:submit="saveTemplate" class="space-y-4">
                    {{ $this->templateForm }}
                    <div class="flex items-center gap-3 pt-2">
                        <x-filament::button type="submit" icon="heroicon-o-check">
                            {{ $editingTemplateId ? 'Update Template' : 'Create Template' }}
                        </x-filament::button>
                        <x-filament::button wire:click="closeTemplateForm" color="gray">
                            Cancel
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        {{-- Scheduled Videos Queue --}}
        <x-filament::section heading="Scheduled Queue" icon="heroicon-o-queue-list"
            description="Videos waiting to be published at their scheduled time. The scheduler runs every minute.">

            @if ($this->scheduledVideos->isEmpty())
                <div class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">
                    <x-heroicon-o-clock class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
                    <p class="font-medium">No scheduled videos</p>
                    <p class="mt-1">Upload videos and apply a schedule template, or schedule them individually from the Videos resource.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Video</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Uploader</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Category</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Scheduled For</th>
                                <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->scheduledVideos as $video)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50" wire:key="sched-{{ $video->id }}">
                                    <td class="py-3 px-3">
                                        <div class="flex items-center gap-3">
                                            @if ($video->thumbnail_url)
                                                <img src="{{ $video->thumbnail_url }}" alt="" class="w-16 h-9 rounded object-cover shrink-0">
                                            @else
                                                <div class="w-16 h-9 rounded bg-gray-200 dark:bg-gray-700 shrink-0 flex items-center justify-center">
                                                    <x-heroicon-o-video-camera class="w-4 h-4 text-gray-400" />
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="font-medium text-gray-900 dark:text-white truncate max-w-[200px]">{{ $video->title }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $video->formatted_duration ?: '—' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                                        {{ $video->user?->username ?? '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                                        {{ $video->category?->name ?? '—' }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $video->scheduled_at->format('M j, Y') }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $video->scheduled_at->format('g:i A') }} ({{ $video->scheduled_at->diffForHumans() }})</p>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3">
                                        @if ($video->status === 'processed')
                                            <x-filament::badge color="success" size="sm">Ready</x-filament::badge>
                                        @elseif ($video->status === 'processing')
                                            <x-filament::badge color="info" size="sm">Processing</x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning" size="sm">{{ ucfirst($video->status) }}</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-filament::icon-button
                                                wire:click="publishNow({{ $video->id }})"
                                                wire:confirm="Publish this video immediately?"
                                                icon="heroicon-o-rocket-launch"
                                                color="success"
                                                size="sm"
                                                tooltip="Publish Now"
                                            />
                                            <x-filament::icon-button
                                                wire:click="unscheduleVideo({{ $video->id }})"
                                                icon="heroicon-o-x-circle"
                                                color="danger"
                                                size="sm"
                                                tooltip="Unschedule"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Recently Published --}}
        @if ($this->publishedScheduled->isNotEmpty())
            <x-filament::section collapsible collapsed heading="Recently Published" icon="heroicon-o-check-circle">
                <div class="space-y-2">
                    @foreach ($this->publishedScheduled as $video)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <div class="flex items-center gap-3">
                                @if ($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="" class="w-12 h-7 rounded object-cover">
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $video->title }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Published {{ $video->published_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <x-filament::badge color="success" size="sm">Published</x-filament::badge>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
