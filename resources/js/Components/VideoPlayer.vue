<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { VidstackPlayer, PlyrLayout } from 'vidstack/global/player';
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

// Build the source(s) for Vidstack.
// - HLS: pass the m3u8 URL (Vidstack uses hls.js internally)
// - Multiple MP4 qualities: pass an array of source objects with
//   width/height so Vidstack populates its quality menu natively
// - Single MP4: pass the URL string
const buildSources = () => {
    if (props.hlsPlaylist) {
        return props.hlsPlaylist;
    }

    const urls = props.qualityUrls;
    if (urls && Object.keys(urls).length > 1) {
        const sources = [];
        for (const [label, url] of Object.entries(urls)) {
            if (label === 'original') {
                sources.push({ src: url, type: 'video/mp4', width: 1920, height: 1080 });
            } else {
                const height = parseInt(label.replace('p', ''));
                if (!isNaN(height)) {
                    const width = Math.round(height * (16 / 9));
                    sources.push({ src: url, type: 'video/mp4', width, height });
                }
            }
        }
        sources.sort((a, b) => b.height - a.height);
        return sources;
    }

    return props.src;
};

const initPlayer = async () => {
    if (!containerRef.value) return;

    // Clean any previous player DOM left behind
    containerRef.value.innerHTML = '';

    const layoutProps = {
        controls: [
            'play-large',
            'play',
            'progress',
            'current-time',
            'duration',
            'mute',
            'volume',
            'settings',
            'pip',
            'airplay',
            'fullscreen',
        ],
        clickToPlay: true,
        clickToFullscreen: true,
        speed: [0.5, 0.75, 1, 1.25, 1.5, 2],
        seekTime: 10,
    };

    if (props.previewThumbnails) {
        layoutProps.thumbnails = props.previewThumbnails;
    }

    try {
        player = await VidstackPlayer.create({
            target: containerRef.value,
            src: buildSources(),
            poster: props.poster,
            playsinline: true,
            crossOrigin: true,
            layout: new PlyrLayout(layoutProps),
            keyShortcuts: {
                togglePaused: 'k Space',
                toggleMuted: 'm',
                toggleFullscreen: 'f',
                seekBackward: 'ArrowLeft j',
                seekForward: 'ArrowRight l',
                volumeUp: 'ArrowUp',
                volumeDown: 'ArrowDown',
            },
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
    --plyr-color-main: var(--color-accent, #ef4444);
    --plyr-video-background: #000;
    --plyr-menu-background: var(--color-bg-card, #1f1f1f);
    --plyr-menu-color: var(--color-text-primary, #fff);
    --plyr-menu-border-color: var(--color-border, #262626);
}

.video-player-wrapper media-player video {
    object-fit: contain !important;
    width: 100%;
    height: 100%;
}

.video-player-wrapper .plyr__control--overlaid {
    background: var(--color-accent, #ef4444);
}

.video-player-wrapper .plyr__control--overlaid:hover {
    background: var(--color-accent, #ef4444);
    opacity: 0.9;
}

.video-player-wrapper [data-plyr="play"][data-plyr="large"] {
    background: var(--color-accent, #ef4444);
}

.video-player-wrapper .plyr--video .plyr__controls {
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.75));
}
</style>
