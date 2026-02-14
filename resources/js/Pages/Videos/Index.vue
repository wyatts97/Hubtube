<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import { Filter, X, ArrowUpDown, Clock, Flame, CalendarDays } from 'lucide-vue-next';
import { sanitizeHtml } from '@/Composables/useSanitize';
import { useAutoTranslate } from '@/Composables/useAutoTranslate';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const { translateVideos, tr } = useAutoTranslate(['title']);
const page = usePage();

const props = defineProps({
    videos: Object,
    categories: Array,
    filters: Object,
    bannerAd: { type: Object, default: () => ({}) },
    adSettings: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

const category = ref(props.filters?.category || '');
const sort = ref(props.filters?.sort || '');
const showFilters = ref(false);

const activeCategory = computed(() => {
    if (!category.value) return null;
    return props.categories?.find(c => c.id == category.value);
});

const applyFilters = () => {
    router.get('/videos', {
        category: category.value || undefined,
        sort: sort.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const setSort = (val) => {
    sort.value = val;
    applyFilters();
};

const setCategory = (id) => {
    category.value = id;
    showFilters.value = false;
    applyFilters();
};

const clearCategory = () => {
    category.value = '';
    applyFilters();
};

// Close filter dropdown on outside click
const filterRef = ref(null);
const onClickOutside = (e) => {
    if (filterRef.value && !filterRef.value.contains(e.target)) {
        showFilters.value = false;
    }
};
onMounted(() => {
    document.addEventListener('click', onClickOutside);
    const allVideos = props.videos?.data || [];
    if (allVideos.length) translateVideos(allVideos);
});
onUnmounted(() => document.removeEventListener('click', onClickOutside));

const withTranslation = (video) => {
    const title = tr(video, 'title');
    const translatedSlug = tr(video, 'translated_slug');
    if (title !== video.title || translatedSlug) {
        const override = { ...video, title };
        if (translatedSlug && translatedSlug !== video.slug) {
            override.translated_slug = translatedSlug;
        }
        return override;
    }
    return video;
};

const bannerEnabled = computed(() => {
    const e = props.bannerAd?.enabled;
    return e === true || e === 'true' || e === 1 || e === '1';
});
const bannerCode = computed(() => sanitizeHtml(props.bannerAd?.code || ''));

const adsEnabled = computed(() => {
    const enabled = props.adSettings?.videoGridEnabled;
    return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
});
const adCode = computed(() => sanitizeHtml(props.adSettings?.videoGridCode || ''));
const adFrequency = computed(() => parseInt(props.adSettings?.videoGridFrequency) || 8);
const shouldShowAd = (index, totalLength) => {
    if (!adsEnabled.value || !adCode.value.trim()) return false;
    return (index + 1) % adFrequency.value === 0 && index < totalLength - 1;
};

// Sponsored cards: insert at frequency intervals, cycling through available cards
const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);
const getSponsoredCard = (index) => {
    if (!props.sponsoredCards?.length) return null;
    if ((index + 1) % sponsoredFrequency.value !== 0) return null;
    const cardIndex = Math.floor((index + 1) / sponsoredFrequency.value) - 1;
    return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
};
</script>

<template>
    <Head title="Browse Videos" />

    <AppLayout>
        <!-- Top Ad Banner -->
        <div v-if="bannerEnabled && bannerCode" class="mb-4 flex justify-center">
            <div class="hidden sm:block" v-html="bannerCode"></div>
            <div class="sm:hidden" v-html="bannerCode"></div>
        </div>

        <div class="mb-5">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">{{ t('common.browse_videos') || 'Browse Videos' }}</h1>

                <div class="flex items-center gap-2">
                    <!-- Sort Buttons -->
                    <div class="flex items-center rounded-lg overflow-hidden" style="border: 1px solid var(--color-border);">
                        <button
                            @click="setSort('')"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors', !sort ? 'text-white' : '']"
                            :style="!sort ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary);'"
                        >
                            <Clock class="w-3.5 h-3.5 inline -mt-0.5 mr-1" />Latest
                        </button>
                        <button
                            @click="setSort('popular')"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors', sort === 'popular' ? 'text-white' : '']"
                            :style="sort === 'popular' ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary); border-left: 1px solid var(--color-border);'"
                        >
                            <Flame class="w-3.5 h-3.5 inline -mt-0.5 mr-1" />Popular
                        </button>
                        <button
                            @click="setSort('oldest')"
                            :class="['px-3 py-1.5 text-xs font-medium transition-colors', sort === 'oldest' ? 'text-white' : '']"
                            :style="sort === 'oldest' ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary); border-left: 1px solid var(--color-border);'"
                        >
                            <CalendarDays class="w-3.5 h-3.5 inline -mt-0.5 mr-1" />Oldest
                        </button>
                    </div>

                    <!-- Filter Button -->
                    <div ref="filterRef" class="relative">
                        <button
                            @click.stop="showFilters = !showFilters"
                            class="p-2 rounded-lg transition-colors flex items-center gap-1.5"
                            :style="category ? 'background-color: var(--color-accent); color: #fff;' : 'background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);'"
                        >
                            <Filter class="w-4 h-4" />
                            <span v-if="activeCategory" class="text-xs font-medium hidden sm:inline">{{ activeCategory.name }}</span>
                        </button>

                        <!-- Category Dropdown -->
                        <div
                            v-if="showFilters"
                            class="absolute right-0 top-full mt-2 w-56 rounded-lg shadow-xl z-50 py-1 max-h-80 overflow-y-auto"
                            style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
                        >
                            <button
                                @click="setCategory('')"
                                class="w-full text-left px-4 py-2 text-sm transition-colors hover:opacity-80"
                                :style="!category ? 'color: var(--color-accent); font-weight: 600;' : 'color: var(--color-text-primary);'"
                            >
                                {{ t('categories.all') || 'All Categories' }}
                            </button>
                            <div style="border-top: 1px solid var(--color-border); margin: 2px 0;"></div>
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                @click="setCategory(cat.id)"
                                class="w-full text-left px-4 py-2 text-sm transition-colors hover:opacity-80"
                                :style="category == cat.id ? 'color: var(--color-accent); font-weight: 600;' : 'color: var(--color-text-primary);'"
                            >
                                {{ cat.name }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Category Chip -->
            <div v-if="activeCategory" class="mt-3 flex items-center gap-2">
                <span class="text-xs px-3 py-1 rounded-full inline-flex items-center gap-1.5" style="background-color: var(--color-bg-secondary); color: var(--color-text-primary); border: 1px solid var(--color-border);">
                    {{ activeCategory.name }}
                    <button @click="clearCategory" class="hover:opacity-70">
                        <X class="w-3 h-3" />
                    </button>
                </span>
            </div>
        </div>

        <div v-if="videos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <template v-for="(video, index) in videos.data" :key="video.id">
                <VideoCard :video="withTranslation(video)" />
                <div
                    v-if="shouldShowAd(index, videos.data.length)"
                    class="col-span-1 flex items-start justify-center rounded-xl p-2"
                >
                    <div v-html="adCode"></div>
                </div>
                <SponsoredVideoCard
                    v-if="getSponsoredCard(index)"
                    :card="getSponsoredCard(index)"
                />
            </template>
        </div>

        <div v-else class="text-center py-16">
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('common.no_videos_found') || 'No videos found' }}</p>
            <p class="mt-2 text-sm" style="color: var(--color-text-muted);">{{ t('common.try_different') || 'Try adjusting your filters' }}</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center gap-1.5">
            <template v-for="link in videos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="['px-3 py-1.5 rounded-lg text-sm transition-colors']"
                    :style="link.active
                        ? 'background-color: var(--color-accent); color: #fff;'
                        : 'background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);'"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    class="px-3 py-1.5 rounded-lg text-sm"
                    style="color: var(--color-text-muted);"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
