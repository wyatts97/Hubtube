<script setup>
import { router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import FilterRail from '@/Components/UI/FilterRail.vue';
import { ref, watch, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import VideoCardSkeleton from '@/Components/VideoCardSkeleton.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import { Search as SearchIcon, Users, Hash } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { useAutoTranslate } from '@/Composables/useAutoTranslate';
import { useI18n } from '@/Composables/useI18n';
import { useVirtualGrid } from '@/Composables/useVirtualGrid';
import AdSlot from '@/Components/AdSlot.vue';

const { t } = useI18n();

const { translateVideos, tr } = useAutoTranslate(['title']);

const props = defineProps({
    query: String,
    type: String,
    results: Object,
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);
const getSponsoredCard = (index) => {
    if (!props.sponsoredCards?.length) return null;
    if ((index + 1) % sponsoredFrequency.value !== 0) return null;
    const cardIndex = Math.floor((index + 1) / sponsoredFrequency.value) - 1;
    return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
};

const bannerEnabled = computed(() => !!props.bannerAd?.enabled);
const desktopBannerHtml = computed(() => {
    if (props.bannerAd?.image && !props.bannerAd?.code) {
        const img = `<img src="${props.bannerAd.image}" alt="Ad" style="max-width:728px;height:auto;">`;
        return props.bannerAd.link ? `<a href="${props.bannerAd.link}" target="_blank" rel="sponsored noopener">${img}</a>` : img;
    }
    return props.bannerAd?.code || '';
});
const mobileBannerHtml = computed(() => {
    if (props.bannerAd?.mobileImage && !props.bannerAd?.mobileCode) {
        const img = `<img src="${props.bannerAd.mobileImage}" alt="Ad" style="max-width:300px;height:auto;">`;
        return props.bannerAd.mobileLink ? `<a href="${props.bannerAd.mobileLink}" target="_blank" rel="sponsored noopener">${img}</a>` : img;
    }
    return props.bannerAd?.mobileCode || props.bannerAd?.code || '';
});

const isInitialLoad = ref(true);
onMounted(() => { setTimeout(() => { isInitialLoad.value = false; }, 100); });

const searchQuery = ref(props.query || '');
const activeType = ref(props.type || 'videos');

/**
 * Search had no filtering at all before this. State mirrors the query string so
 * a filtered search is shareable and survives back/forward.
 */
const activeFilters = ref({
    sort: props.filters?.sort || '',
    duration: props.filters?.duration || '',
    quality: props.filters?.quality || '',
    date: props.filters?.date || '',
    category: props.filters?.category ? String(props.filters.category) : '',
});

watch(() => props.filters, (next) => {
    activeFilters.value = {
        sort: next?.sort || '',
        duration: next?.duration || '',
        quality: next?.quality || '',
        date: next?.date || '',
        category: next?.category ? String(next.category) : '',
    };
}, { deep: true });

const applyFilters = (next) => {
    activeFilters.value = next;

    const params = { q: searchQuery.value, type: activeType.value };
    for (const [key, value] of Object.entries(next)) {
        if (value !== '' && value != null) params[key] = value;
    }

    router.get('/search', params, { preserveState: true, preserveScroll: true });
};

const tabs = computed(() => [
    { key: 'videos', label: t('search.videos'), icon: SearchIcon },
    { key: 'channels', label: t('search.channels'), icon: Users },
    { key: 'hashtags', label: t('search.hashtags'), icon: Hash },
]);

const switchTab = (type) => {
    activeType.value = type;
    router.get('/search', { q: searchQuery.value, type }, { preserveState: true });
};

const submitSearch = () => {
    if (searchQuery.value.trim()) {
        router.get('/search', { q: searchQuery.value, type: activeType.value }, { preserveState: true });
    }
};

const goToPage = (pageNum) => {
    router.get('/search', { q: searchQuery.value, type: activeType.value, page: pageNum }, { preserveState: true, preserveScroll: false });
};

const resultsList = () => {
    if (!props.results) return [];
    return props.results.data || props.results || [];
};

const hasPages = () => {
    return props.results?.last_page && props.results.last_page > 1;
};

onMounted(() => {
    if (props.type === 'videos') {
        const allVideos = props.results?.data || props.results || [];
        if (allVideos.length) translateVideos(allVideos);
    }
});

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

const videoItems = computed(() => resultsList().map(withTranslation));
const { virtualRows, containerProps, wrapperProps, gridStyle } = useVirtualGrid(videoItems, {
    itemHeight: 320,
    overscan: 6,
});
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <!-- Top Ad Banner -->
        <div v-if="bannerEnabled && (desktopBannerHtml || mobileBannerHtml)" class="mb-4 flex justify-center">
            <AdSlot :html="desktopBannerHtml" class="hidden sm:block" />
            <AdSlot :html="mobileBannerHtml" class="sm:hidden" />
        </div>

        <div class="mb-4 sm:mb-6">
            <h1 class="page-title">{{ t('search.title') }}</h1>
        </div>

        <!-- Search Bar -->
        <form @submit.prevent="submitSearch" class="mb-4 sm:mb-6">
            <div class="relative max-w-2xl">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('search.placeholder')"
                    class="input pe-12"
                />
                <button type="submit" class="absolute end-2 top-1/2 -translate-y-1/2 p-2 rounded-full hover:opacity-80 text-text-muted">
                    <SearchIcon class="w-5 h-5" />
                </button>
            </div>
        </form>

        <!-- Tabs -->
        <div class="tab-strip mb-3 border-b border-border">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
                :class="activeType === tab.key
                    ? 'border-accent text-accent-text'
                    : 'border-transparent text-text-secondary hover:text-text-primary'"
                :aria-current="activeType === tab.key ? 'page' : undefined"
                @click="switchTab(tab.key)"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Filters apply to video results only; channels and hashtags have no
             duration or quality to filter on. -->
        <FilterRail
            v-if="activeType === 'videos'"
            :model-value="activeFilters"
            :categories="categories"
            class="mb-4"
            @update:model-value="applyFilters"
        />

        <!-- Results -->
        <div v-if="query">
            <p class="text-sm mb-4 text-text-secondary">
                {{ t('common.results_for') }} "<span class="font-medium text-text-primary">{{ query }}</span>"
            </p>

            <!-- Skeleton Loading -->
            <div v-if="isInitialLoad && activeType === 'videos'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCardSkeleton v-for="i in 8" :key="'skeleton-' + i" />
            </div>

            <!-- Video Results -->
            <template v-if="!isInitialLoad && activeType === 'videos'">
                <!-- Sponsored Cards (above video results) -->
                <div v-if="sponsoredCards.length && videoItems.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
                    <SponsoredVideoCard v-for="card in sponsoredCards.slice(0, 2)" :key="'sp-' + card.id" :card="card" />
                </div>
                <div
                    v-if="videoItems.length"
                    v-bind="containerProps"
                    :style="[containerProps.style, { height: '70vh' }]"
                    class="rounded-xl border overflow-auto border-border"
                >
                    <div v-bind="wrapperProps">
                        <div v-for="row in virtualRows" :key="row.index" :style="gridStyle" class="px-2 pb-4">
                            <VideoCard v-for="video in row.data" :key="video.id" :video="video" />
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <SearchIcon class="w-12 h-12 mx-auto mb-4 text-text-muted" />
                    <p class="text-lg text-text-secondary">{{ t('common.no_videos_found') }}</p>
                    <p class="mt-1 text-text-muted">{{ t('common.try_different') }}</p>
                </div>
            </template>

            <!-- Channel Results -->
            <template v-if="activeType === 'channels'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a
                        v-for="channel in resultsList()"
                        :key="channel.id"
                        :href="`/channel/${channel.username}`"
                        class="card p-4 flex items-center gap-4 hover:opacity-90 transition-opacity"
                    >
                        <div class="w-14 h-14 rounded-full overflow-hidden shrink-0 bg-bg-secondary">
                            <img :src="channel.avatar_url || channel.avatar || '/assets/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" loading="lazy" decoding="async" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-medium truncate text-text-primary">
                                {{ channel.username }}
                                <span v-if="channel.is_verified" class="ms-1">✓</span>
                            </h3>
                            <p class="text-sm text-text-secondary">
                                {{ channel.channel?.name || channel.username }}
                            </p>
                            <p class="text-sm text-text-muted">
                                {{ channel.subscriber_count || 0 }} {{ t('common.subscribers') }}
                            </p>
                        </div>
                    </a>
                </div>
                <div v-else class="text-center py-12">
                    <Users class="w-12 h-12 mx-auto mb-4 text-text-muted" />
                    <p class="text-lg text-text-secondary">{{ t('common.no_channels_found') }}</p>
                </div>
            </template>

            <!-- Hashtag Results -->
            <template v-if="activeType === 'hashtags'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="hashtag in resultsList()"
                        :key="hashtag.id"
                        class="card p-4"
                    >
                        <h3 class="font-medium text-accent-text">#{{ hashtag.name }}</h3>
                        <p class="text-sm mt-1 text-text-muted">{{ hashtag.usage_count || 0 }} {{ t('common.videos') }}</p>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <Hash class="w-12 h-12 mx-auto mb-4 text-text-muted" />
                    <p class="text-lg text-text-secondary">{{ t('common.no_hashtags_found') }}</p>
                </div>
            </template>

            <Pagination
                v-if="hasPages()"
                :current-page="results.current_page"
                :last-page="results.last_page"
                @page-change="goToPage"
            />
        </div>

        <!-- No Query State -->
        <div v-else class="text-center py-16">
            <SearchIcon class="w-16 h-16 mx-auto mb-4 text-text-muted" />
            <p class="text-lg text-text-secondary">{{ t('common.search_prompt') }}</p>
        </div>
    </AppLayout>
</template>
