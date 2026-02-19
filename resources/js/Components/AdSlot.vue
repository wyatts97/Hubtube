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

function loadScriptSequentially(scripts, index, nonScriptNodes) {
    if (index >= scripts.length) return;

    const scriptDef = scripts[index];
    const el = document.createElement('script');

    for (const [name, value] of Object.entries(scriptDef.attrs)) {
        el.setAttribute(name, value);
    }

    if (scriptDef.src) {
        // External script — wait for it to load before running the next one
        el.onload = () => loadScriptSequentially(scripts, index + 1, nonScriptNodes);
        el.onerror = () => loadScriptSequentially(scripts, index + 1, nonScriptNodes);
        container.value?.appendChild(el);
    } else {
        // Inline script — run immediately then continue
        if (scriptDef.content) {
            el.textContent = scriptDef.content;
        }
        container.value?.appendChild(el);
        loadScriptSequentially(scripts, index + 1, nonScriptNodes);
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
                src: node.src || node.getAttribute('src') || '',
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
        loadScriptSequentially(scripts, 0, []);
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
