<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { ChevronLeft, ChevronRight, Hash } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from '@/Composables/useI18n';

const { t, localizedUrl } = useI18n();

const props = defineProps({
    tag: String,
    translatedTag: { type: String, default: null },
    videos: Object,
    seo: { type: Object, default: () => ({}) },
});

const page = usePage();
const mobileGrid = computed(() => page.props.theme?.mobileVideoGrid === '2' ? 2 : 1);
const gridClass = computed(() => mobileGrid.value === 2
    ? 'grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4'
    : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4'
);

const displayTag = props.translatedTag || props.tag;

const goToPage = (pageNum) => {
    router.get(localizedUrl(`/tag/${props.tag}`), { page: pageNum }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-1">
                <Link :href="localizedUrl('/tags')" class="text-sm hover:opacity-80" style="color: var(--color-accent);">{{ t('tags.title') || 'Tags' }}</Link>
                <span style="color: var(--color-text-muted);">/</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ displayTag }}</h1>
                <span class="text-sm" style="color: var(--color-text-muted);">•</span>
                <span class="text-sm" style="color: var(--color-text-muted);">{{ t('tags.video_count', { count: videos.total || 0 }) || `${videos.total || 0} videos` }}</span>
            </div>
        </div>

        <div v-if="videos.data?.length" :class="gridClass">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <Hash class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('tags.no_videos') || 'No videos with this tag yet' }}</p>
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
                        style="color: var(--color-text-muted);"
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
