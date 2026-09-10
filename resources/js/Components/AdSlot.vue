<script setup>
/**
 * AdSlot — renders ad HTML including <script> tags into the DOM.
 *
 * Critical fix for ExoClick and similar ad networks:
 * Their code pattern is:
 *   1. <script async src="ad-provider.js">   ← loads externally, async
 *   2. <ins data-zoneid="...">               ← placeholder element
 *   3. <script>AdProvider.push({serve:{}})<  ← must run AFTER #1 loads
 *
 * If we append all scripts immediately, #3 runs before #1 finishes loading,
 * creating AdProvider as a plain array instead of ExoClick's object → no ad.
 *
 * Solution: collect all nodes, append non-script nodes immediately,
 * load external src scripts sequentially (waiting for each to load),
 * then run inline scripts in order after all external scripts are done.
 */
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import { useFetch } from '@/Composables/useFetch';

const props = defineProps({
    html: { type: String, default: '' },
    /**
     * Where this slot sits, e.g. 'footer', 'grid', 'banner_above_player'.
     *
     * Network ad codes have no creative row, so this label is the only thing
     * identifying them in reporting. Empty means "do not report" — used by
     * slots that already have their own tracking, such as the interstitial.
     */
    placement: { type: String, default: '' },
    /**
     * Hold injection until the slot is near the viewport.
     *
     * On by default. Every slot used to inject in onMounted regardless of
     * position, so a long grid ran several third-party ad scripts far below the
     * fold on page load — bandwidth and main-thread time spent on impressions
     * that may never be seen. Pass :lazy="false" for a slot that is reliably
     * above the fold, such as the banner above the player.
     */
    lazy: { type: Boolean, default: true },
    /**
     * Reserves space so the creative does not shove the page down when it
     * paints. `.ad-slot` had no dimensions at all, which made cumulative layout
     * shift unavoidable on every page carrying an ad.
     */
    format: {
        type: String,
        default: '',
        validator: (v) => ['', 'leaderboard', 'rectangle', 'mobile-banner', 'native'].includes(v),
    },
});

// Width is left to the container; only height needs reserving, since that is
// what pushes content around. Values match the sizes the admin UI advertises.
const RESERVED_HEIGHTS = {
    leaderboard: 90,
    rectangle: 250,
    'mobile-banner': 100,
    native: 0,
};

// Released once the creative is in, so a slot the network declines to fill
// collapses instead of leaving a permanent gap.
const injected = ref(false);

const reservedStyle = computed(() => {
    if (injected.value || !props.format) return {};
    const height = RESERVED_HEIGHTS[props.format] ?? 0;
    return height ? { minHeight: `${height}px` } : {};
});

const { post } = useFetch();

const container = ref(null);

/**
 * External scripts already requested by *any* slot on this page, keyed by URL.
 *
 * Module scope on purpose. The whole point of sequencing is that an inline
 * `AdProvider.push(...)` must not run before the provider script it depends on
 * has finished loading — and with a network like ExoClick the provider script
 * is shared by every slot on the page (footer, sidebar, grid). The previous
 * check asked "is there a tag with this src in the DOM", which is true the
 * moment the *first* slot appends it, so every later slot skipped straight to
 * its inline script and hit exactly the race this file exists to prevent.
 * Tracking the load promise instead means later slots await the same load.
 */
const externalScriptLoads = new Map();

function loadExternalScript(scriptDef, target) {
    const existing = externalScriptLoads.get(scriptDef.src);
    if (existing) return existing;

    const promise = new Promise((resolve) => {
        const el = document.createElement('script');
        // Copy attributes except async/defer — we sequence manually
        for (const [name, value] of Object.entries(scriptDef.attrs)) {
            if (name === 'async' || name === 'defer') continue;
            // Normalize type to text/javascript so browser always executes it
            if (name === 'type') {
                el.setAttribute('type', 'text/javascript');
                continue;
            }
            el.setAttribute(name, value);
        }
        // Resolve on error too: one dead creative must not stall the chain.
        el.onload = () => resolve();
        el.onerror = () => resolve();
        target.appendChild(el);
    });

    externalScriptLoads.set(scriptDef.src, promise);
    return promise;
}

/**
 * Run a script list in order, aborting if this slot has moved on.
 *
 * `isCurrent` is the generation guard. Without it a chain started for the
 * previous `html` value kept appending its remaining scripts after a newer
 * injectHtml() had already wiped the container, interleaving two ad codes in
 * one slot.
 */
async function runScripts(scripts, isCurrent) {
    for (const scriptDef of scripts) {
        if (!isCurrent() || !container.value) return;

        if (scriptDef.src) {
            await loadExternalScript(scriptDef, container.value);
        } else {
            // Inline script — execute then continue
            const el = document.createElement('script');
            if (scriptDef.content) {
                el.textContent = scriptDef.content;
            }
            container.value.appendChild(el);
        }
    }
}

/**
 * Resolves once the CMP has reported a TCF state, or immediately when consent
 * gating is off.
 *
 * Shared across every slot (module scope) so one page with six ad slots waits
 * on one __tcfapi listener rather than six. Deliberately resolves rather than
 * rejects on timeout: a CMP that never answers must not blank every ad slot on
 * the site, so ads proceed and the vendor's own gating remains the backstop.
 */
let consentGate = null;
function awaitConsent() {
    if (consentGate) return consentGate;

    const cfg = (typeof window !== 'undefined' && window.__adConsent) || {};
    if (!cfg.wait) {
        consentGate = Promise.resolve();
        return consentGate;
    }

    consentGate = new Promise((resolve) => {
        let settled = false;
        const done = () => { if (!settled) { settled = true; resolve(); } };

        const timer = setTimeout(done, cfg.timeoutMs || 3000);
        const finish = () => { clearTimeout(timer); done(); };

        if (typeof window.__tcfapi !== 'function') {
            // No CMP present despite the setting — don't strand the slots.
            finish();
            return;
        }

        try {
            window.__tcfapi('addEventListener', 2, (tcData, success) => {
                if (!success) { finish(); return; }
                // Both mean the user is no longer being asked.
                if (tcData.eventStatus === 'tcloaded' || tcData.eventStatus === 'useractioncomplete') {
                    finish();
                }
            });
        } catch {
            finish();
        }
    });

    return consentGate;
}

/**
 * Report that a network slot rendered.
 *
 * Fire-and-forget: a failed beacon must never affect the page, and the server
 * de-duplicates repeats within the minute, so a re-render costs nothing.
 */
function reportSlotImpression(placement) {
    if (!placement) return;
    post('/api/ad-slot-impression', { placement }).catch(() => {});
}

// Incremented on every injection so a superseded script chain can bail out.
let injectGeneration = 0;

function injectHtml(html) {
    if (!container.value) return;

    // Bump first: any chain still running for a previous html value sees a
    // stale generation on its next step and stops instead of appending into
    // the content we are about to replace.
    const generation = ++injectGeneration;
    const isCurrent = () => generation === injectGeneration;

    container.value.innerHTML = '';

    if (!html || !html.trim()) return;

    const temp = document.createElement('div');
    temp.innerHTML = html;

    const scripts = [];
    const nodes = Array.from(temp.childNodes);

    for (const node of nodes) {
        if (node.nodeName === 'SCRIPT') {
            scripts.push({
                src: node.getAttribute('src') || '',
                attrs: Object.fromEntries(
                    Array.from(node.attributes).map(a => [a.name, a.value])
                ),
                content: node.textContent || '',
            });
        } else {
            // Append non-script nodes immediately (ins, div, etc.)
            container.value.appendChild(node.cloneNode(true));
        }
    }

    // Load scripts sequentially so external scripts finish before inline ones run
    if (scripts.length > 0) {
        runScripts(scripts, isCurrent);
    }

    // Accessibility: ensure all injected iframes have a title attribute (PageSpeed audit)
    nextTick(() => {
        if (!container.value) return;
        container.value.querySelectorAll('iframe:not([title])').forEach(iframe => {
            iframe.setAttribute('title', 'Advertisement');
        });
    });
}

// MutationObserver to catch iframes created asynchronously by ad scripts
let iframeObserver = null;

function startIframeObserver() {
    if (!container.value || iframeObserver) return;
    iframeObserver = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeName === 'IFRAME' && !node.getAttribute('title')) {
                    node.setAttribute('title', 'Advertisement');
                }
                if (node.querySelectorAll) {
                    node.querySelectorAll('iframe:not([title])').forEach(iframe => {
                        iframe.setAttribute('title', 'Advertisement');
                    });
                }
            }
        }
    });
    iframeObserver.observe(container.value, { childList: true, subtree: true });
}

// Injection always goes through the consent gate. injectHtml bumps its own
// generation on entry, so a stale queued injection is discarded by the same
// guard that protects an interrupted script chain.
const injectWhenAllowed = async (html) => {
    if (!html || !html.trim()) {
        injectHtml(html);
        return;
    }
    // Visibility first, then consent: a slot the visitor never scrolls to
    // should not be the reason a consent prompt is waited on.
    await awaitVisible();
    await awaitConsent();
    await nextTick();
    injectHtml(html);
    injected.value = true;
    reportSlotImpression(props.placement);
};

/**
 * Resolves when the slot is allowed to load: immediately when eager, otherwise
 * once it comes within 200px of the viewport.
 *
 * 200px is roughly one flick of scroll, which is enough for the ad network's
 * round trip to finish before the slot is actually on screen.
 */
const visible = ref(!props.lazy);
let stopVisibilityObserver = null;

const awaitVisible = () => {
    if (visible.value) return Promise.resolve();

    return new Promise((resolve) => {
        const { stop } = useIntersectionObserver(
            container,
            ([entry]) => {
                if (!entry?.isIntersecting) return;
                visible.value = true;
                stop();
                stopVisibilityObserver = null;
                resolve();
            },
            { rootMargin: '200px' }
        );
        stopVisibilityObserver = stop;
    });
};

onMounted(() => {
    if (props.html) {
        injectWhenAllowed(props.html);
    }
    startIframeObserver();
});

watch(() => props.html, (newHtml) => {
    injectWhenAllowed(newHtml);
});

onBeforeUnmount(() => {
    if (stopVisibilityObserver) {
        stopVisibilityObserver();
        stopVisibilityObserver = null;
    }
    if (iframeObserver) {
        iframeObserver.disconnect();
        iframeObserver = null;
    }
    if (container.value) {
        container.value.innerHTML = '';
    }
});
</script>

<template>
    <div ref="container" class="ad-slot" :style="reservedStyle"></div>
</template>

<style scoped>
/**
 * Trap the creative's stacking inside the slot.
 *
 * Ad markup is third-party and routinely carries `position:absolute` with
 * `z-index:2147483647` — the 32-bit maximum, so no element on the page can be
 * raised above it. Nothing here previously created a stacking context: this div
 * was unstyled, and `<main>` in AppLayout has no position/transform/z-index
 * either. That left injected creatives competing directly in the *root*
 * stacking context against the fixed `z-50` site header, which they won — the
 * banners above and below the video player painted over the top bar on scroll.
 *
 * `isolation: isolate` makes this element a stacking context, so a descendant's
 * z-index — however large — can only order things *within* the slot. The slot
 * itself then sits at z-index 0 in the root context, below the header. This
 * also covers `position: fixed` creatives, which `position: relative` alone
 * would not contain, because the isolation governs painting rather than layout.
 *
 * The trade-off is deliberate: a format designed to expand beyond its slot
 * (pushdown, expandable) is clamped too. Every slot this renders is a
 * fixed-size unit, so containment is the wanted behaviour; an expandable buy
 * would need an explicit opt-out here.
 */
.ad-slot {
    position: relative;
    z-index: 0;
    isolation: isolate;
}
</style>
