<script setup>
import { computed } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import AdSlot from '@/Components/AdSlot.vue';

/**
 * The one banner-ad renderer. Handles:
 *  - `config.enabled` toggle
 *  - Desktop via `code`/`html`, or `image` + `link`
 *  - Mobile via `mobileCode`/`mobile_html`, or `mobileImage` + `mobileLink`
 *  - Falls back to the desktop creative when no mobile variant is set
 *
 * Two things this deliberately does differently from the markup it replaced:
 *
 * 1. **Exactly one AdSlot is mounted**, chosen by a media query rather than by
 *    rendering both and hiding one with `hidden md:block`. Both slots executing
 *    meant every page view fetched and ran two ad codes, and the network saw
 *    two zone loads for a single impression.
 * 2. **Both key conventions are accepted.** Controllers emit camelCase for some
 *    slots (`mobileCode`) and snake_case for others (`mobile_html`), which is
 *    why the video page had its own copy of this markup.
 */
const props = defineProps({
    config: { type: Object, default: () => ({}) },
    /** Min width in px at which the desktop creative is used. Tailwind `sm`. */
    breakpoint: { type: [Number, String], default: 640 },
    desktopWidth: { type: Number, default: 728 },
    desktopHeight: { type: Number, default: 90 },
    mobileWidth: { type: Number, default: 300 },
    mobileHeight: { type: Number, default: 100 },
    /** Wrapper classes, so callers keep their existing spacing. */
    wrapperClass: { type: String, default: 'mb-4 flex justify-center' },
    /** Reporting label for this slot, forwarded to AdSlot. */
    placement: { type: String, default: '' },
});

/**
 * The image/link fields are admin-entered URLs, but they get interpolated into
 * an HTML string that AdSlot then parses *and executes scripts from*. Escaping
 * stops a value like `"><script>…` breaking out of the attribute — the raw-code
 * field is understood to be trusted markup, a URL field is not.
 */
const escapeAttr = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

const buildImageHtml = (image, link, maxWidth, maxHeight) => {
    const img = `<img src="${escapeAttr(image)}" alt="Advertisement" loading="lazy" decoding="async"`
        + ` class="rounded" style="max-width:${maxWidth}px;max-height:${maxHeight}px;height:auto;">`;
    return link
        ? `<a href="${escapeAttr(link)}" target="_blank" rel="sponsored noopener noreferrer">${img}</a>`
        : img;
};

const c = computed(() => props.config || {});
const enabled = computed(() => !!c.value.enabled);

const isDesktop = useMediaQuery(computed(() => `(min-width: ${props.breakpoint}px)`));

const desktopCode = computed(() => c.value.code || c.value.html || '');
const mobileCode = computed(() => c.value.mobileCode || c.value.mobile_html || '');
const mobileImage = computed(() => c.value.mobileImage || c.value.mobile_image || '');
const mobileLink = computed(() => c.value.mobileLink || c.value.mobile_link || '');

const desktopHtml = computed(() => {
    if (desktopCode.value) return desktopCode.value;
    if (c.value.image) {
        return buildImageHtml(c.value.image, c.value.link, props.desktopWidth, props.desktopHeight);
    }
    return '';
});

const mobileHtml = computed(() => {
    if (mobileCode.value) return mobileCode.value;
    if (mobileImage.value) {
        return buildImageHtml(mobileImage.value, mobileLink.value, props.mobileWidth, props.mobileHeight);
    }
    // No mobile variant configured — reuse the desktop creative.
    return desktopHtml.value;
});

const activeHtml = computed(() => (isDesktop.value ? desktopHtml.value : mobileHtml.value));

const hasContent = computed(() => enabled.value && (desktopHtml.value || mobileHtml.value));
</script>

<template>
    <div v-if="hasContent" :class="wrapperClass">
        <!-- Keyed so crossing the breakpoint remounts the slot and injects the
             other variant, instead of leaving the previous creative in place. -->
        <AdSlot :key="isDesktop ? 'desktop' : 'mobile'" :html="activeHtml" :placement="placement" />
    </div>
</template>
