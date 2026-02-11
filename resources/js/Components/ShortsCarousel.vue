<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Zap, Play } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    shorts: { type: Array, required: true },
});

const placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='360' height='640' viewBox='0 0 360 640'%3E%3Crect fill='%23181818' width='360' height='640'/%3E%3Cpolygon fill='%23333' points='155,270 155,370 215,320'/%3E%3C/svg%3E";

const scrollContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(true);
const hoveredId = ref(null);
const previewLoaded = ref({});

const updateScrollState = () => {
    if (!scrollContainer.value) return;
    const el = scrollContainer.value;
    canScrollLeft.value = el.scrollLeft > 10;
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 10;
};

const scroll = (direction) => {
    if (!scrollContainer.value) return;
    const scrollAmount = scrollContainer.value.clientWidth * 0.75;
    scrollContainer.value.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth',
    });
};

const onPreviewLoad = (id) => {
    previewLoaded.value[id] = true;
};

onMounted(() => {
    if (scrollContainer.value) {
        scrollContainer.value.addEventListener('scroll', updateScrollState, { passive: true });
        updateScrollState();
    }
});

onUnmounted(() => {
    if (scrollContainer.value) {
        scrollContainer.value.removeEventListener('scroll', updateScrollState);
    }
});

const formattedDuration = (duration) => {
    if (!duration) return '0:00';
    const d = Math.floor(duration);
    const m = Math.floor(d / 60);
    const s = d % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
};
</script>

<template>
    <section class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold flex items-center gap-2" style="color: var(--color-text-primary);">
                <Zap class="w-5 h-5" style="color: var(--color-accent);" />
                {{ t('channel.shorts') || 'Shorts' }}
            </h2>
            <Link href="/shorts" class="text-sm font-medium" style="color: var(--color-accent);">{{ t('common.view_all') || 'View All' }}</Link>
        </div>

        <div class="relative group">
            <!-- Left Arrow -->
            <button
                v-if="canScrollLeft"
                @click="scroll('left')"
                class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-lg"
                style="background-color: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border);"
            >
                <ChevronLeft class="w-5 h-5" />
            </button>

            <!-- Scroll Container -->
            <div
                ref="scrollContainer"
                class="flex gap-3 overflow-x-auto scrollbar-hide scroll-smooth pb-2"
            >
                <Link
                    v-for="short in shorts"
                    :key="short.id"
                    :href="`/shorts/${short.id}`"
                    class="flex-shrink-0 group/card cursor-pointer"
                    style="width: 180px;"
                    @mouseenter="hoveredId = short.id"
                    @mouseleave="hoveredId = null"
                >
                    <!-- Thumbnail (9:16 aspect ratio) -->
                    <div
                        class="relative rounded-xl overflow-hidden"
                        style="aspect-ratio: 9/16; background-color: var(--color-bg-card);"
                    >
                        <!-- Static Thumbnail -->
                        <img
                            :src="short.thumbnail_url || short.thumbnail || placeholderImg"
                            :alt="short.title"
                            loading="lazy"
                            class="w-full h-full object-cover transition-opacity duration-200"
                            :class="{ 'opacity-0': hoveredId === short.id && short.preview_url && previewLoaded[short.id] }"
                            @error="(e) => e.target.src = placeholderImg"
                        />

                        <!-- Animated Preview on Hover -->
                        <img
                            v-if="short.preview_url"
                            :src="hoveredId === short.id ? short.preview_url : ''"
                            :alt="short.title"
                            class="absolute inset-0 w-full h-full object-cover transition-opacity duration-200"
                            :class="hoveredId === short.id && previewLoaded[short.id] ? 'opacity-100' : 'opacity-0'"
                            @load="onPreviewLoad(short.id)"
                        />

                        <!-- Play icon overlay on hover -->
                        <div
                            class="absolute inset-0 flex items-center justify-center transition-opacity duration-200"
                            :class="hoveredId === short.id ? 'opacity-100' : 'opacity-0'"
                            style="background: rgba(0,0,0,0.15);"
                        >
                            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: rgba(0,0,0,0.5);">
                                <Play class="w-6 h-6 text-white ml-0.5" fill="currentColor" />
                            </div>
                        </div>

                        <!-- Bottom gradient -->
                        <div class="absolute bottom-0 left-0 right-0 h-1/3 bg-gradient-to-t from-black/70 to-transparent pointer-events-none"></div>

                        <!-- Duration badge -->
                        <span class="absolute bottom-2 right-2 bg-black/80 text-white text-xs font-medium px-1.5 py-0.5 rounded">
                            {{ short.duration_formatted || short.formatted_duration || formattedDuration(short.duration) }}
                        </span>

                        <!-- Title overlay at bottom -->
                        <div class="absolute bottom-0 left-0 right-0 p-2 pt-6">
                            <p class="text-white text-xs font-medium line-clamp-2 leading-tight">{{ short.title }}</p>
                        </div>
                    </div>

                    <!-- Channel info below -->
                    <div class="flex items-center gap-2 mt-2 px-0.5">
                        <div class="w-5 h-5 rounded-full overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-card);">
                            <img v-if="short.user?.avatar" :src="short.user.avatar" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-white text-[10px] font-bold" style="background-color: var(--color-accent);">
                                {{ short.user?.username?.charAt(0)?.toUpperCase() }}
                            </div>
                        </div>
                        <span class="text-xs truncate" style="color: var(--color-text-secondary);">{{ short.user?.username }}</span>
                    </div>
                </Link>
            </div>

            <!-- Right Arrow -->
            <button
                v-if="canScrollRight"
                @click="scroll('right')"
                class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-10 h-10 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-lg"
                style="background-color: var(--color-bg-card); color: var(--color-text-primary); border: 1px solid var(--color-border);"
            >
                <ChevronRight class="w-5 h-5" />
            </button>
        </div>
    </section>
</template>
