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
                <form wire:submit.prevent="applyBulkSettings">
                    {{ $this->bulkSettingsForm }}
                    <div class="mt-3">
                        <x-filament::button type="submit" icon="heroicon-o-check" color="gray" size="sm">
                            Apply to All Entries
                        </x-filament::button>
                    </div>
                </form>
            @endif

            {{-- Video Entries --}}
            @if (count($entries) > 0)
                <x-filament::section heading="Video Queue ({{ count($entries) }})" icon="heroicon-o-queue-list">
                    {{ $this->entriesForm }}

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
        @if (!empty($createdVideoIds) || $this->bulkToken)
            <x-filament::section heading="Processing Status" icon="heroicon-o-cpu-chip"
                description="Videos are being processed. Once complete, scheduled videos will auto-publish at their scheduled time.">
                {{-- Poll for async job results when a token is present --}}
                @if ($this->bulkToken)
                    <div wire:poll.3s="pollBulkResults"></div>
                @endif
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
                                    @if ($video->scheduled_at)
                                        <x-filament::badge color="info" icon="heroicon-o-clock">
                                            Scheduled: {{ $video->scheduled_at->format('M j, g:i A') }}
                                        </x-filament::badge>
                                    @elseif ($video->is_approved)
                                        <x-filament::badge color="success">Published</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning">Needs Moderation</x-filament::badge>
                                    @endif
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
