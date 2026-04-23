<script setup>
import { computed } from 'vue';
import AdSlot from '@/Components/AdSlot.vue';

/**
 * Reusable banner-ad renderer. Handles:
 *  - `config.enabled` toggle
 *  - Desktop (728x90) via `config.code` or `config.image` + `config.link`
 *  - Mobile (300x100) via `config.mobileCode` / `config.mobileImage` / `config.mobileLink`
 *  - Falls back to desktop html when no mobile variant is set.
 */
const props = defineProps({
    config: {
        type: Object,
        default: () => ({}),
    },
});

const enabled = computed(() => !!props.config?.enabled);

const desktopHtml = computed(() => {
    const c = props.config || {};
    if (c.image && !c.code) {
        const img = `<img src="${c.image}" alt="Ad" style="max-width:728px;height:auto;">`;
        return c.link
            ? `<a href="${c.link}" target="_blank" rel="sponsored noopener">${img}</a>`
            : img;
    }
    return c.code || '';
});

const mobileHtml = computed(() => {
    const c = props.config || {};
    if (c.mobileImage && !c.mobileCode) {
        const img = `<img src="${c.mobileImage}" alt="Ad" style="max-width:300px;height:auto;">`;
        return c.mobileLink
            ? `<a href="${c.mobileLink}" target="_blank" rel="sponsored noopener">${img}</a>`
            : img;
    }
    return c.mobileCode || c.code || '';
});

const hasContent = computed(() => enabled.value && (desktopHtml.value || mobileHtml.value));
</script>

<template>
    <div v-if="hasContent" class="mb-4 flex justify-center">
        <AdSlot :html="desktopHtml" class="hidden sm:block" />
        <AdSlot :html="mobileHtml" class="sm:hidden" />
    </div>
</template>
