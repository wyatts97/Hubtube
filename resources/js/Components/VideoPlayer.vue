<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { VidstackPlayer, VidstackPlayerLayout } from 'vidstack/global/player';
import 'vidstack/player/styles/default/theme.css';
import 'vidstack/player/styles/default/layouts/video.css';

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
    const source = props.hlsPlaylist || props.src;

    const layoutProps = {};

    if (props.previewThumbnails) {
        layoutProps.thumbnails = props.previewThumbnails;
    }

    try {
        player = await VidstackPlayer.create({
            target: containerRef.value,
            src: source,
            poster: props.poster,
            crossOrigin: true,
            playsinline: true,
            storage: 'hubtube-player',
            layout: new VidstackPlayerLayout({
                ...layoutProps,
                colorScheme: 'dark',
            }),
        });

        // Remove Google Cast button using the documented WC slot override.
        // Appending a child with slot="googleCastButton" replaces the default
        // slot content with an empty (invisible) element.
        const layout = player.querySelector('media-video-layout');
        if (layout) {
            const empty = document.createElement('div');
            empty.slot = 'googleCastButton';
            layout.append(empty);
        }

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
</style>
