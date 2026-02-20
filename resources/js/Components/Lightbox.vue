<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { X, ChevronLeft, ChevronRight, Download, ZoomIn, ZoomOut } from 'lucide-vue-next';

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

watch(() => props.modelValue, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

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

const onKeydown = (e) => {
    if (!props.modelValue) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
    if (e.key === '+' || e.key === '=') zoomIn();
    if (e.key === '-') zoomOut();
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="fixed inset-0 z-[100] flex items-center justify-center"
            style="background-color: rgba(0, 0, 0, 0.95);"
            @click.self="close"
        >
            <!-- Top Bar -->
            <div class="absolute top-0 left-0 right-0 flex items-center justify-between p-4 z-10">
                <div class="text-white text-sm">
                    <span v-if="currentImage?.title" class="font-medium">{{ currentImage.title }}</span>
                    <span class="opacity-60 ml-2">{{ currentIndex + 1 }} / {{ images.length }}</span>
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
                class="absolute left-4 top-1/2 -translate-y-1/2 p-3 rounded-full hover:bg-white/10 text-white transition-colors z-10"
                aria-label="Previous image"
            >
                <ChevronLeft class="w-8 h-8" />
            </button>
            <button
                v-if="images.length > 1"
                @click.stop="next"
                class="absolute right-4 top-1/2 -translate-y-1/2 p-3 rounded-full hover:bg-white/10 text-white transition-colors z-10"
                aria-label="Next image"
            >
                <ChevronRight class="w-8 h-8" />
            </button>

            <!-- Image -->
            <div class="max-w-[90vw] max-h-[85vh] overflow-hidden flex items-center justify-center">
                <img
                    v-if="currentImage"
                    :src="currentImage.image_url || currentImage.url"
                    :alt="currentImage.title || 'Image'"
                    class="max-w-full max-h-[85vh] object-contain transition-transform duration-200 select-none"
                    :style="{ transform: `scale(${zoom}) translate(${offset.x}px, ${offset.y}px)` }"
                    draggable="false"
                    @dblclick="zoom === 1 ? zoomIn() : resetZoom()"
                />
            </div>

            <!-- Thumbnail Strip -->
            <div v-if="images.length > 1" class="absolute bottom-0 left-0 right-0 p-3 flex justify-center gap-1.5 overflow-x-auto">
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
                        :alt="img.title || ''"
                        class="w-full h-full object-cover"
                    />
                </button>
            </div>
        </div>
    </Teleport>
</template>
