<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, Heart } from 'lucide-vue-next';

const props = defineProps({
    channel: Object,
    playlists: Object,
    favoritePlaylists: Object,
    activeTab: { type: String, default: 'user' },
});

const currentTab = ref(props.activeTab);

const channelTabs = [
    { name: 'Videos', href: `/channel/${props.channel.username}` },
    { name: 'Shorts', href: `/channel/${props.channel.username}/shorts` },
    { name: 'Playlists', href: `/channel/${props.channel.username}/playlists`, active: true },
    { name: 'About', href: `/channel/${props.channel.username}/about` },
];

const switchTab = (tab) => {
    currentTab.value = tab;
    router.get(`/channel/${props.channel.username}/playlists`, { tab }, { preserveState: true, preserveScroll: true, replace: true });
};

const activeList = ref(null);
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
                <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">{{ channel.username }}</h1>
                <p style="color: var(--color-text-muted);">Playlists</p>
            </div>
        </div>

        <!-- Channel Tabs -->
        <div class="mb-6" style="border-bottom: 1px solid var(--color-border);">
            <nav class="flex gap-6">
                <Link
                    v-for="tab in channelTabs"
                    :key="tab.name"
                    :href="tab.href"
                    :class="[
                        'pb-3 px-1 border-b-2 transition-colors',
                        tab.active 
                            ? 'border-primary-500' 
                            : 'border-transparent hover:border-primary-500'
                    ]"
                    :style="{ color: tab.active ? 'var(--color-text-primary)' : 'var(--color-text-muted)' }"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!-- Playlist Sub-tabs: User Playlists / Favorite Playlists -->
        <div class="flex gap-3 mb-6">
            <button
                @click="switchTab('user')"
                class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                :style="{
                    backgroundColor: currentTab === 'user' ? 'var(--color-accent)' : 'var(--color-bg-card)',
                    color: currentTab === 'user' ? '#fff' : 'var(--color-text-secondary)',
                }"
            >
                <ListVideo class="w-4 h-4 inline-block mr-1.5 -mt-0.5" />
                User Playlists
            </button>
            <button
                @click="switchTab('favorites')"
                class="px-4 py-2 rounded-full text-sm font-medium transition-colors"
                :style="{
                    backgroundColor: currentTab === 'favorites' ? 'var(--color-accent)' : 'var(--color-bg-card)',
                    color: currentTab === 'favorites' ? '#fff' : 'var(--color-text-secondary)',
                }"
            >
                <Heart class="w-4 h-4 inline-block mr-1.5 -mt-0.5" />
                Favorite Playlists
            </button>
        </div>

        <!-- User Playlists Grid -->
        <template v-if="currentTab === 'user'">
            <div v-if="playlists.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="playlist in playlists.data"
                    :key="playlist.id"
                    :href="`/playlist/${playlist.slug}`"
                    class="card overflow-hidden hover:ring-2 transition-all"
                    style="--tw-ring-color: var(--color-accent);"
                >
                    <div class="aspect-video flex items-center justify-center" style="background-color: var(--color-bg-secondary);">
                        <ListVideo class="w-12 h-12" style="color: var(--color-text-muted);" />
                    </div>
                    <div class="p-3">
                        <h3 class="font-medium truncate" style="color: var(--color-text-primary);">{{ playlist.title }}</h3>
                        <p class="text-sm" style="color: var(--color-text-muted);">{{ playlist.videos_count }} videos</p>
                    </div>
                </Link>
            </div>
            <div v-else class="text-center py-12">
                <ListVideo class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
                <p style="color: var(--color-text-muted);">No playlists yet</p>
            </div>
        </template>

        <!-- Favorite Playlists Grid -->
        <template v-if="currentTab === 'favorites'">
            <div v-if="favoritePlaylists?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="playlist in favoritePlaylists.data"
                    :key="playlist.id"
                    :href="`/playlist/${playlist.slug}`"
                    class="card overflow-hidden hover:ring-2 transition-all"
                    style="--tw-ring-color: var(--color-accent);"
                >
                    <div class="aspect-video flex items-center justify-center" style="background-color: var(--color-bg-secondary);">
                        <ListVideo class="w-12 h-12" style="color: var(--color-text-muted);" />
                    </div>
                    <div class="p-3">
                        <h3 class="font-medium truncate" style="color: var(--color-text-primary);">{{ playlist.title }}</h3>
                        <p class="text-sm" style="color: var(--color-text-muted);">
                            {{ playlist.videos_count }} videos
                            <span v-if="playlist.user"> • by {{ playlist.user.username }}</span>
                        </p>
                    </div>
                </Link>
            </div>
            <div v-else class="text-center py-12">
                <Heart class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
                <p style="color: var(--color-text-muted);">No favorite playlists yet</p>
            </div>
        </template>
    </AppLayout>
</template>
