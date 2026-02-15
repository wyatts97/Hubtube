<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import VideoCardSkeleton from '@/Components/VideoCardSkeleton.vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const isInitialLoad = ref(true);
onMounted(() => { setTimeout(() => { isInitialLoad.value = false; }, 100); });

const tSafe = (key, fallback) => {
    const val = t(key);
    return val === key ? fallback : val;
};

const props = defineProps({
    channel: Object,
    videos: Object,
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: false },
});

const tabs = computed(() => {
    const items = [
        { name: tSafe('channel.videos', 'Videos'), href: `/channel/${props.channel.username}`, active: true },
        { name: tSafe('channel.playlists', 'Playlists'), href: `/channel/${props.channel.username}/playlists` },
    ];
    if (props.showLikedVideos) {
        items.push({ name: tSafe('channel.liked_videos', 'Liked Videos'), href: `/channel/${props.channel.username}/liked` });
    }
    if (props.showWatchHistory) {
        items.push({ name: tSafe('channel.recently_watched', 'Recently Watched'), href: `/channel/${props.channel.username}/history` });
    }
    items.push({ name: tSafe('channel.about', 'About'), href: `/channel/${props.channel.username}/about` });
    return items;
});
</script>

<template>
    <Head :title="`${channel.username} - Videos`" />

    <AppLayout>
        <!-- Channel Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 avatar">
                <img :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">{{ channel.username }}</h1>
                <p class="text-dark-400">{{ videos.total }} {{ t('common.videos') || 'videos' }}</p>
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

        <!-- Skeleton Loading -->
        <div v-if="isInitialLoad" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCardSkeleton v-for="i in 8" :key="'skeleton-' + i" />
        </div>

        <!-- Videos Grid -->
        <div v-else-if="videos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400">{{ t('channel.no_videos') || 'No videos yet' }}</p>
        </div>
    </AppLayout>
</template>
