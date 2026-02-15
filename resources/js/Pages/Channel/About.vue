<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Calendar, Eye, Video } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const tSafe = (key, fallback) => {
    const val = t(key);
    return val === key ? fallback : val;
};

const props = defineProps({
    channel: Object,
    stats: Object,
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: false },
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
        items.push({ name: tSafe('channel.recently_watched', 'Recently Watched'), href: `/channel/${props.channel.username}/history` });
    }
    items.push({ name: tSafe('channel.about', 'About'), href: `/channel/${props.channel.username}/about`, active: true });
    return items;
});

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};
</script>

<template>
    <Head :title="`${channel.username} - About`" />

    <AppLayout>
        <!-- Channel Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 avatar">
                <img :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" />
            </div>
            <div>
                <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">{{ channel.username }}</h1>
                <p style="color: var(--color-text-muted);">{{ tSafe('channel.about', 'About') }}</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Description -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">{{ tSafe('channel.description', 'Description') }}</h2>
                    <p v-if="channel.channel?.description" class="whitespace-pre-wrap" style="color: var(--color-text-secondary);">
                        {{ channel.channel.description }}
                    </p>
                    <p v-else style="color: var(--color-text-muted);"></p>
                </div>
            </div>

            <!-- Stats -->
            <div class="space-y-4">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">{{ tSafe('channel.stats', 'Stats') }}</h2>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <Calendar class="w-5 h-5" style="color: var(--color-text-muted);" />
                            <div>
                                <p class="text-sm" style="color: var(--color-text-muted);">{{ tSafe('channel.joined', 'Joined') }}</p>
                                <p style="color: var(--color-text-primary);">{{ formatDate(stats.joinedAt) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Eye class="w-5 h-5" style="color: var(--color-text-muted);" />
                            <div>
                                <p class="text-sm" style="color: var(--color-text-muted);">{{ tSafe('channel.total_views', 'Total Views') }}</p>
                                <p style="color: var(--color-text-primary);">{{ stats.totalViews?.toLocaleString() || 0 }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Video class="w-5 h-5" style="color: var(--color-text-muted);" />
                            <div>
                                <p class="text-sm" style="color: var(--color-text-muted);">{{ tSafe('channel.video_count', 'Videos') }}</p>
                                <p style="color: var(--color-text-primary);">{{ stats.videoCount }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
