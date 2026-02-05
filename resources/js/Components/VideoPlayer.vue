<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import Plyr from 'plyr';
import Hls from 'hls.js';

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

const videoRef = ref(null);
let player = null;
let hls = null;

const hasHls = computed(() => props.hlsPlaylist && Hls.isSupported());
const hasMultipleQualities = computed(() => props.qualities.length > 1);

const qualityOptions = computed(() => {
    if (!hasMultipleQualities.value) return [];
    
    const options = props.qualities
        .filter(q => q !== 'original')
        .map(q => {
            const height = parseInt(q.replace('p', ''));
            return { label: q, value: height };
        })
        .sort((a, b) => b.value - a.value);
    
    // Add original/auto option
    options.unshift({ label: 'Auto', value: 0 });
    
    return options;
});

const initPlayer = () => {
    if (!videoRef.value) return;

    const plyrOptions = {
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
        settings: ['quality', 'speed'],
        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
        autoplay: false,
        quality: {
            default: 720,
            options: qualityOptions.value.map(q => q.value),
            forced: true,
            onChange: (quality) => updateQuality(quality),
        },
        i18n: {
            qualityLabel: {
                0: 'Auto',
            },
        },
    };
    
    // Add preview thumbnails if available
    if (props.previewThumbnails) {
        plyrOptions.previewThumbnails = {
            enabled: true,
            src: props.previewThumbnails,
        };
    }

    // Add quality labels
    qualityOptions.value.forEach(q => {
        if (q.value !== 0) {
            plyrOptions.i18n.qualityLabel[q.value] = q.label;
        }
    });

    if (hasHls.value) {
        initHls(plyrOptions);
    } else {
        // Direct video playback
        player = new Plyr(videoRef.value, plyrOptions);
        
        if (props.autoplay) {
            player.play().catch(() => {
                // Autoplay blocked, that's fine
            });
        }
    }
};

const initHls = (plyrOptions) => {
    hls = new Hls({
        maxBufferLength: 30,
        maxMaxBufferLength: 60,
    });

    hls.loadSource(props.hlsPlaylist);
    hls.attachMedia(videoRef.value);

    hls.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
        // Get available quality levels from HLS
        const availableLevels = hls.levels.map((level, index) => ({
            height: level.height,
            index: index,
        }));

        // Update quality options based on HLS levels
        plyrOptions.quality.options = [-1, ...availableLevels.map(l => l.height)];
        plyrOptions.quality.default = -1; // Auto
        plyrOptions.i18n.qualityLabel[-1] = 'Auto';

        availableLevels.forEach(level => {
            plyrOptions.i18n.qualityLabel[level.height] = `${level.height}p`;
        });

        player = new Plyr(videoRef.value, plyrOptions);

        player.on('qualitychange', (event) => {
            const newQuality = event.detail.quality;
            if (newQuality === -1) {
                hls.currentLevel = -1; // Auto
            } else {
                const levelIndex = hls.levels.findIndex(l => l.height === newQuality);
                if (levelIndex !== -1) {
                    hls.currentLevel = levelIndex;
                }
            }
        });

        if (props.autoplay) {
            player.play().catch(() => {});
        }
    });

    hls.on(Hls.Events.ERROR, (event, data) => {
        if (data.fatal) {
            switch (data.type) {
                case Hls.ErrorTypes.NETWORK_ERROR:
                    hls.startLoad();
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    hls.recoverMediaError();
                    break;
                default:
                    // Fall back to direct playback
                    destroyHls();
                    initDirectPlayback(plyrOptions);
                    break;
            }
        }
    });
};

const initDirectPlayback = (plyrOptions) => {
    player = new Plyr(videoRef.value, plyrOptions);
    if (props.autoplay) {
        player.play().catch(() => {});
    }
};

const updateQuality = (quality) => {
    if (hls && quality !== 0) {
        const levelIndex = hls.levels.findIndex(l => l.height === quality);
        if (levelIndex !== -1) {
            hls.currentLevel = levelIndex;
        }
    } else if (hls) {
        hls.currentLevel = -1; // Auto
    }
};

const destroyHls = () => {
    if (hls) {
        hls.destroy();
        hls = null;
    }
};

const destroyPlayer = () => {
    if (player) {
        player.destroy();
        player = null;
    }
    destroyHls();
};

onMounted(() => {
    initPlayer();
});

onUnmounted(() => {
    destroyPlayer();
});

watch(() => props.src, () => {
    destroyPlayer();
    initPlayer();
});

watch(() => props.hlsPlaylist, () => {
    destroyPlayer();
    initPlayer();
});
</script>

<template>
    <div class="video-player-wrapper">
        <video
            ref="videoRef"
            class="plyr-video"
            playsinline
            :poster="poster"
            :data-poster="poster"
        >
            <source v-if="!hasHls" :src="src" type="video/mp4" />
        </video>
    </div>
</template>

<style>
/* plyr.css is now imported globally in app.css */

.video-player-wrapper {
    width: 100%;
    height: 100%;
    background: #000;
}

.video-player-wrapper .plyr {
    width: 100%;
    height: 100%;
    --plyr-color-main: var(--color-accent, #ef4444);
    --plyr-video-background: #000;
    --plyr-menu-background: var(--color-bg-card, #1f1f1f);
    --plyr-menu-color: var(--color-text-primary, #fff);
    --plyr-menu-border-color: var(--color-border, #262626);
}

.video-player-wrapper .plyr video {
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

.video-player-wrapper .plyr--video .plyr__controls {
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.75));
}

.video-player-wrapper .plyr__menu__container {
    background: var(--color-bg-card, #1f1f1f);
}

.video-player-wrapper .plyr__menu__container .plyr__control[role=menuitemradio]::before {
    background: var(--color-accent, #ef4444);
}

.plyr-video {
    width: 100%;
    height: 100%;
}
</style>
