<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';

defineProps({
    videos: Object,
});
</script>

<template>
    <Head title="Trending" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Trending</h1>
            <p class="text-dark-400 mt-1">Popular videos from the past week</p>
        </div>

        <div v-if="videos.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400 text-lg">No trending videos yet</p>
            <p class="text-dark-500 mt-2">Check back later for popular content</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links?.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <a
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm',
                        link.active 
                            ? 'bg-primary-600 text-white' 
                            : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                    ]"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
