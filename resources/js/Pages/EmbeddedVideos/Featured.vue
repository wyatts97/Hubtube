<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Star } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    videos: Array,
});

const toVideoFormat = (video) => ({
    id: video.id,
    title: video.title,
    slug: 'embedded-' + video.id,
    thumbnail: video.thumbnail_url,
    thumbnail_url: video.thumbnail_url,
    duration: video.duration,
    duration_formatted: video.duration_formatted,
    views_count: video.views_count,
    published_at: video.imported_at,
    created_at: video.created_at,
    is_embedded: true,
    embed_url: video.embed_url,
    source_site: video.source_site,
    user: {
        id: 0,
        name: video.source_site ? video.source_site.charAt(0).toUpperCase() + video.source_site.slice(1) : 'External',
        username: video.source_site || 'external',
        avatar: null,
    },
});
</script>

<template>
    <Head title="Featured Videos" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold flex items-center gap-2" style="color: var(--color-text-primary);">
                <Star class="w-6 h-6" style="color: var(--color-accent);" />
                {{ t('home.featured') || 'Featured Videos' }}
            </h1>
            <p class="mt-1" style="color: var(--color-text-secondary);">{{ t('embedded.featured_desc') || 'Hand-picked featured content' }}</p>
        </div>

        <div v-if="videos?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos" :key="video.id" :video="toVideoFormat(video)" />
        </div>

        <div v-else class="text-center py-16">
            <Star class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('embedded.no_featured') || 'No featured videos yet' }}</p>
            <p class="mt-1" style="color: var(--color-text-muted);">Check back later for curated content</p>
        </div>
    </AppLayout>
</template>
