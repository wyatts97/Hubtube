<script setup>
/**
 * Sticky bottom banner.
 *
 * Previously rendered straight into the Blade shell (app.blade.php). Inertia
 * serves that shell only on a *full* page load, so the creative initialised
 * once per session and then sat there unchanged for every subsequent SPA
 * navigation — one impression for a visit that might span thirty pageviews.
 *
 * As a Vue component it re-injects on each Inertia navigation, so the slot
 * earns per pageview like every other banner. Config still comes from the
 * server, where the ad-free gate is applied.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import AdSlot from '@/Components/AdSlot.vue';

const props = defineProps({
    config: { type: Object, default: () => ({}) },
});

// Matches the breakpoint the Blade version used for its mobile/desktop split.
const isDesktop = useMediaQuery('(min-width: 768px)');

const activeHtml = computed(() => {
    const c = props.config || {};
    if (!c.enabled) return '';
    return isDesktop.value ? (c.code || '') : (c.mobileCode || c.code || '');
});

/**
 * Bumped on every navigation to key the AdSlot, which remounts it and runs the
 * ad code again. Without a changing key Vue would reuse the existing slot and
 * the creative would never refresh.
 */
const generation = ref(0);
let stopNavigationListener = null;

onMounted(() => {
    stopNavigationListener = router.on('navigate', () => {
        generation.value++;
    });
});

onUnmounted(() => {
    if (stopNavigationListener) {
        stopNavigationListener();
        stopNavigationListener = null;
    }
});
</script>

<template>
    <div
        v-if="activeHtml"
        class="ht-sticky-banner fixed bottom-0 inset-x-0 z-50 flex justify-center w-full"
        style="max-height: 120px; overflow: hidden;"
    >
        <!-- Never lazy: the slot is pinned to the viewport, so it is on screen
             by definition and deferring it would only lose the impression. -->
        <AdSlot
            :key="`sticky-${generation}-${isDesktop ? 'd' : 'm'}`"
            :html="activeHtml"
            placement="sticky_banner"
            :lazy="false"
        />
    </div>
</template>
