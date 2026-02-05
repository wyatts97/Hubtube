<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Video, Eye, ThumbsUp, Users, Wallet, TrendingUp, Edit, BarChart3 } from 'lucide-vue-next';

const props = defineProps({
    stats: Object,
    recentVideos: Array,
    topVideos: Array,
});

const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(parseFloat(amount));
};

const timeAgo = (date) => {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    const intervals = [
        { label: 'year', seconds: 31536000 },
        { label: 'month', seconds: 2592000 },
        { label: 'week', seconds: 604800 },
        { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 },
        { label: 'minute', seconds: 60 },
    ];
    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
    }
    return 'Just now';
};

const statCards = [
    { label: 'Total Videos', value: () => formatNumber(props.stats.totalVideos), icon: Video, color: '#3b82f6' },
    { label: 'Total Views', value: () => formatNumber(props.stats.totalViews), icon: Eye, color: '#8b5cf6' },
    { label: 'Total Likes', value: () => formatNumber(props.stats.totalLikes), icon: ThumbsUp, color: '#ef4444' },
    { label: 'Subscribers', value: () => formatNumber(props.stats.subscriberCount), icon: Users, color: '#22c55e' },
    { label: 'Wallet Balance', value: () => formatCurrency(props.stats.walletBalance), icon: Wallet, color: '#f59e0b' },
];
</script>

<template>
    <Head title="Creator Dashboard" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">Creator Dashboard</h1>
                    <p class="mt-1" style="color: var(--color-text-secondary);">Overview of your channel performance</p>
                </div>
                <Link href="/upload" class="btn btn-primary gap-2">
                    <Video class="w-4 h-4" />
                    Upload Video
                </Link>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                <div v-for="stat in statCards" :key="stat.label" class="card p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" :style="{ backgroundColor: stat.color + '15' }">
                            <component :is="stat.icon" class="w-5 h-5" :style="{ color: stat.color }" />
                        </div>
                        <div>
                            <p class="text-xs" style="color: var(--color-text-muted);">{{ stat.label }}</p>
                            <p class="text-lg font-bold" style="color: var(--color-text-primary);">{{ stat.value() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Videos -->
                <div class="card">
                    <div class="p-4 border-b flex items-center justify-between" style="border-color: var(--color-border);">
                        <h2 class="font-semibold" style="color: var(--color-text-primary);">Recent Videos</h2>
                        <Link href="/settings" class="text-sm" style="color: var(--color-accent);">Manage</Link>
                    </div>
                    <div v-if="recentVideos?.length">
                        <div
                            v-for="video in recentVideos"
                            :key="video.id"
                            class="flex items-center gap-3 p-3 border-b last:border-b-0 hover:opacity-90"
                            style="border-color: var(--color-border);"
                        >
                            <div class="w-24 h-14 rounded-lg overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-secondary);">
                                <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" class="w-full h-full object-cover" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <Link :href="`/${video.slug}`" class="text-sm font-medium truncate block" style="color: var(--color-text-primary);">{{ video.title }}</Link>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ formatNumber(video.views_count) }} views</span>
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ timeAgo(video.created_at) }}</span>
                                    <span
                                        class="text-xs px-1.5 py-0.5 rounded"
                                        :style="{
                                            backgroundColor: video.status === 'processed' ? 'rgba(34,197,94,0.1)' : 'rgba(234,179,8,0.1)',
                                            color: video.status === 'processed' ? '#22c55e' : '#eab308',
                                        }"
                                    >{{ video.status }}</span>
                                </div>
                            </div>
                            <Link :href="`/videos/${video.id}/edit`" class="p-2 rounded-lg hover:opacity-80" style="color: var(--color-text-muted);">
                                <Edit class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                    <div v-else class="p-8 text-center">
                        <Video class="w-10 h-10 mx-auto mb-2" style="color: var(--color-text-muted);" />
                        <p class="text-sm" style="color: var(--color-text-secondary);">No videos yet</p>
                    </div>
                </div>

                <!-- Top Videos -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--color-border);">
                        <h2 class="font-semibold flex items-center gap-2" style="color: var(--color-text-primary);">
                            <TrendingUp class="w-4 h-4" style="color: var(--color-accent);" />
                            Top Performing
                        </h2>
                    </div>
                    <div v-if="topVideos?.length">
                        <div
                            v-for="(video, index) in topVideos"
                            :key="video.id"
                            class="flex items-center gap-3 p-3 border-b last:border-b-0"
                            style="border-color: var(--color-border);"
                        >
                            <span class="text-lg font-bold w-6 text-center" style="color: var(--color-text-muted);">{{ index + 1 }}</span>
                            <div class="w-20 h-12 rounded-lg overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-secondary);">
                                <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" class="w-full h-full object-cover" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <Link :href="`/${video.slug}`" class="text-sm font-medium truncate block" style="color: var(--color-text-primary);">{{ video.title }}</Link>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-xs flex items-center gap-1" style="color: var(--color-text-muted);">
                                        <Eye class="w-3 h-3" /> {{ formatNumber(video.views_count) }}
                                    </span>
                                    <span class="text-xs flex items-center gap-1" style="color: var(--color-text-muted);">
                                        <ThumbsUp class="w-3 h-3" /> {{ formatNumber(video.likes_count) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-8 text-center">
                        <BarChart3 class="w-10 h-10 mx-auto mb-2" style="color: var(--color-text-muted);" />
                        <p class="text-sm" style="color: var(--color-text-secondary);">Upload videos to see analytics</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
