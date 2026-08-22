<script setup>
import { ref, onMounted, watch } from 'vue';
import { useResizeObserver } from '@vueuse/core';

const props = defineProps({
    columns: { type: Number, default: 4 },
    gap: { type: Number, default: 16 },
});

const container = ref(null);
const columnCount = ref(props.columns);

const updateColumns = () => {
    if (!container.value) return;
    const width = container.value.offsetWidth;
    if (width < 640) columnCount.value = 2;
    else if (width < 1024) columnCount.value = 3;
    else columnCount.value = props.columns;
};

onMounted(updateColumns);

useResizeObserver(container, () => updateColumns());

watch(() => props.columns, updateColumns);
</script>

<template>
    <div
        ref="container"
        class="masonry-grid"
        :style="{
            columnCount: columnCount,
            columnGap: gap + 'px',
        }"
    >
        <slot />
    </div>
</template>

<style scoped>
.masonry-grid {
    column-fill: balance;
}

.masonry-grid > :deep(*) {
    break-inside: avoid;
    margin-bottom: v-bind(gap + 'px');
}
</style>
