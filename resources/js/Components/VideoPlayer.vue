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

const videoRef = ref(null);
const isLoadingQuality = ref(false);
let player = null;
let hls = null;
let hlsRetryCount = 0;
const HLS_MAX_RETRIES = 3;

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
                    hlsRetryCount++;
                    if (hlsRetryCount <= HLS_MAX_RETRIES) {
                        hls.startLoad();
                    } else {
                        destroyHls();
                        initDirectPlayback(plyrOptions);
                    }
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    hlsRetryCount++;
                    if (hlsRetryCount <= HLS_MAX_RETRIES) {
                        hls.recoverMediaError();
                    } else {
                        destroyHls();
                        initDirectPlayback(plyrOptions);
                    }
                    break;
                default:
                    destroyHls();
                    initDirectPlayback(plyrOptions);
                    break;
            }
        }
    });
};

const initDirectPlayback = (plyrOptions) => {
    // Reset video element to MP4 source after HLS detach
    if (videoRef.value) {
        videoRef.value.src = props.src;
        videoRef.value.load();
    }
    player = new Plyr(videoRef.value, plyrOptions);
    if (props.autoplay) {
        player.play().catch(() => {});
    }
};

let isSwitchingQuality = false;
let currentQualityLabel = null;

// Build a consistent Plyr options object for (re)initialisation
const buildPlyrOptions = (defaultQuality) => {
    const opts = {
        controls: [
            'play-large', 'play', 'progress', 'current-time',
            'duration', 'mute', 'volume', 'settings',
            'pip', 'airplay', 'fullscreen',
        ],
        settings: ['quality', 'speed'],
        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
        autoplay: false,
        quality: {
            default: defaultQuality ?? 720,
            options: qualityOptions.value.map(q => q.value),
            forced: true,
            onChange: (q) => updateQuality(q),
        },
        i18n: { qualityLabel: { 0: 'Auto' } },
    };

    if (props.previewThumbnails) {
        opts.previewThumbnails = { enabled: true, src: props.previewThumbnails };
    }

    qualityOptions.value.forEach(q => {
        if (q.value !== 0) {
            opts.i18n.qualityLabel[q.value] = q.label;
        }
    });

    return opts;
};

const updateQuality = (quality) => {
    // HLS path — hls.js handles quality switching natively
    if (hls) {
        if (quality === 0 || quality === -1) {
            hls.currentLevel = -1; // Auto
        } else {
            const levelIndex = hls.levels.findIndex(l => l.height === quality);
            if (levelIndex !== -1) {
                hls.currentLevel = levelIndex;
            }
        }
        return;
    }

    // MP4 path — full destroy → clear → reload → rebuild cycle.
    //
    // WHY THIS APPROACH:
    // The <video> element keeps displaying its last decoded frame
    // when you change sources. Neither Plyr's source setter nor
    // direct video.src swaps clear that stale frame. The only way
    // to clear it is: remove all sources → call load() → this puts
    // the element in HAVE_NOTHING state and clears the rendered
    // frame. The user then sees the poster (or black) during the
    // brief loading period instead of a frozen old frame.
    if (isSwitchingQuality) return;

    const qualityLabel = quality === 0 ? 'original' : `${quality}p`;
    const newUrl = props.qualityUrls[qualityLabel];
    if (!newUrl) return;

    if (qualityLabel === currentQualityLabel) return;

    const video = videoRef.value;
    if (!video || !player) return;

    isSwitchingQuality = true;
    isLoadingQuality.value = true;
    const savedTime = video.currentTime;
    const wasPlaying = !video.paused;
    const savedVolume = video.volume;
    const savedMuted = video.muted;
    currentQualityLabel = qualityLabel;

    // 1. Pause and destroy Plyr completely
    video.pause();
    player.destroy();
    player = null;

    // 2. CRITICAL: Clear the video element to remove stale frame.
    //    Remove src attribute + all <source> children + call load().
    //    This puts the element in HAVE_NOTHING / NETWORK_EMPTY state
    //    and the browser clears the rendered video frame immediately.
    video.removeAttribute('src');
    while (video.firstChild) {
        video.removeChild(video.firstChild);
    }
    video.load();

    // 3. Restore poster so user sees it during loading
    video.poster = props.poster || '';
    video.volume = savedVolume;
    video.muted = savedMuted;

    // 4. Now set the new source via a fresh <source> element
    const newSource = document.createElement('source');
    newSource.src = newUrl;
    newSource.type = 'video/mp4';
    video.appendChild(newSource);

    // 5. Wait for the new source to be ready, then rebuild Plyr
    const onReady = () => {
        video.removeEventListener('loadedmetadata', onReady);

        // Rebuild Plyr with the same options, defaulting to the
        // selected quality so Plyr's menu shows the right item
        player = new Plyr(video, buildPlyrOptions(quality));

        // 6. Seek back to saved position
        if (savedTime > 0 && isFinite(video.duration)) {
            video.currentTime = Math.min(savedTime, video.duration);
        }

        const finishSwitch = () => {
            isLoadingQuality.value = false;
            if (wasPlaying) {
                video.play().catch(() => {});
            }
            isSwitchingQuality = false;
        };

        if (savedTime > 0) {
            video.addEventListener('seeked', finishSwitch, { once: true });
        } else {
            finishSwitch();
        }
    };

    video.addEventListener('loadedmetadata', onReady, { once: true });
    video.load();
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

const SEEK_SHORT = 5;
const SEEK_LONG = 10;
const VOLUME_STEP = 0.05;

const handleKeydown = (e) => {
    // Skip if user is typing in an input/textarea
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
    if (!player) return;

    const video = videoRef.value;
    if (!video) return;

    switch (e.key) {
        case ' ':
        case 'k':
        case 'K':
            e.preventDefault();
            player.togglePlay();
            break;

        case 'ArrowLeft':
            e.preventDefault();
            player.currentTime = Math.max(0, player.currentTime - SEEK_SHORT);
            break;

        case 'ArrowRight':
            e.preventDefault();
            player.currentTime = Math.min(player.duration || 0, player.currentTime + SEEK_SHORT);
            break;

        case 'j':
        case 'J':
            e.preventDefault();
            player.currentTime = Math.max(0, player.currentTime - SEEK_LONG);
            break;

        case 'l':
        case 'L':
            e.preventDefault();
            player.currentTime = Math.min(player.duration || 0, player.currentTime + SEEK_LONG);
            break;

        case 'ArrowUp':
            e.preventDefault();
            player.volume = Math.min(1, player.volume + VOLUME_STEP);
            break;

        case 'ArrowDown':
            e.preventDefault();
            player.volume = Math.max(0, player.volume - VOLUME_STEP);
            break;

        case 'm':
        case 'M':
            e.preventDefault();
            player.muted = !player.muted;
            break;

        case 'f':
        case 'F':
            e.preventDefault();
            player.fullscreen.toggle();
            break;

        case '0': case '1': case '2': case '3': case '4':
        case '5': case '6': case '7': case '8': case '9':
            e.preventDefault();
            if (player.duration) {
                player.currentTime = (player.duration * parseInt(e.key)) / 10;
            }
            break;
    }
};

onMounted(() => {
    initPlayer();
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    destroyPlayer();
    document.removeEventListener('keydown', handleKeydown);
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
            loading="lazy"
            preload="metadata"
            :poster="poster"
            :data-poster="poster"
        >
            <source :src="src" type="video/mp4" />
        </video>
        <div v-if="isLoadingQuality" class="quality-loading-overlay">
            <div class="quality-loading-spinner"></div>
            <span class="quality-loading-text">Switching quality...</span>
        </div>
    </div>
</template>

<style>
/* plyr.css is now imported globally in app.css */

.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
}

.quality-loading-overlay {
    position: absolute;
    inset: 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: rgba(0, 0, 0, 0.8);
    pointer-events: none;
}

.quality-loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top-color: #fff;
    border-radius: 50%;
    animation: ql-spin 0.8s linear infinite;
}

.quality-loading-text {
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    font-weight: 500;
}

@keyframes ql-spin {
    to { transform: rotate(360deg); }
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

/* Replace Plyr's radio dots with checkmarks — avoids centering issues */
.video-player-wrapper .plyr__menu__container .plyr__control[role=menuitemradio]::before {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    width: 16px;
    height: 16px;
}

.video-player-wrapper .plyr__menu__container .plyr__control[role=menuitemradio][aria-checked=true]::before {
    background: var(--color-accent, #ef4444);
    border-color: var(--color-accent, #ef4444);
}

.video-player-wrapper .plyr__menu__container .plyr__control[role=menuitemradio]::after {
    /* Checkmark instead of dot — no centering issues */
    background: none;
    border-radius: 0;
    width: 5px;
    height: 9px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    left: 12px;
    top: 50%;
    transform: translateY(-55%) rotate(45deg) scale(0);
    transition: transform 0.2s ease, opacity 0.2s ease;
}

.video-player-wrapper .plyr__menu__container .plyr__control[role=menuitemradio][aria-checked=true]::after {
    opacity: 1;
    transform: translateY(-55%) rotate(45deg) scale(1);
}

.plyr-video {
    width: 100%;
    height: 100%;
}
</style>
