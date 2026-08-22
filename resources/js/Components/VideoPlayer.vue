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
    // Accessible name for the player and its poster image. Videos/Show.vue
    // passes the SEO payload's thumbnailAlt (built from the configurable
    // seo_video_thumbnail_alt_template setting) so the poster isn't unlabelled.
    title: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['ready']);

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
            title: props.title,
            crossOrigin: 'anonymous',
            playsinline: true,
            layout: new VidstackPlayerLayout({
                ...layoutProps,
                colorScheme: 'dark',
            }),
        });

        // Use locally bundled hls.js instead of loading from CDN.
        // NOTE: a lazy `() => import('hls.js')` loader was tried here to defer
        // this chunk for MP4-only pages, but it broke HLS playback in production
        // (Vidstack fell back to a native <video type="application/x-mpegurl">
        // source, which only Safari supports) — reverted to the synchronous
        // import/assignment, which is the reliable, known-working form.
        player.addEventListener('provider-change', (event) => {
            const provider = event.detail;
            if (provider?.type === 'hls') {
                provider.library = HLS;
            }
        });

        // Let parents (e.g. ad orchestration) react to the player actually being
        // ready instead of polling the DOM for a <media-player> element to appear.
        player.addEventListener('can-play', () => emit('ready', player), { once: true });

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

defineExpose({
    getPlayer: () => player,
});

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
    visibility: hidden !important;
}

/* Seekbar thumbnail preview sizing via Vidstack CSS variables */
.video-player-wrapper {
    --vds-thumbnail-max-width: 200px;
    --vds-thumbnail-max-height: 160px;
}

@media (max-width: 640px) {
    .video-player-wrapper {
        --vds-thumbnail-max-width: 140px;
        --vds-thumbnail-max-height: 100px;
    }
}
</style>
