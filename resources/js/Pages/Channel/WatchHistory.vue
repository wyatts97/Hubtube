<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { History } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const tSafe = (key, fallback) => {
    const val = t(key);
    return val === key ? fallback : val;
};

const props = defineProps({
    channel: Object,
    videos: Object,
    isOwner: Boolean,
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: true },
});

const tabs = computed(() => {
    const items = [
        { name: tSafe('channel.videos', 'Videos'), href: `/channel/${props.channel.username}` },
        { name: tSafe('channel.playlists', 'Playlists'), href: `/channel/${props.channel.username}/playlists` },
    ];
    if (props.showLikedVideos) {
        items.push({ name: tSafe('channel.liked_videos', 'Liked Videos'), href: `/channel/${props.channel.username}/liked` });
    }
    if (props.showWatchHistory) {
        items.push({ name: tSafe('channel.recently_watched', 'Recently Watched'), href: `/channel/${props.channel.username}/history`, active: true });
    }
    items.push({ name: tSafe('channel.about', 'About'), href: `/channel/${props.channel.username}/about` });
    return items;
});
</script>

<template>
    <Head :title="`${channel.username} - Recently Watched`" />

    <AppLayout>
        <!-- Channel Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 avatar">
                <img :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" />
            </div>
            <div>
                <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">{{ channel.username }}</h1>
                <p style="color: var(--color-text-muted);">{{ tSafe('channel.recently_watched', 'Recently Watched') }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6" style="border-bottom: 1px solid var(--color-border);">
            <nav class="flex gap-6 overflow-x-auto scrollbar-hide">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="tab.href"
                    :class="[
                        'pb-3 px-1 border-b-2 transition-colors whitespace-nowrap shrink-0',
                        tab.active
                            ? 'border-current'
                            : 'border-transparent'
                    ]"
                    :style="{ color: tab.active ? 'var(--color-text-primary)' : 'var(--color-text-muted)' }"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!-- Videos Grid -->
        <div v-if="videos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <History class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
            <p style="color: var(--color-text-muted);">{{ tSafe('channel.no_watch_history', 'No watch history yet') }}</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="px-4 py-2 rounded-lg text-sm"
                    :style="{
                        backgroundColor: link.active ? 'var(--color-accent)' : 'var(--color-bg-card)',
                        color: link.active ? '#fff' : 'var(--color-text-secondary)',
                    }"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
