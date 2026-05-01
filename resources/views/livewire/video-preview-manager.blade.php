<div class="space-y-4">
    @once
        <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
        <script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js" defer></script>
    @endonce

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
            plyr: null,
            currentTime: 0,
            duration: 0,
            isCloudOnly: {{ $isCloudOnly ? 'true' : 'false' }},
            init() {
                this.$nextTick(() => {
                    const videoEl = this.$refs.videoPlayer;
                    if (!videoEl) return;

                    this.plyr = new Plyr(videoEl, {
                        controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings', 'fullscreen'],
                        settings: ['speed'],
                        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                        keyboard: { focused: true, global: false },
                        tooltips: { controls: true, seek: true },
                        seekTime: 5,
                    });

                    this.plyr.on('timeupdate', () => {
                        this.currentTime = this.plyr.currentTime || 0;
                        this.duration = this.plyr.duration || 0;
                    });
                    this.plyr.on('loadedmetadata', () => {
                        this.duration = this.plyr.duration || 0;
                    });
                });
            },
            destroy() {
                if (this.plyr) { this.plyr.destroy(); this.plyr = null; }
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
        <div class="fi-section rounded-xl bg-gray-900 shadow-sm ring-1 ring-white/10">
            <div class="fi-section-header flex items-center justify-between gap-3 px-5 py-3 border-b border-white/5">
                <h3 class="fi-section-header-heading text-sm font-semibold leading-6 text-white flex items-center gap-2">
                    <x-heroicon-m-play-circle class="h-5 w-5 text-primary-400" />
                    Video Preview
                </h3>
                <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-gray-300 font-mono">{{ $stats['status'] ?? '' }}</span>
            </div>
            <div class="fi-section-content px-5 py-4">
                <div wire:ignore
                    class="relative rounded-lg overflow-hidden bg-black mx-auto"
                    @if($isPortrait)
                        style="aspect-ratio: 9 / 16; max-width: 320px;"
                    @else
                        style="aspect-ratio: 16 / 9; max-width: 100%;"
                    @endif
                >
                    <video
                        x-ref="videoPlayer"
                        class="w-full h-full"
                        controls
                        preload="metadata"
                        playsinline
                        crossorigin="anonymous"
                        @if($currentThumbnailUrl)
                        poster="{{ $currentThumbnailUrl }}"
                        @endif
                        @if($videoUrl)
                        src="{{ $videoUrl }}"
                        @endif
                    >
                    </video>
                </div>

                {{-- Mini stats row --}}
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 text-gray-300">
                        <x-heroicon-m-eye class="h-3.5 w-3.5" /> {{ number_format($stats['views'] ?? 0) }} views
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 text-gray-300">
                        <x-heroicon-m-hand-thumb-up class="h-3.5 w-3.5" /> {{ number_format($stats['likes'] ?? 0) }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 text-gray-300">
                        <x-heroicon-m-clock class="h-3.5 w-3.5" /> {{ $stats['duration'] ?? '—' }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 text-gray-300">
                        <x-heroicon-m-circle-stack class="h-3.5 w-3.5" /> {{ $stats['size'] ?? '—' }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white/5 text-gray-300">
                        <x-heroicon-m-server class="h-3.5 w-3.5" /> {{ $stats['disk'] ?? '—' }}
                    </span>
                </div>

                {{-- Cloud-only warning --}}
                <template x-if="isCloudOnly">
                    <div class="mt-3 flex items-center gap-2 rounded-lg bg-amber-500/10 border border-amber-500/20 px-3 py-2">
                        <x-heroicon-m-exclamation-triangle class="h-4 w-4 text-amber-400 shrink-0" />
                        <p class="text-xs text-amber-300">
                            Original file offloaded to cloud. Frame capture via FFmpeg unavailable.
                        </p>
                    </div>
                </template>

                {{-- Action Row: Capture Frame + Copy URLs + Replace Source --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        x-on:click="$wire.captureFrame(currentTime)"
                        class="fi-btn inline-flex items-center gap-1.5 font-semibold rounded-lg px-3 py-1.5 text-xs bg-primary-500 text-white hover:bg-primary-400 transition"
                        :disabled="!duration || $wire.isCapturing || isCloudOnly"
                        :class="{ 'opacity-50 cursor-not-allowed': isCloudOnly }"
                    >
                        <template x-if="$wire.isCapturing"><x-filament::loading-indicator class="h-4 w-4" /></template>
                        <template x-if="!$wire.isCapturing"><x-heroicon-m-camera class="h-4 w-4" /></template>
                        <span>Capture Frame</span>
                    </button>

                    <span class="text-xs text-gray-400 font-mono" x-show="duration > 0">
                        <span x-text="formatTime(currentTime)"></span> / <span x-text="formatTime(duration)"></span>
                    </span>

                    <div class="ml-auto flex items-center gap-1.5"
                         x-data="{
                            copied: null,
                            copy(key, text) {
                                if (!text) return;
                                navigator.clipboard.writeText(text).then(() => {
                                    this.copied = key;
                                    setTimeout(() => this.copied = null, 1500);
                                });
                            }
                         }"
                    >
                        @if($shareUrls['public'] ?? false)
                        <button type="button" x-on:click="copy('public', @js($shareUrls['public']))"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs bg-white/5 hover:bg-white/10 text-gray-300 transition"
                            title="Copy public URL">
                            <x-heroicon-m-link class="h-3.5 w-3.5" />
                            <span x-text="copied === 'public' ? 'Copied!' : 'Public'"></span>
                        </button>
                        @endif
                        @if($shareUrls['stream'] ?? false)
                        <button type="button" x-on:click="copy('stream', @js($shareUrls['stream']))"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs bg-white/5 hover:bg-white/10 text-gray-300 transition"
                            title="Copy direct stream URL">
                            <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" />
                            <span x-text="copied === 'stream' ? 'Copied!' : 'Stream'"></span>
                        </button>
                        @endif
                        @if($shareUrls['source'] ?? false)
                        <button type="button" x-on:click="copy('source', @js($shareUrls['source']))"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs bg-white/5 hover:bg-white/10 text-gray-300 transition font-mono"
                            title="Copy source path">
                            <x-heroicon-m-document-duplicate class="h-3.5 w-3.5" />
                            <span x-text="copied === 'source' ? 'Copied!' : 'Path'"></span>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="fi-section rounded-xl bg-gray-900 shadow-sm ring-1 ring-white/10">
        <div class="fi-section-content px-6 py-8 text-center">
            <x-heroicon-o-video-camera class="mx-auto h-12 w-12 text-gray-500" />
            <p class="mt-2 text-sm text-gray-400">No video file available for preview.</p>
        </div>
    </div>
    @endif

    {{-- Thumbnails --}}
    <div class="fi-section rounded-xl bg-gray-900 shadow-sm ring-1 ring-white/10"
         x-data="{ showUpload: false }">
        <div class="fi-section-header flex items-center justify-between gap-3 px-5 py-3 border-b border-white/5">
            <h3 class="text-sm font-semibold leading-6 text-white flex items-center gap-2">
                <x-heroicon-m-photo class="h-5 w-5 text-primary-400" />
                Thumbnails
                @if(count($thumbnails) > 0)
                    <span class="text-xs font-normal text-gray-400">({{ count($thumbnails) }})</span>
                @endif
            </h3>
            <button type="button" x-on:click="showUpload = !showUpload"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs bg-white/5 hover:bg-white/10 text-gray-300 transition">
                <x-heroicon-m-arrow-up-tray class="h-3.5 w-3.5" />
                <span x-text="showUpload ? 'Hide upload' : 'Upload custom'"></span>
            </button>
        </div>
        <div class="fi-section-content px-5 py-4">
            @if(count($thumbnails) > 0)
            <div class="flex gap-2 overflow-x-auto snap-x pb-2" style="scrollbar-width: thin;">
                @foreach($thumbnails as $thumb)
                <button type="button" wire:click="selectThumbnail('{{ $thumb['path'] }}')"
                    class="shrink-0 snap-start relative rounded-lg overflow-hidden border-2 transition-all focus:outline-none {{ $thumb['is_active'] ? 'border-primary-500 ring-2 ring-primary-500/40' : 'border-transparent hover:border-primary-500/50' }}"
                    style="width: 140px; height: 80px;">
                    <img src="{{ $thumb['url'] }}" alt="Thumbnail" class="w-full h-full object-cover" loading="lazy" />
                    @if($thumb['is_active'])
                    <span class="absolute bottom-1 right-1 bg-primary-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">Active</span>
                    @endif
                </button>
                @endforeach
            </div>
            @else
            <div class="text-center py-4 rounded-lg border border-dashed border-gray-700">
                <p class="text-xs text-gray-500">No generated thumbnails found.</p>
            </div>
            @endif

            {{-- Collapsible custom upload --}}
            <div x-show="showUpload" x-cloak x-transition class="mt-3">
                <div
                    x-data="{ isDragging: false }"
                    x-on:dragover.prevent="isDragging = true"
                    x-on:dragleave.prevent="isDragging = false"
                    x-on:drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                    class="relative rounded-lg border-2 border-dashed transition-colors p-4 text-center"
                    :class="isDragging ? 'border-primary-500 bg-primary-500/10' : 'border-gray-700 hover:border-gray-500'"
                >
                    <input x-ref="fileInput" type="file" accept="image/*" wire:model="customThumbnail" class="sr-only" id="custom-thumb-upload" />
                    <label for="custom-thumb-upload" class="cursor-pointer">
                        <x-heroicon-o-cloud-arrow-up class="mx-auto h-6 w-6 text-gray-500" />
                        <p class="mt-1 text-xs font-medium text-gray-300">
                            Drop image or <span class="text-primary-400 underline">browse</span>
                        </p>
                        <p class="text-[11px] text-gray-500">PNG/JPG/WebP · up to 5MB · 1280×720 recommended</p>
                    </label>
                    @if($customThumbnail)
                    <div class="mt-3 flex items-center justify-center gap-3">
                        <img src="{{ $customThumbnail->temporaryUrl() }}" alt="Preview" class="h-14 rounded shadow" />
                        <button type="button" wire:click="uploadCustomThumbnail"
                            class="fi-btn inline-flex items-center gap-1.5 font-semibold rounded-lg px-3 py-1.5 text-xs bg-success-500 text-white hover:bg-success-400">
                            <x-heroicon-m-check class="h-4 w-4" />
                            <span>Set as Thumbnail</span>
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Replace Source Video --}}
    <div class="fi-section rounded-xl bg-gray-900 shadow-sm ring-1 ring-white/10"
         x-data="{ showReplace: false }">
        <div class="fi-section-header flex items-center justify-between gap-3 px-5 py-3 border-b border-white/5">
            <h3 class="text-sm font-semibold leading-6 text-white flex items-center gap-2">
                <x-heroicon-m-arrow-path class="h-5 w-5 text-primary-400" />
                Replace Source Video
            </h3>
            <button type="button" x-on:click="showReplace = !showReplace"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs bg-white/5 hover:bg-white/10 text-gray-300 transition">
                <span x-text="showReplace ? 'Cancel' : 'Replace file'"></span>
            </button>
        </div>
        <div x-show="showReplace" x-cloak x-transition class="fi-section-content px-5 py-4">
            <div class="rounded-lg bg-amber-500/10 border border-amber-500/20 px-3 py-2 mb-3 flex items-start gap-2">
                <x-heroicon-m-exclamation-triangle class="h-4 w-4 text-amber-400 shrink-0 mt-0.5" />
                <p class="text-xs text-amber-300">
                    Uploading a replacement will overwrite the current source, reset status to <strong>pending</strong>, and re-queue transcoding. HLS variants will be regenerated.
                </p>
            </div>
            <div
                x-data="{ isDragging: false }"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="isDragging = false; $refs.videoFileInput.files = $event.dataTransfer.files; $refs.videoFileInput.dispatchEvent(new Event('change'))"
                class="relative rounded-lg border-2 border-dashed transition-colors p-4 text-center"
                :class="isDragging ? 'border-primary-500 bg-primary-500/10' : 'border-gray-700 hover:border-gray-500'"
            >
                <input x-ref="videoFileInput" type="file" accept="video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm" wire:model="replacementVideo" class="sr-only" id="replace-video-upload" />
                <label for="replace-video-upload" class="cursor-pointer">
                    <x-heroicon-o-film class="mx-auto h-6 w-6 text-gray-500" />
                    <p class="mt-1 text-xs font-medium text-gray-300">
                        Drop video or <span class="text-primary-400 underline">browse</span>
                    </p>
                    <p class="text-[11px] text-gray-500">MP4, MOV, AVI, MKV, WebM · up to 5GB</p>
                </label>
                @if($replacementVideo)
                <div class="mt-3 flex items-center justify-center gap-3">
                    <span class="text-xs text-gray-300 font-mono truncate max-w-[240px]">{{ $replacementVideo->getClientOriginalName() }}</span>
                    <button type="button" wire:click="replaceSourceVideo"
                        wire:loading.attr="disabled" wire:target="replaceSourceVideo,replacementVideo"
                        class="fi-btn inline-flex items-center gap-1.5 font-semibold rounded-lg px-3 py-1.5 text-xs bg-danger-500 text-white hover:bg-danger-400 disabled:opacity-50">
                        <x-heroicon-m-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" wire:target="replaceSourceVideo" />
                        <span>Replace & Re-process</span>
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
