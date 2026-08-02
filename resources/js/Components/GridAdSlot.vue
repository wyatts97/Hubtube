<script setup>
/**
 * GridAdSlot — resilient ad placement for the video card grid.
 *
 *
 * 1. Single injection — picks ONE variant and renders ONE AdSlot, choosing
 *    desktop vs mobile code via matchMedia. Previously each slot mounted two
 *    AdSlots (both executed scripts) and picked two different random variants.
 * 2. Containment — the wrapper clips anything that still overflows, and
 *    injected <img> tags are clamped to the cell width.
 * 3. Scale-to-fit — fixed-size creatives (iframes/ins with hardcoded widths)
 *    that are wider than the cell are scaled down with transform: scale()
 *    and the wrapper height is set to the scaled height, so the grid row
 *    stays aligned and the creative never paints over neighboring cards.
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import AdSlot from '@/Components/AdSlot.vue';

const props = defineProps({
    ads: { type: Array, default: () => [] }, // [{ code, mobileCode }]
});

const wrapper = ref(null);
const scaledHeight = ref(null);

// Pick one variant once per slot instance (stable across re-renders)
const variant = props.ads.length
    ? props.ads[Math.floor(Math.random() * props.ads.length)]
    : null;

// Reactive breakpoint matching Tailwind's `sm`
const isDesktop = ref(false);
let mediaQuery = null;

const activeCode = computed(() => {
    if (!variant) return '';
    const code = isDesktop.value
        ? variant.code
        : (variant.mobileCode || variant.code);
    return code || '';
});

const wrapperStyle = computed(() =>
    scaledHeight.value ? { height: scaledHeight.value } : {}
);

function measureAndScale() {
    const el = wrapper.value;
    if (!el) return;

    const slot = el.querySelector('.ad-slot');
    const creative = slot?.firstElementChild;
    if (!slot || !creative) return;

    // Reset previous transform so we measure the creative's natural size
    slot.style.transform = '';
    slot.style.width = '';

    const available = el.clientWidth;
    if (!available) return;

    const naturalW = Math.max(slot.scrollWidth, creative.offsetWidth || 0);
    const naturalH = Math.max(slot.scrollHeight, creative.offsetHeight || 0);

    if (naturalW > available && naturalH > 0) {
        const scale = available / naturalW;
        slot.style.width = `${naturalW}px`;
        slot.style.transformOrigin = 'top center';
        slot.style.transform = `scale(${scale})`;
        scaledHeight.value = `${Math.ceil(naturalH * scale)}px`;
    } else {
        scaledHeight.value = null;
    }
}

// Ad networks inject their creative asynchronously — watch for DOM changes
// and re-measure. A couple of delayed retries catch slow iframe loads.
let mutationObserver = null;
let resizeObserver = null;
let retryTimers = [];

function scheduleMeasurements() {
    retryTimers.forEach(clearTimeout);
    retryTimers = [150, 600, 1500, 3000].map((delay) =>
        setTimeout(() => nextTick(measureAndScale), delay)
    );
}

onMounted(() => {
    mediaQuery = window.matchMedia('(min-width: 640px)');
    isDesktop.value = mediaQuery.matches;
    mediaQuery.addEventListener('change', onMediaChange);

    mutationObserver = new MutationObserver(() => nextTick(measureAndScale));
    resizeObserver = new ResizeObserver(() => measureAndScale());

    nextTick(() => {
        if (!wrapper.value) return;
        resizeObserver.observe(wrapper.value);
        const slot = wrapper.value.querySelector('.ad-slot');
        if (slot) {
            // Note: no attribute observation — measureAndScale mutates the
            // slot's style itself, which would retrigger an infinite loop.
            mutationObserver.observe(slot, { childList: true, subtree: true });
        }
        scheduleMeasurements();
    });
});

function onMediaChange(e) {
    isDesktop.value = e.matches;
    scaledHeight.value = null;
    nextTick(() => {
        const slot = wrapper.value?.querySelector('.ad-slot');
        if (slot && mutationObserver) {
            mutationObserver.disconnect();
            mutationObserver.observe(slot, { childList: true, subtree: true });
        }
        scheduleMeasurements();
    });
}

onBeforeUnmount(() => {
    retryTimers.forEach(clearTimeout);
    if (mediaQuery) mediaQuery.removeEventListener('change', onMediaChange);
    if (mutationObserver) mutationObserver.disconnect();
    if (resizeObserver) resizeObserver.disconnect();
});
</script>

<template>
    <div
        v-if="activeCode"
        ref="wrapper"
        class="grid-ad-slot"
        :style="wrapperStyle"
    >
        <AdSlot :key="isDesktop ? 'desktop' : 'mobile'" :html="activeCode" />
    </div>
</template>

<style scoped>
.grid-ad-slot {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* Clamp plain image creatives to the cell width (iframes/ins are handled
   by the transform scaler so their aspect ratio is preserved). */
.grid-ad-slot :deep(img) {
    max-width: 100%;
    height: auto;
}

.grid-ad-slot :deep(.ad-slot) {
    flex-shrink: 0;
}
</style>
