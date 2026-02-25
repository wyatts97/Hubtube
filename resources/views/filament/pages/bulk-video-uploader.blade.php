<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Upload Zone --}}
        @if (!$isCreating)
            <x-filament::section heading="Upload Videos" icon="heroicon-o-arrow-up-tray"
                description="Select multiple video files to upload. After uploading, fill in the details for each video below.">
                <form wire:submit="addUploadedFiles" class="space-y-4">
                    {{ $this->uploadForm }}
                    <x-filament::button type="submit" icon="heroicon-o-plus-circle">
                        Add to Queue
                    </x-filament::button>
                </form>
            </x-filament::section>

            {{-- Apply to All Bar --}}
            @if (count($entries) > 0)
                <x-filament::section heading="Apply to All" icon="heroicon-o-adjustments-horizontal" collapsible>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                            <select wire:model="bulkCategoryId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                <option value="">— None —</option>
                                @foreach ($this->categories as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign to User</label>
                            <select wire:model="bulkUserId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                @foreach ($this->users as $id => $username)
                                    <option value="{{ $id }}">{{ $username }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Add to Queue</label>
                            <label class="flex items-center gap-2 mt-2">
                                <input type="checkbox" wire:model="addToQueue" class="rounded border-gray-300 dark:border-gray-600">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Yes (Schedule automatically)</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Age Restricted</label>
                            <label class="flex items-center gap-2 mt-2">
                                <input type="checkbox" wire:model="bulkAgeRestricted" class="rounded border-gray-300 dark:border-gray-600">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Yes</span>
                            </label>
                        </div>
                        <div class="flex items-end">
                            <x-filament::button wire:click="applyBulkSettings" icon="heroicon-o-check" color="gray" size="sm">
                                Apply to All
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endif

            {{-- Video Entries --}}
            @if (count($entries) > 0)
                <x-filament::section heading="Video Queue ({{ count($entries) }})" icon="heroicon-o-queue-list">
                    <div class="space-y-4">
                        @foreach ($entries as $index => $entry)
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4" wire:key="entry-{{ $index }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 space-y-3">
                                        {{-- File info header --}}
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-900/20">
                                                <x-heroicon-o-video-camera class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $entry['file_name'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $entry['file_size'] ? number_format($entry['file_size'] / 1048576, 1) . ' MB' : '—' }}
                                                </p>
                                            </div>
                                        </div>

                                        {{-- Metadata fields --}}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Title *</label>
                                                <input
                                                    type="text"
                                                    wire:model.blur="entries.{{ $index }}.title"
                                                    placeholder="Enter video title..."
                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
                                                />
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                                                <textarea
                                                    wire:model.blur="entries.{{ $index }}.description"
                                                    rows="2"
                                                    placeholder="Optional description..."
                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
                                                ></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Category</label>
                                                <select
                                                    wire:model="entries.{{ $index }}.category_id"
                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
                                                >
                                                    <option value="">— None —</option>
                                                    @foreach ($this->categories as $id => $name)
                                                        <option value="{{ $id }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Assign to User</label>
                                                <select
                                                    wire:model="entries.{{ $index }}.user_id"
                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
                                                >
                                                    @foreach ($this->users as $id => $username)
                                                        <option value="{{ $id }}">{{ $username }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Remove button --}}
                                    <x-filament::icon-button
                                        wire:click="removeEntry({{ $index }})"
                                        icon="heroicon-o-x-mark"
                                        color="danger"
                                        size="sm"
                                        tooltip="Remove"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 mt-4">
                        <x-filament::button
                            wire:click="createAllVideos"
                            wire:confirm="Create {{ count($entries) }} video(s)? They will be queued for processing."
                            icon="heroicon-o-rocket-launch"
                            size="lg"
                        >
                            Create {{ count($entries) }} Video(s)
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        @endif

        {{-- Processing Status --}}
        @if (!empty($createdVideoIds))
            <x-filament::section heading="Processing Status" icon="heroicon-o-cpu-chip"
                description="Videos are being processed. Thumbnails will appear once processing completes.">
                <div wire:poll.5s class="space-y-3">
                    @foreach ($this->createdVideos as $video)
                        <div class="flex items-center justify-between py-3 px-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900" wire:key="proc-{{ $video->id }}">
                            <div class="flex items-center gap-3">
                                @if ($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="" class="w-16 h-9 rounded object-cover shrink-0">
                                @else
                                    <div class="w-16 h-9 rounded bg-gray-200 dark:bg-gray-700 shrink-0 flex items-center justify-center">
                                        <x-heroicon-o-video-camera class="w-4 h-4 text-gray-400" />
                                    </div>
                                @endif
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $video->title }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $video->user?->username ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($video->status === 'processed')
                                    <x-filament::badge color="success">Processed</x-filament::badge>
                                @elseif ($video->status === 'processing')
                                    <x-filament::badge color="info">
                                        <span class="flex items-center gap-1">
                                            <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            Processing
                                        </span>
                                    </x-filament::badge>
                                @elseif ($video->status === 'failed')
                                    <x-filament::badge color="danger">Failed</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">{{ ucfirst($video->status) }}</x-filament::badge>
                                @endif

                                {{-- Thumbnail chooser (visible after processing) --}}
                                @if ($video->status === 'processed')
                                    @php
                                        $slugTitle = \Illuminate\Support\Str::slug($video->title, '_');
                                        $thumbCount = (int) \App\Models\Setting::get('thumbnail_count', 4);
                                    @endphp
                                    <div class="flex items-center gap-1">
                                        @for ($i = 0; $i < $thumbCount; $i++)
                                            @php
                                                $thumbPath = "videos/{$video->slug}/{$slugTitle}_thumb_{$i}.jpg";
                                                $thumbExists = \Illuminate\Support\Facades\Storage::disk($video->storage_disk ?? 'public')->exists($thumbPath);
                                            @endphp
                                            @if ($thumbExists)
                                                <button
                                                    wire:click="selectThumbnail({{ $video->id }}, {{ $i }})"
                                                    class="rounded border-2 overflow-hidden transition {{ $video->thumbnail === $thumbPath ? 'border-primary-500 ring-2 ring-primary-300' : 'border-gray-300 dark:border-gray-600 hover:border-primary-400' }}"
                                                    title="Select thumbnail {{ $i + 1 }}"
                                                >
                                                    <img
                                                        src="{{ \Illuminate\Support\Facades\Storage::disk($video->storage_disk ?? 'public')->url($thumbPath) }}"
                                                        alt="Thumb {{ $i + 1 }}"
                                                        class="w-12 h-7 object-cover"
                                                    >
                                                </button>
                                            @endif
                                        @endfor
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($this->createdVideos->every(fn ($v) => $v->status === 'processed' || $v->status === 'failed'))
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700 mt-4">
                        <x-filament::button
                            wire:click="$set('createdVideoIds', []); $set('isCreating', false)"
                            icon="heroicon-o-plus"
                            color="gray"
                        >
                            Upload More Videos
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
