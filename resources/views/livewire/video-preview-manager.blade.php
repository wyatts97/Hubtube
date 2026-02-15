<div class="space-y-6">
    {{-- Video Player --}}
    @if($videoUrl || $hlsUrl)
    @php
        $video = \App\Models\Video::find($videoId);
        $isCloudOnly = $video && $video->storage_disk && $video->storage_disk !== 'public'
            && \App\Models\Setting::get('cloud_offloading_delete_local', false);
    @endphp
    <div
        wire:ignore.self
        x-data="{
            player: null,
            currentTime: 0,
            duration: 0,
            isCloudOnly: {{ $isCloudOnly ? 'true' : 'false' }},
            init() {
                this.$nextTick(() => {
                    this.player = this.$refs.videoPlayer;
                    if (this.player) {
                        this.player.addEventListener('timeupdate', () => {
                            this.currentTime = this.player.currentTime;
                            this.duration = this.player.duration || 0;
                        });
                        this.player.addEventListener('loadedmetadata', () => {
                            this.duration = this.player.duration || 0;
                        });
                    }
                });
            },
            formatTime(seconds) {
                if (!seconds || isNaN(seconds)) return '0:00';
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = Math.floor(seconds % 60);
                if (h > 0) return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                return m + ':' + String(s).padStart(2, '0');
            }
        }"
    >
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-header flex items-center gap-3 px-6 py-4">
                <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    Video Preview
                </h3>
            </div>
            <div class="fi-section-content px-6 pb-6">
                <div wire:ignore class="relative rounded-lg overflow-hidden bg-black" style="max-height: 400px;">
                    <video
                        x-ref="videoPlayer"
                        class="w-full"
                        style="max-height: 400px;"
                        controls
                        preload="auto"
                        @if($videoUrl)
                        src="{{ $videoUrl }}"
                        @endif
                    >
                        Your browser does not support the video tag.
                    </video>
                </div>

                {{-- Cloud-only warning --}}
                <template x-if="isCloudOnly">
                    <div class="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-4 py-2.5">
                        <x-heroicon-m-exclamation-triangle class="h-5 w-5 text-amber-500 shrink-0" />
                        <p class="text-sm text-amber-700 dark:text-amber-400">
                            The original video file has been offloaded to cloud storage and deleted locally. Frame capture via FFmpeg is not available.
                        </p>
                    </div>
                </template>

                {{-- Capture Frame Button --}}
                <div class="mt-4 flex items-center gap-3">
                    <button
                        type="button"
                        x-on:click="$wire.captureFrame(currentTime)"
                        class="fi-btn relative inline-flex items-center justify-center gap-1.5 font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg px-3 py-2 text-sm bg-primary-600 text-white shadow-sm hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400 focus-visible:ring-primary-500/50 dark:focus-visible:ring-primary-400/50"
                        :disabled="!duration || $wire.isCapturing || isCloudOnly"
                        :class="{ 'opacity-50 cursor-not-allowed': isCloudOnly }"
                    >
                        <template x-if="$wire.isCapturing">
                            <x-filament::loading-indicator class="h-4 w-4" />
                        </template>
                        <template x-if="!$wire.isCapturing">
                            <x-heroicon-m-camera class="h-4 w-4" />
                        </template>
                        <span>Capture Frame as Thumbnail</span>
                    </button>
                    <span class="text-sm text-gray-500 dark:text-gray-400" x-show="duration > 0">
                        Current position: <span x-text="formatTime(currentTime)"></span> / <span x-text="formatTime(duration)"></span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-content px-6 py-8 text-center">
            <x-heroicon-o-video-camera class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No video file available for preview.</p>
        </div>
    </div>
    @endif

    {{-- Thumbnail Management --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex items-center gap-3 px-6 py-4">
            <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Thumbnails
            </h3>
            <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                Click a thumbnail to set it as the active one, or upload a custom image.
            </p>
        </div>
        <div class="fi-section-content px-6 pb-6">
            {{-- Thumbnail Grid --}}
            @if(count($thumbnails) > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-6">
                @foreach($thumbnails as $thumb)
                <button
                    type="button"
                    wire:click="selectThumbnail('{{ $thumb['path'] }}')"
                    class="group relative rounded-lg overflow-hidden border-2 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 {{ $thumb['is_active'] ? 'border-primary-500 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-600' }}"
                >
                    <div class="aspect-video bg-gray-100 dark:bg-gray-800">
                        <img
                            src="{{ $thumb['url'] }}"
                            alt="Thumbnail"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                    </div>
                    @if($thumb['is_active'])
                    <div class="absolute inset-0 bg-primary-500/10 flex items-center justify-center">
                        <span class="bg-primary-500 text-white text-xs font-bold px-2 py-1 rounded-full shadow">
                            Active
                        </span>
                    </div>
                    @else
                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                        <span class="bg-white/90 dark:bg-gray-800/90 text-gray-700 dark:text-gray-200 text-xs font-medium px-2 py-1 rounded-full shadow">
                            Set as active
                        </span>
                    </div>
                    @endif
                </button>
                @endforeach
            </div>
            @else
            <div class="mb-6 text-center py-6 rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
                <x-heroicon-o-photo class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No generated thumbnails found.</p>
            </div>
            @endif

            {{-- Custom Upload --}}
            <div
                x-data="{ isDragging: false }"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                class="relative rounded-lg border-2 border-dashed transition-colors p-6 text-center"
                :class="isDragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'"
            >
                <input
                    x-ref="fileInput"
                    type="file"
                    accept="image/*"
                    wire:model="customThumbnail"
                    class="sr-only"
                    id="custom-thumb-upload"
                />
                <label for="custom-thumb-upload" class="cursor-pointer">
                    <x-heroicon-o-cloud-arrow-up class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" />
                    <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Drop an image here or <span class="text-primary-600 dark:text-primary-400 underline">browse</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        PNG, JPG, WebP up to 5MB. Recommended: 1280×720 (16:9)
                    </p>
                </label>

                {{-- Upload preview --}}
                @if($customThumbnail)
                <div class="mt-4 flex items-center justify-center gap-4">
                    <img src="{{ $customThumbnail->temporaryUrl() }}" alt="Preview" class="h-20 rounded-lg shadow" />
                    <button
                        type="button"
                        wire:click="uploadCustomThumbnail"
                        class="fi-btn relative inline-flex items-center justify-center gap-1.5 font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg px-3 py-2 text-sm bg-success-600 text-white shadow-sm hover:bg-success-500 dark:bg-success-500 dark:hover:bg-success-400"
                    >
                        <x-heroicon-m-check class="h-4 w-4" />
                        <span>Set as Thumbnail</span>
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
