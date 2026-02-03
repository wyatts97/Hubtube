<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';

const props = defineProps({
    channel: Object,
    shorts: Object,
});

const tabs = [
    { name: 'Videos', href: `/channel/${props.channel.username}` },
    { name: 'Shorts', href: `/channel/${props.channel.username}/shorts`, active: true },
    { name: 'Playlists', href: `/channel/${props.channel.username}/playlists` },
    { name: 'About', href: `/channel/${props.channel.username}/about` },
];
</script>

<template>
    <Head :title="`${channel.username} - Shorts`" />

    <AppLayout>
        <!-- Channel Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 avatar">
                <img v-if="channel.avatar" :src="channel.avatar" :alt="channel.username" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-xl font-bold">
                    {{ channel.username?.charAt(0)?.toUpperCase() }}
                </div>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">{{ channel.username }}</h1>
                <p class="text-dark-400">Shorts</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-dark-800 mb-6">
            <nav class="flex gap-6">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="tab.href"
                    :class="[
                        'pb-3 px-1 border-b-2 transition-colors',
                        tab.active 
                            ? 'text-white border-primary-500' 
                            : 'text-dark-400 hover:text-white border-transparent hover:border-primary-500'
                    ]"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!-- Shorts Grid -->
        <div v-if="shorts.data.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <VideoCard v-for="short in shorts.data" :key="short.id" :video="short" />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400">No shorts yet</p>
        </div>
    </AppLayout>
</template>
