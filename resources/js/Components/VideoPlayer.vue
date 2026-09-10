<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick, computed } from 'vue';
import fluidPlayer from 'fluid-player';
import 'fluid-player/src/css/fluidplayer.css';

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
    // Per-quality progressive renditions as Video::getQualityUrlsAttribute()
    // returns them: a map keyed by quality label, e.g. { '1080p': url,
    // '720p': url, original: url }. Only used when there is no HLS playlist —
    // an adaptive stream carries its own levels and Fluid builds the quality
    // menu from the manifest.
    qualitySources: {
        type: Object,
        default: () => ({}),
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
    // VAST breaks, built server-side by VideoController so ad gating and
    // mid-roll scheduling stay in one place. Shape matches Fluid's adList.
    adList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['ready', 'ended', 'ad-ended']);

const videoRef = ref(null);
let player = null;

// Fluid rewrites the DOM around the <video> it is handed, so Vue must not try
// to patch that subtree. Re-keying the element on a source change makes Vue
// discard and recreate it wholesale, which pairs with the destroy/recreate
// below — Fluid does not support switching sources on a live instance.
const playerKey = computed(() => `${props.hlsPlaylist}|${props.src}`);

/**
 * <source> list for the current video.
 *
 * HLS wins when it exists: one source, adaptive levels, quality menu built from
 * the manifest. Otherwise fall back to the per-quality progressive renditions,
 * which is what `title` and `data-fluid-hd` drive in Fluid's quality selector.
 */
const sources = computed(() => {
    if (props.hlsPlaylist) {
        return [{ src: props.hlsPlaylist, type: 'application/x-mpegURL', label: null, hd: false }];
    }

    const entries = Object.entries(props.qualitySources || {});

    if (entries.length) {
        // Fluid lists qualities in source order, so sort highest-first here.
        // 'original' is the untranscoded upload and outranks every rendition;
        // the rest sort on the numeric part of their label ('1080p' -> 1080).
        const rank = (label) => (label === 'original' ? Infinity : parseInt(label, 10) || 0);

        return entries
            .sort(([a], [b]) => rank(b) - rank(a))
            .map(([label, url]) => ({
                src: url,
                type: 'video/mp4',
                label,
                hd: rank(label) >= 720,
            }));
    }

    return [{ src: props.src, type: 'video/mp4', label: null, hd: false }];
});

const initPlayer = () => {
    if (!videoRef.value) return;

    const layoutControls = {
        primaryColor: '#f59e0b',
        posterImage: props.poster || undefined,
        playButtonShowing: true,
        playPauseAnimation: true,
        fillToContainer: true,
        autoPlay: props.autoplay,
        keyboardControl: true,
        allowDownload: false,
        allowTheatre: true,
        playbackRateEnabled: true,
        miniPlayer: {
            enabled: true,
            width: 400,
            height: 225,
            widthMobile: 40,
            placeholderText: '',
            position: 'bottom right',
            autoToggle: false,
        },
        // No caption pipeline exists yet (nothing generates .vtt subtitle
        // tracks), so the menu would always be empty. The hook stays here so
        // enabling it later is a one-line change.
        subtitlesEnabled: false,
    };

    if (props.previewThumbnails) {
        // ProcessVideoJob already emits exactly this shape: a WEBVTT file whose
        // cues name individual sprite JPEGs by a path relative to the VTT.
        layoutControls.timelinePreview = {
            file: props.previewThumbnails,
            type: 'VTT',
            spriteRelativePath: true,
        };
    }

    const options = { layoutControls };

    if (props.adList.length) {
        options.vastOptions = {
            adList: props.adList,
            // Fluid defaults to 3. Network wrapper chains routinely exceed that,
            // and IMA — which this replaced — was more permissive, so keeping
            // the old default here would look like a fill-rate drop.
            maxAllowedVastTagRedirects: 5,
            vastTimeout: 5000,
            allowVPAID: true,
            showProgressbarMarkers: true,
            adCTAText: false,
        };
    }

    try {
        player = fluidPlayer(videoRef.value, options);

        // 'source' means content, not an ad break — the distinction matters for
        // playlist auto-advance, which must not fire on a post-roll ending.
        player.on('ended', (_e, info) => {
            emit('ended', info?.mediaSourceType ?? 'source');
        });

        emit('ready', player);
    } catch (e) {
        console.error('[VideoPlayer] Failed to create Fluid Player:', e);
    }
};

const destroyPlayer = () => {
    if (!player) return;

    try {
        player.destroy();
    } catch (e) {
        // destroy() throws if Fluid's DOM was already torn down by Vue. Nothing
        // to recover, but an uncaught error here would break the whole unmount.
        console.warn('[VideoPlayer] destroy() failed:', e);
    }

    player = null;
};

defineExpose({
    getPlayer: () => player,
    getVideoElement: () => videoRef.value,
});

onMounted(() => {
    initPlayer();
});

// Not optional in an Inertia SPA: a missed destroy() leaves Fluid's listeners
// and its miniplayer scroll handler bound to a detached subtree.
onUnmounted(() => {
    destroyPlayer();
});

watch(playerKey, async () => {
    destroyPlayer();
    await nextTick();
    initPlayer();
});
</script>

<template>
    <div class="video-player-wrapper">
        <video
            :key="playerKey"
            ref="videoRef"
            class="video-player-el"
            :title="title"
            playsinline
            crossorigin="anonymous"
        >
            <source
                v-for="source in sources"
                :key="source.src"
                :src="source.src"
                :type="source.type"
                :title="source.label"
                :data-fluid-hd="source.hd ? true : null"
            />
        </video>
    </div>
</template>

<style>
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
}

.video-player-wrapper .fluid_video_wrapper {
    width: 100% !important;
    height: 100% !important;
}

.video-player-wrapper video {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
