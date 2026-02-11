<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Calendar, Eye, Video } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    channel: Object,
    stats: Object,
});

const tabs = computed(() => [
    { name: t('channel.videos') || 'Videos', href: `/channel/${props.channel.username}` },
    { name: t('channel.shorts') || 'Shorts', href: `/channel/${props.channel.username}/shorts` },
    { name: t('channel.playlists') || 'Playlists', href: `/channel/${props.channel.username}/playlists` },
    { name: t('channel.about') || 'About', href: `/channel/${props.channel.username}/about`, active: true },
]);

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
                <img v-if="channel.avatar" :src="channel.avatar" :alt="channel.username" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-xl font-bold">
                    {{ channel.username?.charAt(0)?.toUpperCase() }}
                </div>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">{{ channel.username }}</h1>
                <p class="text-dark-400">{{ t('channel.about') || 'About' }}</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Description -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">{{ t('channel.description') || 'Description' }}</h2>
                    <p v-if="channel.channel?.description" class="text-dark-300 whitespace-pre-wrap">
                        {{ channel.channel.description }}
                    </p>
                    <p v-else class="text-dark-500">{{ t('channel.no_videos_desc') || 'No description provided' }}</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="space-y-4">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">{{ t('channel.stats') || 'Stats' }}</h2>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <Calendar class="w-5 h-5 text-dark-400" />
                            <div>
                                <p class="text-dark-400 text-sm">{{ t('channel.joined', { date: '' }).replace('{date}', '').trim() || 'Joined' }}</p>
                                <p class="text-white">{{ formatDate(stats.joinedAt) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Eye class="w-5 h-5 text-dark-400" />
                            <div>
                                <p class="text-dark-400 text-sm">{{ t('channel.total_views') || 'Total Views' }}</p>
                                <p class="text-white">{{ stats.totalViews?.toLocaleString() || 0 }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Video class="w-5 h-5 text-dark-400" />
                            <div>
                                <p class="text-dark-400 text-sm">{{ t('channel.video_count') || 'Videos' }}</p>
                                <p class="text-white">{{ stats.videoCount }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
