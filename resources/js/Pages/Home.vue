<script setup>
import { usePage, router } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import ShortsRail from '@/Components/ShortsRail.vue';
import ImagesRail from '@/Components/ImagesRail.vue';
import PlaylistsRail from '@/Components/PlaylistsRail.vue';
import { Loader2 } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import GridAdSlot from '@/Components/GridAdSlot.vue';
import BannerAd from '@/Components/UI/BannerAd.vue';
import { useI18n } from '@/Composables/useI18n';
import { useAutoTranslate } from '@/Composables/useAutoTranslate';
import { useVideoGrid } from '@/Composables/useVideoGrid';

const { t, localizedUrl } = useI18n();
const { translateVideos, tr } = useAutoTranslate(['title']);
const { gridClass, mobileGrid } = useVideoGrid();

const props = defineProps({
    featuredVideos: Array,
    latestVideos: Object, // Now a paginated object
    popularVideos: Array,
    categories: Array,
    shortsPreview: { type: Array, default: () => [] },
    latestImages: { type: Array, default: () => [] },
    latestPlaylists: { type: Array, default: () => [] },
    adSettings: Object, // Ad settings from admin
    seo: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

const page = usePage();
const infiniteScrollEnabled = computed(() => page.props.app?.infinite_scroll_enabled ?? false);

// Auto-translate video titles when viewing in a non-default locale
onMounted(() => {
    const allVideos = [
        ...(props.featuredVideos || []),
        ...(props.latestVideos?.data || []),
        ...(props.popularVideos || []),
    ];
    if (allVideos.length) translateVideos(allVideos);
});

// Helper to create a video object with translated title + slug for VideoCard
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

// Infinite scroll state
const videos = ref([...(props.latestVideos?.data || [])]);
const currentPage = ref(props.latestVideos?.current_page || 1);
const lastPage = ref(props.latestVideos?.last_page || 1);
const loading = ref(false);
const hasMore = computed(() => currentPage.value < lastPage.value);

// Load more videos for infinite scroll — uses Inertia's native partial-reload
// API (only the `latestVideos` prop is re-requested and re-rendered) instead of
// a hand-rolled fetch against a separate JSON endpoint, so it gets Inertia's
// built-in request de-duplication/CSRF handling for free.
const loadMore = () => {
    if (loading.value || !hasMore.value) return;

    loading.value = true;
    router.reload({
        only: ['latestVideos'],
        data: { page: currentPage.value + 1 },
        preserveScroll: true,
        preserveState: true,
        onSuccess: (visitedPage) => {
            const newVideos = visitedPage.props.latestVideos;
            if (newVideos?.data) {
                videos.value.push(...newVideos.data);
                currentPage.value = newVideos.current_page;
                lastPage.value = newVideos.last_page;
            } else {
                console.error('Invalid load-more response:', newVideos);
            }
        },
        onError: (errors) => {
            console.error('Failed to load more videos:', errors);
        },
        onFinish: () => {
            loading.value = false;
        },
    });
};

// Infinite scroll observer — attach when loadMoreTrigger becomes available (after isInitialLoad)
let observer = null;
const loadMoreTrigger = ref(null);

const setupObserver = () => {
    if (!infiniteScrollEnabled.value || !loadMoreTrigger.value) return;
    if (observer) observer.disconnect();

    observer = new IntersectionObserver(
        (entries) => {
            const entry = entries?.length > 0 ? entries[0] : null;
            if (entry?.isIntersecting && hasMore.value && !loading.value) {
                loadMore();
            }
        },
        { rootMargin: '200px' }
    );
    observer.observe(loadMoreTrigger.value);
};

onMounted(() => {
    if (infiniteScrollEnabled.value) {
        nextTick(setupObserver);
    }
});

onUnmounted(() => {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
});

// Pagination navigation
const goToPage = (pageNum) => {
    router.get('/', { page: pageNum }, { preserveState: true, preserveScroll: false });
};

// Check if ads are enabled
const adsEnabled = computed(() => {
    const enabled = props.adSettings?.videoGridEnabled;
    return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
});

const gridAds = computed(() => props.adSettings?.videoGridAds || []);
const adFrequency = computed(() => parseInt(props.adSettings?.videoGridFrequency) || 8);

// Helper to check if ad should show after index
const shouldShowAd = (index, totalLength) => {
    if (!adsEnabled.value || !gridAds.value.length) return false;
    return (index + 1) % adFrequency.value === 0 && index < totalLength - 1;
};

// Sponsored cards: insert at frequency intervals, cycling through available cards
// Offset by half the grid ad frequency so they interleave instead of stacking
const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);
const sponsoredOffset = computed(() => Math.floor(adFrequency.value / 2));
const getSponsoredCard = (index) => {
    if (!props.sponsoredCards?.length) return null;
    if ((index + 1 + sponsoredOffset.value) % sponsoredFrequency.value !== 0) return null;
    const cardIndex = Math.floor((index + 1 + sponsoredOffset.value) / sponsoredFrequency.value) - 1;
    return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <!-- Featured Videos -->
        <section v-if="featuredVideos.length > 0" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-text-primary">{{ t('home.featured') }}</h2>
            </div>
            <div :class="gridClass">
                <VideoCard v-for="video in featuredVideos" :key="video.id" :video="withTranslation(video)" />
            </div>
        </section>

        <BannerAd :config="adSettings?.rail1" />

        <!-- Shorts Preview Rail -->
        <ShortsRail v-if="shortsPreview?.length" :shorts="shortsPreview" />

        <BannerAd :config="adSettings?.rail2" />

        <!-- Latest Videos -->
        <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-text-primary">{{ t('home.latest') }}</h2>
                <a :href="localizedUrl('/videos')" class="text-sm font-medium text-accent">{{ t('common.view_all') }}</a>
            </div>
            
            <!-- Infinite Scroll Mode -->
            <template v-if="infiniteScrollEnabled">
                <div :class="gridClass">
                    <template v-for="(video, index) in videos" :key="'scroll-' + video.id">
                        <VideoCard :video="withTranslation(video)" />
                        <!-- Ad after every X videos -->
                        <div
                            v-if="shouldShowAd(index, videos.length)"
                            class="rounded-xl p-2"
                            :class="mobileGrid === 2 ? 'col-span-2 sm:col-span-1' : 'col-span-1'"
                        >
                            <GridAdSlot :ads="gridAds" />
                        </div>
                        <SponsoredVideoCard
                            v-if="getSponsoredCard(index)"
                            :card="getSponsoredCard(index)"
                        />
                    </template>
                </div>
                
                <!-- Load More Trigger -->
                <div ref="loadMoreTrigger" class="flex justify-center py-8">
                    <div v-if="loading" class="flex items-center gap-2 text-text-secondary">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span>{{ t('home.loading_more') }}</span>
                    </div>
                    <p v-else-if="!hasMore && videos.length > 0" class="text-sm text-text-muted">
                        {{ t('home.reached_end') }}
                    </p>
                </div>
            </template>
            
            <!-- Pagination Mode -->
            <template v-else>
                <div :class="gridClass">
                    <template v-for="(video, index) in latestVideos.data" :key="'page-' + video.id">
                        <VideoCard :video="withTranslation(video)" />
                        <!-- Ad after every X videos -->
                        <div
                            v-if="shouldShowAd(index, latestVideos.data.length)"
                            class="rounded-xl p-2"
                            :class="mobileGrid === 2 ? 'col-span-2 sm:col-span-1' : 'col-span-1'"
                        >
                            <GridAdSlot :ads="gridAds" />
                        </div>
                        <SponsoredVideoCard
                            v-if="getSponsoredCard(index)"
                            :card="getSponsoredCard(index)"
                        />
                    </template>
                </div>
                
                <Pagination
                    :current-page="latestVideos.current_page"
                    :last-page="latestVideos.last_page"
                    @page-change="goToPage"
                />
            </template>
        </section>

        <BannerAd :config="adSettings?.rail3" />

        <!-- Latest Playlists Rail -->
        <PlaylistsRail v-if="latestPlaylists?.length" :playlists="latestPlaylists" />

        <!-- Latest Images Rail -->
        <ImagesRail v-if="latestImages?.length" :images="latestImages" />

        <BannerAd :config="adSettings?.rail4" />

        <!-- Popular Videos -->
        <section v-if="popularVideos.length > 0" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-text-primary">{{ t('home.popular') }}</h2>
                <a :href="localizedUrl('/trending')" class="text-sm font-medium text-accent">{{ t('common.view_all') }}</a>
            </div>
            <div :class="gridClass">
                <VideoCard v-for="video in popularVideos" :key="video.id" :video="withTranslation(video)" />
            </div>
        </section>
    </AppLayout>
</template>
