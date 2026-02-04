<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import VideoCardSkeleton from '@/Components/VideoCardSkeleton.vue';
import LiveStreamCard from '@/Components/LiveStreamCard.vue';
import { Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next';

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
    latestEmbedded: Array, // Embedded videos from external sites
    popularVideos: Array,
    liveStreams: Array,
    categories: Array,
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
</script>

<template>
    <Head title="Home" />

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
                    <VideoCard v-for="video in videos" :key="video.id" :video="video" />
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
                    <VideoCard v-for="video in latestVideos.data" :key="video.id" :video="video" />
                </div>
                
                <!-- Pagination Controls -->
                <div v-if="latestVideos.last_page > 1" class="flex justify-center items-center gap-2 mt-8">
                    <button 
                        @click="goToPage(latestVideos.current_page - 1)"
                        :disabled="latestVideos.current_page === 1"
                        class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :style="{ 
                            backgroundColor: 'var(--color-bg-secondary)',
                            color: 'var(--color-text-primary)'
                        }"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </button>
                    
                    <div class="flex items-center gap-1">
                        <template v-for="pageNum in latestVideos.last_page" :key="pageNum">
                            <button
                                v-if="pageNum === 1 || pageNum === latestVideos.last_page || 
                                      (pageNum >= latestVideos.current_page - 2 && pageNum <= latestVideos.current_page + 2)"
                                @click="goToPage(pageNum)"
                                class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                                :style="pageNum === latestVideos.current_page 
                                    ? { backgroundColor: 'var(--color-accent)', color: 'white' }
                                    : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                            >
                                {{ pageNum }}
                            </button>
                            <span 
                                v-else-if="pageNum === latestVideos.current_page - 3 || pageNum === latestVideos.current_page + 3"
                                style="color: var(--color-text-muted);"
                            >
                                ...
                            </span>
                        </template>
                    </div>
                    
                    <button 
                        @click="goToPage(latestVideos.current_page + 1)"
                        :disabled="latestVideos.current_page === latestVideos.last_page"
                        class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :style="{ 
                            backgroundColor: 'var(--color-bg-secondary)',
                            color: 'var(--color-text-primary)'
                        }"
                    >
                        <ChevronRight class="w-5 h-5" />
                    </button>
                </div>
            </template>
        </section>

        <!-- Embedded Videos Section -->
        <section v-if="(latestEmbedded && latestEmbedded.length > 0) || isInitialLoad" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold" style="color: var(--color-text-primary);">
                    External Videos
                </h2>
                <a href="/embedded" class="text-sm font-medium" style="color: var(--color-accent);">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template v-if="isInitialLoad">
                    <VideoCardSkeleton v-for="i in 4" :key="'skeleton-embedded-' + i" />
                </template>
                <template v-else>
                    <VideoCard 
                        v-for="video in latestEmbedded" 
                        :key="'embedded-' + video.id" 
                        :video="video" 
                    />
                </template>
            </div>
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
