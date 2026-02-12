<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Rss } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { useI18n } from '@/Composables/useI18n';
import { useVirtualGrid } from '@/Composables/useVirtualGrid';

const { t } = useI18n();

const props = defineProps({
    videos: Object,
});

const videoItems = computed(() => props.videos?.data || []);
const { virtualRows, containerProps, wrapperProps, gridStyle } = useVirtualGrid(videoItems, {
    itemHeight: 320,
    overscan: 6,
});

const goToPage = (pageNum) => {
    router.get('/feed', { page: pageNum }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <Head :title="t('feed.title') || 'Subscriptions Feed'" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('feed.title') || 'Subscriptions' }}</h1>
            <p class="mt-1" style="color: var(--color-text-secondary);">{{ t('feed.description') || 'Latest videos from channels you follow' }}</p>
        </div>

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

        <div v-else class="text-center py-16">
            <Rss class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('feed.empty') || 'No videos in your feed yet' }}</p>
            <p class="mt-1" style="color: var(--color-text-muted);">{{ t('feed.empty_desc') || 'Subscribe to channels to see their latest videos here' }}</p>
        </div>

        <Pagination
            :current-page="videos.current_page"
            :last-page="videos.last_page"
            @page-change="goToPage"
        />
    </AppLayout>
</template>
