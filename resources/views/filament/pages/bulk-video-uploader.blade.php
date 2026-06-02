<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Upload Zone --}}
        @if (!$isCreating)
            <x-filament::section heading="Upload Videos" icon="phosphor-tray-arrow-up"
                description="Select multiple video files to upload. After uploading, fill in the details for each video below.">
                <form wire:submit="addUploadedFiles" class="space-y-4">
                    {{ $this->uploadForm }}
                    <x-filament::button type="submit" icon="phosphor-plus-circle">
                        Add to Queue
                    </x-filament::button>
                </form>
            </x-filament::section>

            {{-- Apply to All Bar --}}
            @if (count($entries) > 0)
                <form wire:submit.prevent="applyBulkSettings">
                    {{ $this->bulkSettingsForm }}
                    <div class="mt-3">
                        <x-filament::button type="submit" icon="phosphor-check" color="gray" size="sm">
                            Apply to All Entries
                        </x-filament::button>
                    </div>
                </form>
            @endif

            {{-- Video Entries --}}
            @if (count($entries) > 0)
                <x-filament::section heading="Video Queue ({{ count($entries) }})" icon="phosphor-list-numbers">
                    {{ $this->entriesForm }}

                    <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700 mt-4">
                        <x-filament::button
                            wire:click="createAllVideos"
                            wire:confirm="Create {{ count($entries) }} video(s)? They will be queued for processing."
                            icon="phosphor-rocket-launch"
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
            <x-filament::section heading="Processing Status" icon="phosphor-cpu"
                description="Videos are being processed. Once complete, scheduled videos will auto-publish at their scheduled time.">
                {{-- Poll for async job results when a token is present --}}
                @if ($this->bulkToken)
                    <div wire:poll.3s="pollBulkResults"></div>
                @endif
                <div wire:poll.5s class="ht-bulkproc">
                    @foreach ($this->createdVideos as $video)
                        <div class="ht-bulkproc__row" wire:key="proc-{{ $video->id }}">
                            <div class="ht-bulkproc__info">
                                @if ($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="" class="ht-bulkproc__thumb">
                                @else
                                    <div class="ht-bulkproc__thumb ht-bulkproc__thumb--empty">
                                        <x-phosphor-video-camera />
                                    </div>
                                @endif
                                <div class="ht-bulkproc__meta">
                                    <p class="ht-bulkproc__title">{{ $video->title }}</p>
                                    <p class="ht-bulkproc__user">{{ $video->user?->username ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="ht-bulkproc__status">
                                @if ($video->status === 'processed')
                                    @if ($video->scheduled_at)
                                        <x-filament::badge color="info" icon="phosphor-clock">
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
                                    <div class="ht-bulkproc__thumbs">
                                        @for ($i = 0; $i < $thumbCount; $i++)
                                            @php
                                                $thumbPath = "videos/{$video->slug}/{$slugTitle}_thumb_{$i}.jpg";
                                                $thumbExists = \Illuminate\Support\Facades\Storage::disk($video->storage_disk ?? 'public')->exists($thumbPath);
                                            @endphp
                                            @if ($thumbExists)
                                                <button
                                                    wire:click="selectThumbnail({{ $video->id }}, {{ $i }})"
                                                    class="ht-bulkproc__thumb-btn {{ $video->thumbnail === $thumbPath ? 'is-active' : '' }}"
                                                    title="Select thumbnail {{ $i + 1 }}"
                                                >
                                                    <img
                                                        src="{{ \Illuminate\Support\Facades\Storage::disk($video->storage_disk ?? 'public')->url($thumbPath) }}"
                                                        alt="Thumb {{ $i + 1 }}"
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
                            icon="phosphor-plus"
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
