<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Calendar, Eye, Video } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

const { t } = useI18n();


const props = defineProps({
    channel: Object,
    stats: Object,
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: false },
});

const tabs = computed(() => {
    const items = [
        { name: t('channel.videos'), href: `/channel/${props.channel.username}` },
        { name: t('channel.playlists'), href: `/channel/${props.channel.username}/playlists` },
    ];
    if (props.showLikedVideos) {
        items.push({ name: t('channel.liked_videos'), href: `/channel/${props.channel.username}/liked` });
    }
    if (props.showWatchHistory) {
        items.push({ name: t('channel.recently_watched'), href: `/channel/${props.channel.username}/history` });
    }
    items.push({ name: t('channel.about'), href: `/channel/${props.channel.username}/about`, active: true });
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
    <SeoHead :title="`${channel.username} - About`" />

    <AppLayout>
        <!-- Channel Header -->
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 avatar">
                <img :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" :alt="channel.username" class="w-full h-full object-cover" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-text-primary">{{ channel.username }}</h1>
                <p class="text-text-muted">{{ t('channel.about') }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-border">
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
                    <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('channel.description') }}</h2>
                    <p v-if="channel.channel?.description" class="whitespace-pre-wrap text-text-secondary">
                        {{ channel.channel.description }}
                    </p>
                    <p v-else class="text-text-muted"></p>
                </div>
            </div>

            <!-- Stats -->
            <div class="space-y-4">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('channel.stats') }}</h2>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <Calendar class="w-5 h-5 text-text-muted" />
                            <div>
                                <p class="text-sm text-text-muted">{{ t('channel.joined') }}</p>
                                <p class="text-text-primary">{{ formatDate(stats.joinedAt) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Eye class="w-5 h-5 text-text-muted" />
                            <div>
                                <p class="text-sm text-text-muted">{{ t('channel.total_views') }}</p>
                                <p class="text-text-primary">{{ stats.totalViews?.toLocaleString() || 0 }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Video class="w-5 h-5 text-text-muted" />
                            <div>
                                <p class="text-sm text-text-muted">{{ t('channel.video_count') }}</p>
                                <p class="text-text-primary">{{ stats.videoCount }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
