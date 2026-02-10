<script setup>
import { Link, router } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { ChevronLeft, ChevronRight, Hash } from 'lucide-vue-next';

const props = defineProps({
    tag: String,
    videos: Object,
    seo: { type: Object, default: () => ({}) },
});

const goToPage = (pageNum) => {
    router.get(`/tag/${props.tag}`, { page: pageNum }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="mb-6">
            <div class="flex items-center gap-2">
                <Hash class="w-6 h-6" style="color: var(--color-accent);" />
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">#{{ tag }}</h1>
            </div>
            <p class="text-sm mt-1" style="color: var(--color-text-muted);">{{ videos.total || 0 }} videos with this tag</p>
        </div>

        <div v-if="videos.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <Hash class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">No videos with this tag yet</p>
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
