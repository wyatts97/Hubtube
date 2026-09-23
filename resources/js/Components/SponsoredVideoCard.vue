<script setup>
/**
 * SponsoredVideoCard — the in-grid ad unit.
 *
 * Three creative types:
 *   - image: a video-card lookalike; preview images cycle on hover.
 *   - video: muted MP4 that plays while at least half the card is on screen.
 *   - html:  pasted ad network code or an <iframe>, scaled to the cell by
 *            GridAdSlot. No link wrapper — the creative handles its own clicks,
 *            and an iframe inside an <a> is invalid markup.
 */
import { usePage } from '@inertiajs/vue3';
import { computed, ref, onUnmounted } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import GridAdSlot from '@/Components/GridAdSlot.vue';

const page = usePage();
const { post } = useFetch();
const { t } = useI18n();

const props = defineProps({
    card: {
        type: Object,
        required: true,
    },
});

const cardEl = ref(null);
const videoEl = ref(null);
let impressionFired = false;

const isHtml = computed(() => props.card.type === 'html');
const isVideo = computed(() => props.card.type === 'video' && !!props.card.video_src);

const htmlAds = computed(() => [{ code: props.card.html_code, mobileCode: props.card.mobile_html_code }]);

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
    color: vc.value.metaColor || 'var(--color-text-muted)',
}));

const thumbRadius = computed(() => {
    const r = vc.value.borderRadius;
    return r !== undefined && r !== null ? `${r}px` : '12px';
});

// Preview images cycling on hover (image cards)
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

const currentImage = computed(() =>
    previewImages.value[currentImageIndex.value] || props.card.thumbnail_url || placeholderImg
);

const startPreview = () => {
    if (props.card.type !== 'image' || previewImages.value.length <= 1) return;
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

const handleClick = () => {
    if (props.card?.id) {
        post(`/api/sponsored/${props.card.id}/click`, { placement: 'sponsored_card' }).catch(() => {});
    }
};

const fireImpression = () => {
    if (impressionFired || !props.card?.id) return;
    impressionFired = true;
    post(`/api/sponsored/${props.card.id}/impression`, { placement: 'sponsored_card' }).catch(() => {});
};

// One observer: counts the impression once and, for video cards, plays only
// while at least half the card is visible.
const { stop: stopObserver } = useIntersectionObserver(
    cardEl,
    ([entry]) => {
        if (!entry) return;
        if (entry.isIntersecting) fireImpression();

        if (!isVideo.value) {
            if (entry.isIntersecting) stopObserver();
            return;
        }

        const video = videoEl.value;
        if (!video) return;
        if (entry.isIntersecting) {
            video.play().catch(() => {});
        } else {
            video.pause();
        }
    },
    { threshold: 0.5 }
);

onUnmounted(() => {
    if (previewInterval) clearInterval(previewInterval);
    videoEl.value?.pause();
});

// Price display
const displayPrice = computed(() => props.card.formatted_sale_price || props.card.formatted_price);
const originalPrice = computed(() => props.card.is_on_sale ? props.card.formatted_price : null);
</script>

<template>
    <!-- HTML creative: the network's code, scaled to the cell -->
    <div v-if="isHtml" ref="cardEl" class="sponsored-html">
        <GridAdSlot :ads="htmlAds" placement="" />
        <p class="mt-1 text-xs" :style="metaStyle">{{ t('common.sponsored') }}</p>
    </div>

    <!-- Image / video creative: looks like a video card -->
    <a
        v-else
        ref="cardEl"
        :href="card.click_url"
        target="_blank"
        rel="noopener noreferrer sponsored"
        class="video-card group"
        :aria-label="`${t('common.sponsored')}: ${card.title}`"
        @mouseenter="startPreview"
        @mouseleave="stopPreview"
        @click="handleClick"
    >
        <div class="thumbnail relative overflow-hidden" :style="{ borderRadius: thumbRadius }">
            <!-- Ad badge -->
            <div class="absolute top-2 start-2 z-20">
                <span class="ad-badge">Ad</span>
            </div>

            <!-- Sale Badge -->
            <div v-if="card.discount_percent" class="absolute top-2 end-2 z-20">
                <span class="sale-badge">-{{ card.discount_percent }}%</span>
            </div>

            <video
                v-if="isVideo"
                ref="videoEl"
                :src="card.video_src"
                :poster="card.thumbnail_url || undefined"
                class="w-full h-full object-cover"
                muted
                playsinline
                loop
                preload="none"
            />
            <img
                v-else
                :src="currentImage"
                :alt="card.title"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
                decoding="async"
                @error="(e) => e.target.src = placeholderImg"
            />

            <!-- Preview Progress Dots -->
            <div v-if="isHovering && previewImages.length > 1" class="absolute bottom-2 start-1/2 -translate-x-1/2 flex gap-1 z-10">
                <span
                    v-for="(_, idx) in previewImages"
                    :key="idx"
                    class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                    :class="idx === currentImageIndex ? 'bg-white w-4' : 'bg-white/50'"
                />
            </div>

            <!-- Duration Badge (no clock icon, matches regular video cards) -->
            <div v-if="card.formatted_duration" class="absolute bottom-2 end-2 z-10">
                <span class="duration-badge">{{ card.formatted_duration }}</span>
            </div>

            <!-- Gradient Overlay -->
            <div class="absolute inset-0 bg-linear-to-t from-black/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        </div>

        <div class="flex gap-3 mt-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-medium leading-tight" :class="`line-clamp-${vc.titleLines || 2}`" :style="titleStyle">
                    {{ card.title }}
                </h3>

                <!-- Studio, or description, followed by price when there is one -->
                <p v-if="card.studio || card.description || displayPrice" class="mt-1 flex items-center gap-2" :style="metaStyle">
                    <span v-if="card.studio || card.description" class="line-clamp-1 min-w-0" :class="{ 'flex-1': !card.studio }">
                        {{ card.studio || card.description }}
                    </span>
                    <template v-if="displayPrice">
                        <span v-if="card.studio" class="text-gray-500 dark:text-gray-600">•</span>
                        <span>{{ displayPrice }}</span>
                        <span v-if="originalPrice" class="line-through opacity-60">{{ originalPrice }}</span>
                    </template>
                </p>

                <p v-else class="mt-1" :style="metaStyle">
                    {{ t('common.sponsored') }}
                </p>
            </div>
        </div>
    </a>
</template>

<style scoped>
.ad-badge {
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
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
