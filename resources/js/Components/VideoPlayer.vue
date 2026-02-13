<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Plyr } from 'vidstack/global/plyr';
import 'vidstack/player/styles/plyr/theme.css';

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    poster: {
        type: String,
        default: '',
    },
    qualities: {
        type: Array,
        default: () => [],
    },
    qualityUrls: {
        type: Object,
        default: () => ({}),
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

// Build Plyr-compatible sources with `size` for quality switching.
// The Plyr wrapper maps `size` to video quality levels and populates
// the settings menu automatically.
const buildSources = () => {
    if (props.hlsPlaylist) {
        return [{ src: props.hlsPlaylist, type: 'application/x-mpegURL' }];
    }

    const urls = props.qualityUrls;
    const sources = [];

    if (urls && Object.keys(urls).length > 0) {
        for (const [label, url] of Object.entries(urls)) {
            const height = label === 'original'
                ? 1080
                : parseInt(label.replace('p', ''));
            if (!isNaN(height)) {
                sources.push({ src: url, type: 'video/mp4', size: height });
            }
        }
        sources.sort((a, b) => b.size - a.size);
    }

    if (sources.length === 0) {
        sources.push({ src: props.src, type: 'video/mp4', size: 720 });
    }

    return sources;
};

const initPlayer = () => {
    if (!containerRef.value) return;

    // Clean any previous player DOM left behind
    containerRef.value.innerHTML = '';

    // Create a fresh <video> element (keeps Vue out of its lifecycle)
    const video = document.createElement('video');
    video.setAttribute('playsinline', '');
    video.setAttribute('crossorigin', '');
    containerRef.value.appendChild(video);

    try {
        player = new Plyr(video, {
            controls: [
                'play-large',
                'play',
                'progress',
                'current-time',
                'duration',
                'mute+volume',
                'settings',
                'pip',
                'airplay',
                'fullscreen',
            ],
            clickToPlay: true,
            clickToFullscreen: true,
            speed: [0.5, 0.75, 1, 1.25, 1.5, 2],
            seekTime: 10,
            keyboard: { focused: true, global: true },
        });

        // Set source using Plyr-compatible format with sized sources
        const sourceConfig = {
            type: 'video',
            sources: buildSources(),
            poster: props.poster,
        };

        if (props.previewThumbnails) {
            sourceConfig.thumbnails = props.previewThumbnails;
        }

        player.source = sourceConfig;

        if (props.autoplay) {
            player.play().catch(() => {});
        }
    } catch (e) {
        console.error('[VideoPlayer] Failed to create player:', e);
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

/* Vidstack Plyr Layout theming via CSS custom properties */
.video-player-wrapper media-player {
    --plyr-color-main: var(--color-accent, #ef4444);
    --plyr-video-background: #000;
    --plyr-menu-background: var(--color-bg-card, #1f1f1f);
    --plyr-menu-color: var(--color-text-primary, #fff);
    --plyr-menu-border-color: var(--color-border, #262626);
    width: 100%;
    height: 100%;
}

.video-player-wrapper media-player video {
    object-fit: contain;
}
</style>
