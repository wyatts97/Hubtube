<script setup>
/**
 * AdSlot — renders ad HTML (including <script> tags) safely into the DOM.
 *
 * Vue's v-html does NOT execute <script> tags for security reasons.
 * Ad networks (ExoClick, JuicyAds, TrafficStars, etc.) deliver ads via
 * <script> tags, so we must manually clone and re-insert them so the
 * browser treats them as new, executable script elements.
 *
 * Usage:
 *   <AdSlot :html="adCode" />
 *   <AdSlot :html="mobileAdCode" class="md:hidden" />
 */
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    html: { type: String, default: '' },
});

const container = ref(null);

function injectHtml(html) {
    if (!container.value) return;

    // Clear previous content
    container.value.innerHTML = '';

    if (!html || !html.trim()) return;

    // Create a temporary container to parse the HTML
    const temp = document.createElement('div');
    temp.innerHTML = html;

    // Move all child nodes into the real container
    // For script tags, we must create NEW script elements so the browser executes them
    const nodes = Array.from(temp.childNodes);
    for (const node of nodes) {
        if (node.nodeName === 'SCRIPT') {
            const script = document.createElement('script');
            // Copy all attributes
            for (const attr of node.attributes) {
                script.setAttribute(attr.name, attr.value);
            }
            // Copy inline script content
            if (node.textContent) {
                script.textContent = node.textContent;
            }
            container.value.appendChild(script);
        } else {
            container.value.appendChild(node.cloneNode(true));
            // Also check for nested script tags inside non-script nodes
            const nestedScripts = container.value.lastChild?.querySelectorAll?.('script');
            if (nestedScripts?.length) {
                for (const nested of nestedScripts) {
                    const script = document.createElement('script');
                    for (const attr of nested.attributes) {
                        script.setAttribute(attr.name, attr.value);
                    }
                    if (nested.textContent) {
                        script.textContent = nested.textContent;
                    }
                    nested.parentNode.replaceChild(script, nested);
                }
            }
        }
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
