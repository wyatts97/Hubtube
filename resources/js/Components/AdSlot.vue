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
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
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
    await awaitConsent();
    await nextTick();
    injectHtml(html);
    if (html && html.trim()) {
        reportSlotImpression(props.placement);
    }
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
    <div ref="container" class="ad-slot"></div>
</template>
