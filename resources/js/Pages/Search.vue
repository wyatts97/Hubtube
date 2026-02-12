<script setup>
import { router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, watch, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Search as SearchIcon, Users, Hash } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { useAutoTranslate } from '@/Composables/useAutoTranslate';
import { useI18n } from '@/Composables/useI18n';
import { useVirtualGrid } from '@/Composables/useVirtualGrid';

const { t } = useI18n();

const { translateVideos, tr } = useAutoTranslate(['title']);

const props = defineProps({
    query: String,
    type: String,
    results: Object,
    seo: { type: Object, default: () => ({}) },
});

const searchQuery = ref(props.query || '');
const activeType = ref(props.type || 'videos');

const tabs = computed(() => [
    { key: 'videos', label: t('search.videos') || 'Videos', icon: SearchIcon },
    { key: 'channels', label: t('search.channels') || 'Channels', icon: Users },
    { key: 'hashtags', label: t('search.hashtags') || 'Hashtags', icon: Hash },
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
        <div class="mb-4 sm:mb-6">
            <h1 class="text-xl sm:text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('search.title') || 'Search' }}</h1>
        </div>

        <!-- Search Bar -->
        <form @submit.prevent="submitSearch" class="mb-4 sm:mb-6">
            <div class="relative max-w-2xl">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="t('search.placeholder') || 'Search videos, channels, hashtags...'"
                    class="input pr-12"
                />
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full hover:opacity-80" style="color: var(--color-text-muted);">
                    <SearchIcon class="w-5 h-5" />
                </button>
            </div>
        </form>

        <!-- Tabs -->
        <div class="flex gap-1 mb-4 sm:mb-6 border-b overflow-x-auto scrollbar-hide" style="border-color: var(--color-border);">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="switchTab(tab.key)"
                class="flex items-center gap-2 px-3 sm:px-4 py-3 text-sm font-medium border-b-2 transition-colors -mb-px whitespace-nowrap shrink-0"
                :style="activeType === tab.key
                    ? { borderColor: 'var(--color-accent)', color: 'var(--color-accent)' }
                    : { borderColor: 'transparent', color: 'var(--color-text-secondary)' }"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Results -->
        <div v-if="query">
            <p class="text-sm mb-4" style="color: var(--color-text-secondary);">
                {{ t('common.results_for') || 'Results for' }} "<span class="font-medium" style="color: var(--color-text-primary);">{{ query }}</span>"
            </p>

            <!-- Video Results -->
            <template v-if="activeType === 'videos'">
                <div
                    v-if="videoItems.length"
                    v-bind="containerProps"
                    :style="[containerProps.style, { height: '70vh' }]"
                    class="rounded-xl border overflow-auto"
                    style="border-color: var(--color-border);"
                >
                    <div v-bind="wrapperProps">
                        <div v-for="row in virtualRows" :key="row.index" :style="gridStyle" class="px-2 pb-4">
                            <VideoCard v-for="video in row.data" :key="video.id" :video="video" />
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <SearchIcon class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('common.no_videos_found') || 'No videos found' }}</p>
                    <p class="mt-1" style="color: var(--color-text-muted);">{{ t('common.try_different') || 'Try different keywords' }}</p>
                </div>
            </template>

            <!-- Channel Results -->
            <template v-else-if="activeType === 'channels'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a
                        v-for="channel in resultsList()"
                        :key="channel.id"
                        :href="`/channel/${channel.username}`"
                        class="card p-4 flex items-center gap-4 hover:opacity-90 transition-opacity"
                    >
                        <div class="w-14 h-14 rounded-full overflow-hidden shrink-0" style="background-color: var(--color-bg-secondary);">
                            <img :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" loading="lazy" decoding="async" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-medium truncate" style="color: var(--color-text-primary);">
                                {{ channel.username }}
                                <span v-if="channel.is_verified" class="ml-1">✓</span>
                            </h3>
                            <p class="text-sm" style="color: var(--color-text-secondary);">
                                {{ channel.channel?.name || channel.username }}
                            </p>
                            <p class="text-sm" style="color: var(--color-text-muted);">
                                {{ channel.subscriber_count || 0 }} {{ t('common.subscribers') || 'subscribers' }}
                            </p>
                        </div>
                    </a>
                </div>
                <div v-else class="text-center py-12">
                    <Users class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('common.no_channels_found') || 'No channels found' }}</p>
                </div>
            </template>

            <!-- Hashtag Results -->
            <template v-else-if="activeType === 'hashtags'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="hashtag in resultsList()"
                        :key="hashtag.id"
                        class="card p-4"
                    >
                        <h3 class="font-medium" style="color: var(--color-accent);">#{{ hashtag.name }}</h3>
                        <p class="text-sm mt-1" style="color: var(--color-text-muted);">{{ hashtag.usage_count || 0 }} {{ t('common.videos') || 'videos' }}</p>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <Hash class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('common.no_hashtags_found') || 'No hashtags found' }}</p>
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
            <SearchIcon class="w-16 h-16 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('common.search_prompt') || 'Search for videos, channels, and more' }}</p>
        </div>
    </AppLayout>
</template>
