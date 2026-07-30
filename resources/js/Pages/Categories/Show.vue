<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import AdSlot from '@/Components/AdSlot.vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { useVideoGrid } from '@/Composables/useVideoGrid';
import BannerAd from '@/Components/UI/BannerAd.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';

const { t, localizedUrl } = useI18n();
const { gridClass } = useVideoGrid();

const props = defineProps({
    category: Object,
    translatedName: { type: String, default: null },
    translatedDescription: { type: String, default: null },
    videos: Object,
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
    adSettings: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

const adsEnabled = computed(() => {
    const enabled = props.adSettings?.videoGridEnabled;
    return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
});
const gridAds = computed(() => props.adSettings?.videoGridAds || []);
const adFrequency = computed(() => parseInt(props.adSettings?.videoGridFrequency) || 8);

const getGridAd = () => {
    const ads = gridAds.value;
    if (!ads.length) return { code: '', mobileCode: '' };
    return ads[Math.floor(Math.random() * ads.length)];
};

const shouldShowAd = (index, totalLength) => {
    if (!adsEnabled.value || !gridAds.value.length) return false;
    return (index + 1) % adFrequency.value === 0 && index < totalLength - 1;
};

const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);
const sponsoredOffset = computed(() => Math.floor(adFrequency.value / 2));
const getSponsoredCard = (index) => {
    if (!props.sponsoredCards?.length) return null;
    if ((index + 1 + sponsoredOffset.value) % sponsoredFrequency.value !== 0) return null;
    const cardIndex = Math.floor((index + 1 + sponsoredOffset.value) / sponsoredFrequency.value) - 1;
    return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
};

const displayName = props.translatedName || props.category.name;
const breadcrumbs = computed(() => [
    { label: t('categories.title') || 'Categories', href: localizedUrl('/categories') },
    { label: displayName },
]);
const displayDescription = props.translatedDescription || props.category.description;

const goToPage = (pageNum) => {
    router.get(localizedUrl(`/category/${props.category.slug}`), { page: pageNum }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <BannerAd :config="bannerAd" />
        <Breadcrumbs :items="breadcrumbs" />

        <div class="mb-6">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-text-primary">{{ displayName }}</h1>
                <span class="text-sm text-text-muted">•</span>
                <span class="text-sm text-text-muted">{{ t('categories.video_count', { count: videos.total || 0 }) || `${videos.total || 0} videos` }}</span>
            </div>
            <p v-if="displayDescription" class="text-sm mt-1 text-text-muted">{{ displayDescription }}</p>
        </div>

        <div v-if="videos.data?.length" :class="gridClass">
            <template v-for="(video, index) in videos.data" :key="video.id">
                <VideoCard :video="video" />
                <div
                    v-if="shouldShowAd(index, videos.data.length)"
                    class="col-span-1 flex items-start justify-center rounded-xl p-2"
                >
                    <AdSlot :html="getGridAd().code" class="hidden sm:block" />
                    <AdSlot :html="getGridAd().mobileCode" class="sm:hidden" />
                </div>
                <SponsoredVideoCard
                    v-if="getSponsoredCard(index)"
                    :card="getSponsoredCard(index)"
                />
            </template>
        </div>

        <div v-else class="text-center py-12">
            <p class="text-lg text-text-secondary">{{ t('categories.no_videos') || 'No videos in this category yet' }}</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.last_page > 1" class="flex justify-center items-center gap-2 mt-8">
            <button
                @click="goToPage(videos.current_page - 1)"
                :disabled="videos.current_page === 1"
                class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                aria-label="Previous page"
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
                        class="text-text-muted"
                    >...</span>
                </template>
            </div>
            <button
                @click="goToPage(videos.current_page + 1)"
                :disabled="videos.current_page === videos.last_page"
                class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                aria-label="Next page"
            >
                <ChevronRight class="w-5 h-5" />
            </button>
        </div>
    </AppLayout>
</template>
