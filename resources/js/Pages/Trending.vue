<script setup>
import { usePage, router } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Loader2 } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { sanitizeHtml } from '@/Composables/useSanitize';
import { useI18n } from '@/Composables/useI18n';
import { useAutoTranslate } from '@/Composables/useAutoTranslate';

const { t, localizedUrl } = useI18n();
const { translateVideos, tr } = useAutoTranslate(['title']);

const props = defineProps({
    videos: Object,
    seo: { type: Object, default: () => ({}) },
    adSettings: { type: Object, default: () => ({}) },
});

const page = usePage();
const infiniteScrollEnabled = computed(() => page.props.app?.infinite_scroll_enabled ?? false);

// Infinite scroll state
const videoList = ref([...(props.videos?.data || [])]);
const currentPage = ref(props.videos?.current_page || 1);
const lastPage = ref(props.videos?.last_page || 1);
const loading = ref(false);
const hasMore = computed(() => currentPage.value < lastPage.value);

// Load more videos for infinite scroll
const loadMore = async () => {
    if (loading.value || !hasMore.value) return;
    
    loading.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(`/trending?page=${currentPage.value + 1}`, {
            headers: { 
                'Accept': 'application/json', 
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            credentials: 'same-origin',
        });
        const data = await response.json();
        
        videoList.value.push(...data.data);
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;
    } catch (error) {
        console.error('Failed to load more videos:', error);
    } finally {
        loading.value = false;
    }
};

// Infinite scroll observer
let observer = null;
const loadMoreTrigger = ref(null);

onMounted(() => {
    const allVideos = props.videos?.data || [];
    if (allVideos.length) translateVideos(allVideos);
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

onMounted(() => {
    if (infiniteScrollEnabled.value && loadMoreTrigger.value) {
        observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore.value && !loading.value) {
                    loadMore();
                }
            },
            { rootMargin: '200px' }
        );
        observer.observe(loadMoreTrigger.value);
    }
});

onUnmounted(() => {
    if (observer) {
        observer.disconnect();
    }
});

// Pagination navigation
const goToPage = (pageNum) => {
    router.get('/trending', { page: pageNum }, { preserveState: true, preserveScroll: false });
};

// Grid ads
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
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('nav.trending') || 'Trending' }}</h1>
            <p class="mt-1" style="color: var(--color-text-secondary);">{{ t('home.popular') || 'Popular videos from the past week' }}</p>
        </div>

        <!-- Infinite Scroll Mode -->
        <template v-if="infiniteScrollEnabled">
            <div v-if="videoList.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template v-for="(video, index) in videoList" :key="video.id">
                    <VideoCard :video="withTranslation(video)" />
                    <div v-if="shouldShowAd(index, videoList.length)" class="col-span-1 flex items-start justify-center rounded-xl p-2">
                        <div v-html="adCode"></div>
                    </div>
                </template>
            </div>
            
            <div ref="loadMoreTrigger" class="flex justify-center py-8">
                <div v-if="loading" class="flex items-center gap-2" style="color: var(--color-text-secondary);">
                    <Loader2 class="w-5 h-5 animate-spin" />
                    <span>{{ t('home.loading_more') || 'Loading more videos...' }}</span>
                </div>
                <p v-else-if="!hasMore && videoList.length > 0" class="text-sm" style="color: var(--color-text-muted);">
                    {{ t('home.reached_end') || "You've reached the end" }}
                </p>
            </div>
        </template>

        <!-- Pagination Mode -->
        <template v-else>
            <div v-if="videos.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template v-for="(video, index) in videos.data" :key="video.id">
                    <VideoCard :video="withTranslation(video)" />
                    <div v-if="shouldShowAd(index, videos.data.length)" class="col-span-1 flex items-start justify-center rounded-xl p-2">
                        <div v-html="adCode"></div>
                    </div>
                </template>
            </div>

            <div v-else class="text-center py-12">
                <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('trending.no_videos') || 'No trending videos yet' }}</p>
                <p class="mt-2" style="color: var(--color-text-muted);">{{ t('trending.check_back') || 'Check back later for popular content' }}</p>
            </div>

            <Pagination
                :current-page="videos.current_page"
                :last-page="videos.last_page"
                @page-change="goToPage"
            />
        </template>
    </AppLayout>
</template>
