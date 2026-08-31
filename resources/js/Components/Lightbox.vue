<script setup>
import { ref, watch } from 'vue';
import { useEventListener } from '@vueuse/core';
import { X, ChevronLeft, ChevronRight, Download, ZoomIn, ZoomOut } from 'lucide-vue-next';
import BaseDialog from '@/Components/UI/BaseDialog.vue';

const props = defineProps({
    images: { type: Array, default: () => [] },
    startIndex: { type: Number, default: 0 },
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const currentIndex = ref(props.startIndex);
const zoom = ref(1);
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const offset = ref({ x: 0, y: 0 });

watch(() => props.startIndex, (val) => {
    currentIndex.value = val;
    resetZoom();
});

// Body scroll lock is Reka's job now (BaseDialog's Dialog locks it while open).
// The manual `document.body.style.overflow` pair that used to live here was
// deleted deliberately: running both could double-toggle and leave the body
// stuck unscrollable after a close.

const currentImage = ref(null);
watch(currentIndex, () => {
    currentImage.value = props.images[currentIndex.value] || null;
    resetZoom();
}, { immediate: true });

const close = () => {
    emit('update:modelValue', false);
    resetZoom();
};

const prev = () => {
    if (currentIndex.value > 0) {
        currentIndex.value--;
    } else {
        currentIndex.value = props.images.length - 1;
    }
};

const next = () => {
    if (currentIndex.value < props.images.length - 1) {
        currentIndex.value++;
    } else {
        currentIndex.value = 0;
    }
};

const zoomIn = () => {
    zoom.value = Math.min(zoom.value + 0.5, 5);
};

const zoomOut = () => {
    zoom.value = Math.max(zoom.value - 0.5, 0.5);
    if (zoom.value <= 1) {
        offset.value = { x: 0, y: 0 };
    }
};

const resetZoom = () => {
    zoom.value = 1;
    offset.value = { x: 0, y: 0 };
};

const downloadImage = () => {
    const img = currentImage.value;
    if (!img) return;
    const url = img.image_url || img.url;
    const a = document.createElement('a');
    a.href = url;
    a.download = img.title || 'image';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

// Escape is handled by BaseDialog's Dialog; this covers the lightbox's own
// navigation and zoom keys only.
const onKeydown = (e) => {
    if (!props.modelValue) return;
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
    if (e.key === '+' || e.key === '=') zoomIn();
    if (e.key === '-') zoomOut();
};

useEventListener(document, 'keydown', onKeydown);
</script>

<template>
    <!--
        Shell only: BaseDialog supplies the portal, focus trap, Escape and body
        scroll lock. Everything inside — pan/zoom, arrow navigation, the
        thumbnail strip — stays this component's own logic.
        `contentClass` uses `fixed inset-0` so the panel fills the viewport
        instead of sitting in BaseDialog's padded, centred wrapper.
    -->
    <BaseDialog
        :model-value="modelValue"
        unstyled
        max-width="max-w-none"
        content-class="fixed inset-0 z-[100]"
        aria-label="Image viewer"
        :overlay-style="{ backgroundColor: 'rgba(0, 0, 0, 0.95)', backdropFilter: 'none', WebkitBackdropFilter: 'none' }"
        @update:model-value="!$event && close()"
    >
        <div
            class="absolute inset-0 flex items-center justify-center"
            @click.self="close"
        >
            <!-- Top Bar -->
            <div class="absolute top-0 start-0 end-0 flex items-center justify-between p-4 z-10">
                <div class="text-white text-sm">
                    <span v-if="currentImage?.title" class="font-medium">{{ currentImage.title }}</span>
                    <span class="opacity-60 ms-2">{{ currentIndex + 1 }} / {{ images.length }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="zoomOut" class="p-2 rounded-full hover:bg-white/10 text-white transition-colors" title="Zoom out" aria-label="Zoom out">
                        <ZoomOut class="w-5 h-5" />
                    </button>
                    <button @click="zoomIn" class="p-2 rounded-full hover:bg-white/10 text-white transition-colors" title="Zoom in" aria-label="Zoom in">
                        <ZoomIn class="w-5 h-5" />
                    </button>
                    <button @click="downloadImage" class="p-2 rounded-full hover:bg-white/10 text-white transition-colors" title="Download" aria-label="Download image">
                        <Download class="w-5 h-5" />
                    </button>
                    <button @click="close" class="p-2 rounded-full hover:bg-white/10 text-white transition-colors" title="Close" aria-label="Close lightbox">
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Navigation Arrows -->
            <button
                v-if="images.length > 1"
                @click.stop="prev"
                class="absolute start-4 top-1/2 -translate-y-1/2 p-3 rounded-full hover:bg-white/10 text-white transition-colors z-10"
                aria-label="Previous image"
            >
                <ChevronLeft class="w-8 h-8" />
            </button>
            <button
                v-if="images.length > 1"
                @click.stop="next"
                class="absolute end-4 top-1/2 -translate-y-1/2 p-3 rounded-full hover:bg-white/10 text-white transition-colors z-10"
                aria-label="Next image"
            >
                <ChevronRight class="w-8 h-8" />
            </button>

            <!-- Image -->
            <div class="max-w-[90vw] max-h-[85vh] overflow-hidden flex items-center justify-center">
                <img
                    v-if="currentImage"
                    :src="currentImage.image_url || currentImage.url"
                    :alt="currentImage.alt || currentImage.title || 'Image'"
                    class="max-w-full max-h-[85vh] object-contain transition-transform duration-200 select-none"
                    :style="{ transform: `scale(${zoom}) translate(${offset.x}px, ${offset.y}px)` }"
                    draggable="false"
                    @dblclick="zoom === 1 ? zoomIn() : resetZoom()"
                />
            </div>

            <!-- Thumbnail Strip -->
            <div v-if="images.length > 1" class="absolute bottom-0 start-0 end-0 p-3 flex justify-center gap-1.5 overflow-x-auto">
                <button
                    v-for="(img, idx) in images"
                    :key="idx"
                    @click="currentIndex = idx"
                    class="w-12 h-12 rounded-lg overflow-hidden shrink-0 transition-all border-2"
                    :aria-label="`View image ${idx + 1}`"
                    :style="{ borderColor: idx === currentIndex ? 'var(--color-accent)' : 'transparent', opacity: idx === currentIndex ? 1 : 0.5 }"
                >
                    <img
                        :src="img.thumbnail_url || img.image_url || img.url"
                        :alt="img.alt || img.title || ''"
                        class="w-full h-full object-cover"
                    />
                </button>
            </div>
        </div>
    </BaseDialog>
</template>
