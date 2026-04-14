<script setup>
/**
 * OutstreamAd — muted autoplay video ad rendered as a video-card-sized unit in the grid.
 *
 * Uses IntersectionObserver to:
 *   - Start playing when ≥50% of the card enters the viewport
 *   - Pause when it leaves
 * Fires impression on first play, click on CTA interaction.
 */
import { ref, onMounted, onUnmounted } from 'vue';
import { useFetch } from '@/Composables/useFetch';

const props = defineProps({
    ad: {
        type: Object,
        required: true,
    },
});

const { post } = useFetch();

const videoRef = ref(null);
const containerRef = ref(null);
const isPlaying = ref(false);
const impressionFired = ref(false);
let observer = null;

const fireImpression = () => {
    if (impressionFired.value || !props.ad?.id) return;
    impressionFired.value = true;
    post('/api/ad-impression', { ad_id: props.ad.id }).catch(() => {});
};

const fireClick = () => {
    if (!props.ad?.id) return;
    post('/api/ad-click', { ad_id: props.ad.id }).catch(() => {});
};

const handleClick = () => {
    fireClick();
    if (props.ad?.click_url) {
        window.open(props.ad.click_url, '_blank', 'noopener,noreferrer');
    }
};

const tryPlay = () => {
    if (!videoRef.value) return;
    videoRef.value.play().then(() => {
        isPlaying.value = true;
        fireImpression();
    }).catch(() => {});
};

const pause = () => {
    if (!videoRef.value) return;
    videoRef.value.pause();
    isPlaying.value = false;
};

onMounted(() => {
    if (!containerRef.value) return;

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    tryPlay();
                } else {
                    pause();
                }
            }
        },
        { threshold: 0.5 }
    );

    observer.observe(containerRef.value);
});

onUnmounted(() => {
    observer?.disconnect();
    pause();
});
</script>

<template>
    <div
        ref="containerRef"
        class="video-card group relative cursor-pointer overflow-hidden"
        @click="handleClick"
        :aria-label="`Ad: ${ad.name}`"
        role="link"
    >
        <!-- Thumbnail shown before video loads / while paused -->
        <div class="thumbnail relative overflow-hidden rounded-xl">
            <img
                v-if="ad.outstream_thumbnail && !isPlaying"
                :src="ad.outstream_thumbnail"
                :alt="ad.name"
                class="w-full h-full object-cover"
                loading="lazy"
            />

            <!-- Video element (muted autoplay) -->
            <video
                v-if="ad.type === 'mp4' && ad.content"
                ref="videoRef"
                :src="ad.content"
                class="w-full h-full object-cover"
                muted
                playsinline
                loop
                preload="none"
            />

            <!-- Ad badge -->
            <div class="absolute top-2 left-2 z-10">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-500 text-black uppercase tracking-wide">Ad</span>
            </div>

            <!-- Play indicator overlay when paused -->
            <div
                v-if="!isPlaying"
                class="absolute inset-0 flex items-center justify-center bg-black/30 transition-opacity duration-200"
            >
                <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                    <svg class="w-5 h-5 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
            </div>

            <!-- Gradient overlay -->
            <div class="absolute inset-0 bg-linear-to-t from-black/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        </div>

        <!-- Meta row -->
        <div class="flex gap-3 mt-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-medium text-sm leading-tight line-clamp-2" style="color: var(--color-text-primary);">
                    {{ ad.name }}
                </h3>
                <p class="text-xs mt-1" style="color: var(--color-text-muted);">Sponsored</p>
            </div>
        </div>
    </div>
</template>
