<script setup>
/**
 * Pre-roll for third-party embedded videos.
 *
 * An embed is an iframe we cannot pause, so a mid-stream ad break is impossible
 * and the only monetizable moment is before the iframe exists at all. The page
 * shows a click-to-play poster, this component plays the pre-roll on that click,
 * and only then is the iframe mounted (with autoplay).
 *
 * It works by running Fluid Player over a 1-second blank clip whose only job is
 * to be something a preRoll can precede — Fluid has no "play an ad by itself"
 * mode, an ad break is always attached to content. That is a workaround, not a
 * design: when the blank clip starts, the ad is over.
 *
 * Everything else on the site gets its ads from the player that is already
 * showing the video; this is the one surface that needs its own instance.
 */
import { ref, onUnmounted, nextTick } from 'vue';
import fluidPlayer from 'fluid-player';
import 'fluid-player/src/css/fluidplayer.css';

const props = defineProps({
    // Pre-roll only. Passing the full schedule would attach mid/post-rolls to a
    // one-second placeholder.
    adList: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['finished']);

const videoRef = ref(null);
const visible = ref(false);
let player = null;

// Guards the two paths that can finish the gate — the placeholder starting and
// the watchdog firing — so the iframe is never mounted twice.
let settled = false;

let watchdog = null;

const settle = () => {
    if (settled) return;
    settled = true;

    if (watchdog) {
        clearTimeout(watchdog);
        watchdog = null;
    }

    destroy();
    visible.value = false;
    emit('finished');
};

const destroy = () => {
    if (!player) return;

    try {
        player.destroy();
    } catch (e) {
        console.warn('[EmbedPreRollGate] destroy() failed:', e);
    }

    player = null;
};

/**
 * Run the pre-roll. Resolves once the gate is finished either way, so the caller
 * can simply await it before mounting the iframe.
 */
const play = async () => {
    if (!props.adList.length) {
        emit('finished');
        return;
    }

    settled = false;
    visible.value = true;
    await nextTick();

    if (!videoRef.value) {
        settle();
        return;
    }

    try {
        player = fluidPlayer(videoRef.value, {
            layoutControls: {
                fillToContainer: true,
                autoPlay: true,
                playButtonShowing: false,
                playPauseAnimation: false,
                keyboardControl: false,
                allowDownload: false,
                allowTheatre: false,
                controlBar: { autoHide: true, autoHideTimeout: 0 },
                miniPlayer: { enabled: false },
            },
            vastOptions: {
                adList: props.adList,
                maxAllowedVastTagRedirects: 5,
                vastTimeout: 5000,
                allowVPAID: true,
            },
        });

        // The placeholder only becomes the active source once every preRoll has
        // finished, errored or been skipped — so this fires exactly when the ad
        // is done, without needing to track each outcome separately.
        player.on('play', (_e, info) => {
            if ((info?.mediaSourceType ?? 'source') === 'source') {
                settle();
            }
        });
    } catch (e) {
        console.warn('[EmbedPreRollGate] Failed to start pre-roll:', e);
        settle();
        return;
    }

    // A viewer who clicked play and got a black frame will leave. If the ad has
    // not resolved by now — a dead tag, a VAST timeout that never settles — drop
    // the gate and show them the video they asked for.
    watchdog = setTimeout(settle, 15000);
};

defineExpose({ play });

onUnmounted(() => {
    if (watchdog) clearTimeout(watchdog);
    destroy();
});
</script>

<template>
    <div v-if="visible" class="absolute inset-0 z-30 bg-black">
        <video ref="videoRef" class="w-full h-full" playsinline muted>
            <source src="/media/blank.mp4" type="video/mp4" />
        </video>
    </div>
</template>
