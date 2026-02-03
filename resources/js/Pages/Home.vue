<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import LiveStreamCard from '@/Components/LiveStreamCard.vue';

defineProps({
    featuredVideos: Array,
    latestVideos: Array,
    popularVideos: Array,
    liveStreams: Array,
    categories: Array,
});
</script>

<template>
    <Head title="Home" />

    <AppLayout>
        <!-- Live Streams Section -->
        <section v-if="liveStreams.length > 0" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <span class="w-3 h-3 bg-red-600 rounded-full animate-pulse"></span>
                    Live Now
                </h2>
                <a href="/live" class="text-primary-500 hover:text-primary-400 text-sm font-medium">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <LiveStreamCard v-for="stream in liveStreams" :key="stream.id" :stream="stream" />
            </div>
        </section>

        <!-- Featured Videos -->
        <section v-if="featuredVideos.length > 0" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">Featured</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCard v-for="video in featuredVideos" :key="video.id" :video="video" />
            </div>
        </section>

        <!-- Latest Videos -->
        <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">Latest Videos</h2>
                <a href="/videos" class="text-primary-500 hover:text-primary-400 text-sm font-medium">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCard v-for="video in latestVideos" :key="video.id" :video="video" />
            </div>
        </section>

        <!-- Popular Videos -->
        <section v-if="popularVideos.length > 0" class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold">Popular</h2>
                <a href="/trending" class="text-primary-500 hover:text-primary-400 text-sm font-medium">View All</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCard v-for="video in popularVideos" :key="video.id" :video="video" />
            </div>
        </section>
    </AppLayout>
</template>
