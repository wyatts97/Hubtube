<script setup>
import { Link, router } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { sanitizeHtml } from '@/Composables/useSanitize';

const { t, localizedUrl } = useI18n();

const props = defineProps({
    category: Object,
    translatedName: { type: String, default: null },
    translatedDescription: { type: String, default: null },
    videos: Object,
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

const bannerEnabled = computed(() => {
    const e = props.bannerAd?.enabled;
    return e === true || e === 'true' || e === 1 || e === '1';
});
const bannerCode = computed(() => sanitizeHtml(props.bannerAd?.code || ''));

const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);
const getSponsoredCard = (index) => {
    if (!props.sponsoredCards?.length) return null;
    if ((index + 1) % sponsoredFrequency.value !== 0) return null;
    const cardIndex = Math.floor((index + 1) / sponsoredFrequency.value) - 1;
    return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
};

const displayName = props.translatedName || props.category.name;
const displayDescription = props.translatedDescription || props.category.description;

const goToPage = (pageNum) => {
    router.get(localizedUrl(`/category/${props.category.slug}`), { page: pageNum }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <!-- Top Ad Banner -->
        <div v-if="bannerEnabled && bannerCode" class="mb-4 flex justify-center">
            <div v-html="bannerCode"></div>
        </div>

        <div class="mb-6">
            <div class="flex items-center gap-2 mb-1">
                <Link :href="localizedUrl('/categories')" class="text-sm hover:opacity-80" style="color: var(--color-accent);">{{ t('categories.title') || 'Categories' }}</Link>
                <span style="color: var(--color-text-muted);">/</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ displayName }}</h1>
                <span class="text-sm" style="color: var(--color-text-muted);">•</span>
                <span class="text-sm" style="color: var(--color-text-muted);">{{ t('categories.video_count', { count: videos.total || 0 }) || `${videos.total || 0} videos` }}</span>
            </div>
            <p v-if="displayDescription" class="text-sm mt-1" style="color: var(--color-text-muted);">{{ displayDescription }}</p>
        </div>

        <div v-if="videos.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <template v-for="(video, index) in videos.data" :key="video.id">
                <VideoCard :video="video" />
                <SponsoredVideoCard
                    v-if="getSponsoredCard(index)"
                    :card="getSponsoredCard(index)"
                />
            </template>
        </div>

        <div v-else class="text-center py-12">
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('categories.no_videos') || 'No videos in this category yet' }}</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.last_page > 1" class="flex justify-center items-center gap-2 mt-8">
            <button
                @click="goToPage(videos.current_page - 1)"
                :disabled="videos.current_page === 1"
                class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
            >
                <ChevronLeft class="w-5 h-5" />
            </button>
            <div class="flex items-center gap-1">
                <template v-for="pageNum in videos.last_page" :key="pageNum">
                    <button
                        v-if="pageNum === 1 || pageNum === videos.last_page || (pageNum >= videos.current_page - 2 && pageNum <= videos.current_page + 2)"
                        @click="goToPage(pageNum)"
                        class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                        :style="pageNum === videos.current_page
                            ? { backgroundColor: 'var(--color-accent)', color: 'white' }
                            : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                    >
                        {{ pageNum }}
                    </button>
                    <span
                        v-else-if="pageNum === videos.current_page - 3 || pageNum === videos.current_page + 3"
                        style="color: var(--color-text-muted);"
                    >...</span>
                </template>
            </div>
            <button
                @click="goToPage(videos.current_page + 1)"
                :disabled="videos.current_page === videos.last_page"
                class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
            >
                <ChevronRight class="w-5 h-5" />
            </button>
        </div>
    </AppLayout>
</template>
