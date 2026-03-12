@php
    $record = $getRecord();
    $videoUrl = null;

    // Match the same logic as VideoPreviewManager::loadVideoData()
    if ($record->video_path && $record->storage_disk === 'public') {
        $videoUrl = route('admin.video-stream', ['path' => $record->video_path]);
    } else {
        $videoUrl = $record->video_url;
    }
    $hlsUrl = $record->hls_playlist_url;
@endphp

@if($videoUrl)
<link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
<script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>

<div
    x-data="{
        plyr: null,
        init() {
            this.$nextTick(() => {
                const el = this.$refs.infolistPlayer;
                if (!el) return;
                this.plyr = new Plyr(el, {
                    controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings', 'fullscreen'],
                    settings: ['speed'],
                    speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                    keyboard: { focused: true, global: false },
                    tooltips: { controls: true, seek: true },
                    seekTime: 5,
                });
            });
        },
        destroy() {
            if (this.plyr) { this.plyr.destroy(); this.plyr = null; }
        }
    }"
>
    <div class="rounded-lg overflow-hidden bg-black" style="max-height: 420px;">
        <video
            x-ref="infolistPlayer"
            class="w-full"
            style="max-height: 420px;"
            preload="auto"
            playsinline
            crossorigin="anonymous"
            src="{{ $videoUrl }}"
        ></video>
    </div>
</div>
@else
<div class="text-center py-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">No video file available for preview.</p>
</div>
@endif
