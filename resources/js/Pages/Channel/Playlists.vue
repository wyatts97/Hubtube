<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo } from 'lucide-vue-next';

const props = defineProps({
    channel: Object,
    playlists: Object,
});

const tabs = [
    { name: 'Videos', href: `/channel/${props.channel.username}` },
    { name: 'Shorts', href: `/channel/${props.channel.username}/shorts` },
    { name: 'Playlists', href: `/channel/${props.channel.username}/playlists`, active: true },
    { name: 'About', href: `/channel/${props.channel.username}/about` },
];
</script>

<template>
    <Head :title="`${channel.username} - Playlists`" />

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
                <p class="text-dark-400">Playlists</p>
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

        <!-- Playlists Grid -->
        <div v-if="playlists.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="playlist in playlists.data"
                :key="playlist.id"
                :href="`/playlist/${playlist.slug}`"
                class="card p-4 hover:bg-dark-800 transition-colors"
            >
                <div class="aspect-video bg-dark-800 rounded-lg flex items-center justify-center mb-3">
                    <ListVideo class="w-12 h-12 text-dark-500" />
                </div>
                <h3 class="font-medium text-white">{{ playlist.title }}</h3>
                <p class="text-sm text-dark-400">{{ playlist.videos_count }} videos</p>
            </Link>
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400">No playlists yet</p>
        </div>
    </AppLayout>
</template>
