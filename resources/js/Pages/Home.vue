<script setup>
import { usePage, router } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import VideoCardSkeleton from '@/Components/VideoCardSkeleton.vue';
import LiveStreamCard from '@/Components/LiveStreamCard.vue';
import ShortsCarousel from '@/Components/ShortsCarousel.vue';
import { Loader2 } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { sanitizeHtml } from '@/Composables/useSanitize';

// Initial loading state for skeleton display
const isInitialLoad = ref(true);
onMounted(() => {
    // Simulate brief loading for skeleton demo, or remove for instant load
    setTimeout(() => {
        isInitialLoad.value = false;
    }, 100);
});

const props = defineProps({
    featuredVideos: Array,
    latestVideos: Object, // Now a paginated object
    popularVideos: Array,
    liveStreams: Array,
    categories: Array,
    adSettings: Object, // Ad settings from admin
    shortsCarousel: Array,
    shortsCarouselEnabled: Boolean,
    seo: { type: Object, default: () => ({}) },
});

const page = usePage();
const infiniteScrollEnabled = computed(() => page.props.app?.infinite_scroll_enabled ?? false);

// Infinite scroll state
const videos = ref([...(props.latestVideos?.data || [])]);
const currentPage = ref(props.latestVideos?.current_page || 1);
const lastPage = ref(props.latestVideos?.last_page || 1);
const loading = ref(false);
const hasMore = computed(() => currentPage.value < lastPage.value);

// Load more videos for infinite scroll
const loadMore = async () => {
    if (loading.value || !hasMore.value) return;
    
    loading.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(`/api/videos/load-more?page=${currentPage.value + 1}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            credentials: 'same-origin',
        });
        const data = await response.json();
        
        videos.value.push(...data.data);
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
    router.get('/', { page: pageNum }, { preserveState: true, preserveScroll: false });
};

// Check if ads are enabled
const adsEnabled = computed(() => {
    const enabled = props.adSettings?.videoGridEnabled;
    return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
});

const adCode = computed(() => sanitizeHtml(props.adSettings?.videoGridCode || ''));
const adFrequency = computed(() => parseInt(props.adSettings?.videoGridFrequency) || 8);

// Helper to check if ad should show after index
const shouldShowAd = (index, totalLength) => {
    if (!adsEnabled.value || !adCode.value.trim()) return false;
    return (index + 1) % adFrequency.value === 0 && index < totalLength - 1;
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <!-- Live Streams Section -->
        <section v-if="liveStreams.length > 0" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold flex items-center gap-2" style="color: var(--color-text-primary);">
                    <span class="w-3 h-3 rounded-full animate-pulse" style="background-color: var(--color-accent);"></span>
                    Live Now
                </h2>
                <a href="/live" class="text-sm font-medium" style="color: var(--color-accent);">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <LiveStreamCard v-for="stream in liveStreams" :key="stream.id" :stream="stream" />
            </div>
        </section>

        <!-- Shorts Carousel -->
        <ShortsCarousel v-if="shortsCarouselEnabled && shortsCarousel?.length" :shorts="shortsCarousel" />

        <!-- Featured Videos -->
        <section v-if="featuredVideos.length > 0 || isInitialLoad" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Featured</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template v-if="isInitialLoad">
                    <VideoCardSkeleton v-for="i in 4" :key="'skeleton-featured-' + i" />
                </template>
                <template v-else>
                    <VideoCard v-for="video in featuredVideos" :key="video.id" :video="video" />
                </template>
            </div>
        </section>

        <!-- Latest Videos -->
        <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Latest Videos</h2>
                <a href="/videos" class="text-sm font-medium" style="color: var(--color-accent);">View All</a>
            </div>
            
            <!-- Skeleton Loading State -->
            <div v-if="isInitialLoad" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCardSkeleton v-for="i in 8" :key="'skeleton-latest-' + i" />
            </div>
            
            <!-- Infinite Scroll Mode -->
            <template v-else-if="infiniteScrollEnabled">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <template v-for="(video, index) in videos" :key="'scroll-' + video.id">
                        <VideoCard :video="video" />
                        <!-- Ad after every X videos -->
                        <div 
                            v-if="shouldShowAd(index, videos.length)"
                            class="col-span-1 flex items-start justify-center rounded-xl p-2"
                        >
                            <div v-html="adCode"></div>
                        </div>
                    </template>
                </div>
                
                <!-- Load More Trigger -->
                <div ref="loadMoreTrigger" class="flex justify-center py-8">
                    <div v-if="loading" class="flex items-center gap-2" style="color: var(--color-text-secondary);">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span>Loading more videos...</span>
                    </div>
                    <p v-else-if="!hasMore && videos.length > 0" class="text-sm" style="color: var(--color-text-muted);">
                        You've reached the end
                    </p>
                </div>
            </template>
            
            <!-- Pagination Mode -->
            <template v-else>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <template v-for="(video, index) in latestVideos.data" :key="'page-' + video.id">
                        <VideoCard :video="video" />
                        <!-- Ad after every X videos -->
                        <div 
                            v-if="shouldShowAd(index, latestVideos.data.length)"
                            class="col-span-1 flex items-start justify-center rounded-xl p-2"
                        >
                            <div v-html="adCode"></div>
                        </div>
                    </template>
                </div>
                
                <Pagination
                    :current-page="latestVideos.current_page"
                    :last-page="latestVideos.last_page"
                    @page-change="goToPage"
                />
            </template>
        </section>

        <!-- Popular Videos -->
        <section v-if="popularVideos.length > 0 || isInitialLoad" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">Popular</h2>
                <a href="/trending" class="text-sm font-medium" style="color: var(--color-accent);">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template v-if="isInitialLoad">
                    <VideoCardSkeleton v-for="i in 4" :key="'skeleton-popular-' + i" />
                </template>
                <template v-else>
                    <VideoCard v-for="video in popularVideos" :key="video.id" :video="video" />
                </template>
            </div>
        </section>
    </AppLayout>
</template>
