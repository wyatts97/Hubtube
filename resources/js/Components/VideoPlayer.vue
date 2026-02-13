<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { VidstackPlayer, VidstackPlayerLayout } from 'vidstack/global/player';
import 'vidstack/player/styles/default/theme.css';
import 'vidstack/player/styles/default/layouts/video.css';
import HLS from 'hls.js';

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

        // Use locally bundled hls.js instead of loading from CDN.
        player.addEventListener('provider-setup', (event) => {
            const provider = event.detail;
            if (provider.type === 'hls') {
                provider.library = HLS;
            }
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

/* Hide Google Cast button globally */
media-google-cast-button {
    display: none !important;
}

/* Constrain seekbar thumbnail preview on mobile */
@media (max-width: 640px) {
    .video-player-wrapper {
        --video-slider-thumbnail-min-width: 60px;
        --video-slider-thumbnail-min-height: 60px;
    }

    .video-player-wrapper .vds-slider-thumbnail {
        max-height: 120px;
        max-width: 80px;
        min-height: auto;
        min-width: auto;
        overflow: hidden;
    }

    .video-player-wrapper .vds-slider-thumbnail media-thumbnail,
    .video-player-wrapper .vds-slider-thumbnail img {
        max-height: 120px !important;
        max-width: 80px !important;
        object-fit: contain;
        height: auto;
        width: auto;
    }
}
</style>
