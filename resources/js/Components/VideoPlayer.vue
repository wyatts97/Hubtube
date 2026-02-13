<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { VidstackPlayer, VidstackPlayerLayout } from 'vidstack/global/player';
import 'vidstack/player/styles/default/theme.css';
import 'vidstack/player/styles/default/layouts/video.css';

// Custom MediaStorage: persists only volume/muted — never time.
// Videos always start from the beginning on page load.
const STORAGE_KEY = 'hubtube-player';
const volumeStorage = {
    getVolume:       async () => { try { const v = localStorage.getItem(`${STORAGE_KEY}-volume`); return v !== null ? Number(v) : null; } catch { return null; } },
    setVolume:       async (v) => { try { localStorage.setItem(`${STORAGE_KEY}-volume`, String(v)); } catch {} },
    getMuted:        async () => { try { const m = localStorage.getItem(`${STORAGE_KEY}-muted`); return m !== null ? m === 'true' : null; } catch { return null; } },
    setMuted:        async (m) => { try { localStorage.setItem(`${STORAGE_KEY}-muted`, String(m)); } catch {} },
    getTime:         async () => null,
    getLang:         async () => null,
    getCaptions:     async () => null,
    getPlaybackRate: async () => null,
    getVideoQuality: async () => null,
    getAudioGain:    async () => null,
};

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    poster: {
        type: String,
        default: '',
    },
    hlsPlaylist: {
        type: String,
        default: '',
    },
    autoplay: {
        type: Boolean,
        default: false,
    },
    previewThumbnails: {
        type: String,
        default: '',
    },
});

const containerRef = ref(null);
let player = null;

const initPlayer = async () => {
    if (!containerRef.value) return;

    // Clean any previous player DOM left behind
    containerRef.value.innerHTML = '';

    // Primary source: HLS playlist for adaptive bitrate streaming.
    // Fallback: direct MP4 URL if HLS is unavailable.
    // Use object format with type hint for HLS so Vidstack selects the
    // HLS provider even if the URL lacks a .m3u8 extension (e.g. CDN rewrites).
    const source = props.hlsPlaylist
        ? { src: props.hlsPlaylist, type: 'application/x-mpegurl' }
        : props.src;

    const layoutProps = {};

    if (props.previewThumbnails) {
        layoutProps.thumbnails = props.previewThumbnails;
    }

    try {
        player = await VidstackPlayer.create({
            target: containerRef.value,
            src: source,
            poster: props.poster,
            crossOrigin: 'anonymous',
            playsinline: true,
            layout: new VidstackPlayerLayout({
                ...layoutProps,
                colorScheme: 'dark',
            }),
        });

        if (props.autoplay) {
            player.play().catch(() => {});
        }
    } catch (e) {
        console.error('[VideoPlayer] Failed to create Vidstack player:', e);
    }
};

const destroyPlayer = () => {
    if (player) {
        player.destroy();
        player = null;
    }
};

onMounted(() => {
    initPlayer();
});

onUnmounted(() => {
    destroyPlayer();
});

watch(() => props.src, async () => {
    destroyPlayer();
    await nextTick();
    initPlayer();
});

watch(() => props.hlsPlaylist, async () => {
    destroyPlayer();
    await nextTick();
    initPlayer();
});
</script>

<template>
    <div class="video-player-wrapper" ref="containerRef"></div>
</template>

<style>
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
}

.video-player-wrapper media-player {
    width: 100%;
    height: 100%;
}

.video-player-wrapper media-player video {
    object-fit: contain;
}

/* Constrain seekbar thumbnail preview on mobile so portrait videos
   don't overflow past the player's top edge. */
@media (max-width: 640px) {
    .video-player-wrapper .vds-slider-thumbnail {
        max-height: 100px;
        max-width: 80px;
    }
    .video-player-wrapper .vds-slider-thumbnail media-thumbnail {
        max-height: 100px;
        max-width: 80px;
    }
    .video-player-wrapper .vds-slider-thumbnail img {
        max-height: 100px;
        max-width: 80px;
        object-fit: contain;
    }
}
</style>
