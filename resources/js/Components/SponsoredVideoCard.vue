<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, ref, onUnmounted } from 'vue';
import { Clock, Tag } from 'lucide-vue-next';

const page = usePage();

const props = defineProps({
    card: {
        type: Object,
        required: true,
    },
});

const vc = computed(() => page.props.theme?.videoCard || {});

const placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23181818' width='640' height='360'/%3E%3Ctext x='50%25' y='50%25' fill='%23555' font-size='24' text-anchor='middle' dominant-baseline='middle'%3EAd%3C/text%3E%3C/svg%3E";

const titleStyle = computed(() => ({
    color: vc.value.titleColor || 'var(--color-text-primary)',
    fontFamily: vc.value.titleFont || undefined,
    fontSize: vc.value.titleSize ? `${vc.value.titleSize}px` : undefined,
    '-webkit-line-clamp': vc.value.titleLines || 2,
}));

const metaStyle = computed(() => ({
    fontFamily: vc.value.metaFont || undefined,
    fontSize: vc.value.metaSize ? `${vc.value.metaSize}px` : undefined,
}));

const metaColor = computed(() => vc.value.metaColor || 'var(--color-text-muted)');

const thumbRadius = computed(() => {
    const r = vc.value.borderRadius;
    return r !== undefined && r !== null ? `${r}px` : '12px';
});

// Preview images cycling on hover
const previewImages = computed(() => {
    const images = props.card.preview_images || [];
    if (images.length === 0 && props.card.thumbnail_url) {
        return [props.card.thumbnail_url];
    }
    return images;
});

const currentImageIndex = ref(0);
const isHovering = ref(false);
let previewInterval = null;

const currentImage = computed(() => {
    if (previewImages.value.length === 0) return props.card.thumbnail_url || placeholderImg;
    return previewImages.value[currentImageIndex.value] || props.card.thumbnail_url || placeholderImg;
});

const startPreview = () => {
    if (previewImages.value.length <= 1) return;
    isHovering.value = true;
    currentImageIndex.value = 0;
    previewInterval = setInterval(() => {
        currentImageIndex.value = (currentImageIndex.value + 1) % previewImages.value.length;
    }, 800);
};

const stopPreview = () => {
    isHovering.value = false;
    if (previewInterval) {
        clearInterval(previewInterval);
        previewInterval = null;
    }
    currentImageIndex.value = 0;
};

onUnmounted(() => {
    if (previewInterval) clearInterval(previewInterval);
});

// Ribbon text
const ribbonText = computed(() => {
    const suffix = props.card.ribbon_text || '';
    return suffix ? `Featured ${suffix}` : 'Featured';
});

// Price display
const hasPrice = computed(() => props.card.price || props.card.formatted_price);
const displayPrice = computed(() => props.card.formatted_sale_price || props.card.formatted_price);
const originalPrice = computed(() => props.card.is_on_sale ? props.card.formatted_price : null);
const discountPercent = computed(() => props.card.discount_percent);
</script>

<template>
    <a
        :href="card.click_url"
        target="_blank"
        rel="noopener noreferrer sponsored"
        class="video-card group"
        :aria-label="`Sponsored: ${card.title}`"
        @mouseenter="startPreview"
        @mouseleave="stopPreview"
    >
        <div class="thumbnail relative overflow-hidden" :style="{ borderRadius: thumbRadius }">
            <!-- Featured Ribbon -->
            <div class="absolute top-0 left-0 z-20">
                <div class="featured-ribbon">
                    <span class="featured-ribbon-text">{{ ribbonText }}</span>
                </div>
            </div>

            <!-- Sale Badge -->
            <div v-if="discountPercent" class="absolute top-2 right-2 z-20">
                <span class="sale-badge">-{{ discountPercent }}%</span>
            </div>

            <!-- Preview Image -->
            <img
                :src="currentImage"
                :alt="card.title"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
                decoding="async"
                @error="(e) => e.target.src = placeholderImg"
            />

            <!-- Preview Progress Dots -->
            <div v-if="isHovering && previewImages.length > 1" class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1 z-10">
                <span
                    v-for="(_, idx) in previewImages"
                    :key="idx"
                    class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                    :class="idx === currentImageIndex ? 'bg-white w-4' : 'bg-white/50'"
                />
            </div>

            <!-- Duration Badge -->
            <div v-if="card.formatted_duration" class="absolute bottom-2 right-2 z-10">
                <span class="duration-badge flex items-center gap-1">
                    <Clock class="w-3 h-3" />
                    {{ card.formatted_duration }}
                </span>
            </div>

            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        </div>

        <div class="flex gap-3 mt-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-medium leading-tight" :class="`line-clamp-${vc.titleLines || 2}`" :style="titleStyle">
                    {{ card.title }}
                </h3>
                
                <!-- Studio / Brand -->
                <p v-if="card.studio" class="mt-1 text-sm" :style="{ color: metaColor }">
                    {{ card.studio }}
                </p>

                <!-- Price Display -->
                <div v-if="hasPrice" class="mt-2 flex items-center gap-2">
                    <span class="text-lg font-bold text-emerald-500">{{ displayPrice }}</span>
                    <span v-if="originalPrice" class="text-sm line-through" :style="{ color: metaColor }">
                        {{ originalPrice }}
                    </span>
                </div>

                <!-- Description or Sponsored label -->
                <p v-else-if="card.description" class="mt-1 text-sm line-clamp-2" :style="{ color: metaColor }">
                    {{ card.description }}
                </p>
                <p v-else class="mt-1 flex items-center gap-1 text-sm" :style="{ color: metaColor }">
                    <Tag class="w-3 h-3" />
                    Sponsored
                </p>
            </div>
        </div>
    </a>
</template>

<style scoped>
.featured-ribbon {
    position: relative;
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    color: white;
    padding: 6px 16px 6px 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    clip-path: polygon(0 0, 100% 0, calc(100% - 8px) 100%, 0 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.featured-ribbon::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 4px;
    height: 4px;
    background: #7f1d1d;
    clip-path: polygon(0 0, 100% 0, 100% 100%);
}

.sale-badge {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.duration-badge {
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}
</style>
