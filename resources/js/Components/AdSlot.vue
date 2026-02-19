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

const props = defineProps({
    html: { type: String, default: '' },
});

const container = ref(null);

function isScriptAlreadyLoaded(src) {
    return !!document.querySelector(`script[src="${src}"]`);
}

function loadScriptSequentially(scripts, index) {
    if (!container.value || index >= scripts.length) return;

    const scriptDef = scripts[index];
    const next = () => loadScriptSequentially(scripts, index + 1);

    if (scriptDef.src) {
        if (isScriptAlreadyLoaded(scriptDef.src)) {
            // Script already in DOM/loaded — run next immediately
            next();
        } else {
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
            el.onload = next;
            el.onerror = next;
            container.value.appendChild(el);
        }
    } else {
        // Inline script — execute then continue
        const el = document.createElement('script');
        if (scriptDef.content) {
            el.textContent = scriptDef.content;
        }
        container.value.appendChild(el);
        next();
    }
}

function injectHtml(html) {
    if (!container.value) return;

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
        loadScriptSequentially(scripts, 0);
    }
}

onMounted(() => {
    if (props.html) {
        nextTick(() => injectHtml(props.html));
    }
});

watch(() => props.html, (newHtml) => {
    nextTick(() => injectHtml(newHtml));
});

onBeforeUnmount(() => {
    if (container.value) {
        container.value.innerHTML = '';
    }
});
</script>

<template>
    <div ref="container" class="ad-slot"></div>
</template>
