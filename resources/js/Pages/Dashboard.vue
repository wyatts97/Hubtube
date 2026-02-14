<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Video, Eye, ThumbsUp, Users, Wallet, TrendingUp, Edit, BarChart3, Clock } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { timeAgo, formatViews } from '@/Composables/useFormatters';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const formatNumber = (n) => {
    if (n == null) return '0';
    return Number(n).toLocaleString();
};

const props = defineProps({
    stats: Object,
    recentVideos: Array,
    topVideos: Array,
});

const page = usePage();
const canEdit = computed(() => page.props.auth?.user?.can_edit_video);
const monetizationEnabled = computed(() => page.props.app?.monetization_enabled !== false);

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(parseFloat(amount));
};

const statCards = computed(() => {
    const cards = [
        { label: t('dashboard.total_videos') || 'Total Videos', value: () => formatViews(props.stats.totalVideos), icon: Video, color: '#3b82f6' },
        { label: t('dashboard.total_views') || 'Total Views', value: () => formatViews(props.stats.totalViews), icon: Eye, color: '#8b5cf6' },
        { label: t('dashboard.total_likes') || 'Total Likes', value: () => formatViews(props.stats.totalLikes), icon: ThumbsUp, color: '#ef4444' },
        { label: t('dashboard.subscribers') || 'Subscribers', value: () => formatViews(props.stats.subscriberCount), icon: Users, color: '#22c55e' },
    ];
    if (monetizationEnabled.value) {
        cards.push({ label: t('dashboard.wallet_balance') || 'Wallet Balance', value: () => formatCurrency(props.stats.walletBalance), icon: Wallet, color: '#f59e0b' });
    }
    return cards;
});

const statsGridCols = computed(() => monetizationEnabled.value ? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5' : 'grid-cols-2 sm:grid-cols-4');
</script>

<template>
    <Head :title="t('dashboard.title') || 'Creator Dashboard'" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between gap-3 mb-6">
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('dashboard.title') || 'Creator Dashboard' }}</h1>
                    <p class="mt-1 text-sm sm:text-base" style="color: var(--color-text-secondary);">{{ t('dashboard.overview') || 'Overview of your channel performance' }}</p>
                </div>
                <Link href="/upload" class="btn btn-primary gap-2 flex-shrink-0 text-sm sm:text-base">
                    <Video class="w-4 h-4" />
                    <span class="hidden sm:inline">{{ t('dashboard.upload_video') || 'Upload Video' }}</span>
                    <span class="sm:hidden">{{ t('nav.upload') || 'Upload' }}</span>
                </Link>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-2 sm:gap-4 mb-6 sm:mb-8" :class="statsGridCols">
                <div v-for="stat in statCards" :key="stat.label" class="card p-3 sm:p-4">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: stat.color + '15' }">
                            <component :is="stat.icon" class="w-4 h-4 sm:w-5 sm:h-5" :style="{ color: stat.color }" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs truncate" style="color: var(--color-text-muted);">{{ stat.label }}</p>
                            <p class="text-sm sm:text-lg font-bold truncate" style="color: var(--color-text-primary);">{{ stat.value() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Videos -->
                <div class="card">
                    <div class="p-4 border-b flex items-center justify-between" style="border-color: var(--color-border);">
                        <h2 class="font-semibold" style="color: var(--color-text-primary);">{{ t('dashboard.recent_videos') || 'Recent Videos' }}</h2>
                        <Link href="/settings" class="text-sm" style="color: var(--color-accent);">{{ t('common.manage') || 'Manage' }}</Link>
                    </div>
                    <div v-if="recentVideos?.length">
                        <div
                            v-for="video in recentVideos"
                            :key="video.id"
                            class="flex items-center gap-2 sm:gap-3 p-2 sm:p-3 border-b last:border-b-0 hover:opacity-90"
                            style="border-color: var(--color-border);"
                        >
                            <div class="w-20 h-12 sm:w-24 sm:h-14 rounded-lg overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-secondary);">
                                <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" class="w-full h-full object-cover" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <Link :href="`/${video.slug}`" class="text-sm font-medium truncate block" style="color: var(--color-text-primary);">{{ video.title }}</Link>
                                <div class="flex items-center gap-2 sm:gap-3 mt-1 flex-wrap">
                                    <span class="text-xs" style="color: var(--color-text-muted);">{{ formatNumber(video.views_count) }} {{ t('common.videos') ? '' : '' }}{{ t('video.views', { count: '' }).replace('{count}', '').trim() || 'views' }}</span>
                                    <span class="text-xs hidden sm:inline" style="color: var(--color-text-muted);">{{ timeAgo(video.created_at) }}</span>
                                    <span
                                        class="text-xs px-1.5 py-0.5 rounded"
                                        :style="{
                                            backgroundColor: video.status === 'processed' && video.is_approved ? 'rgba(34,197,94,0.1)' : video.status === 'processed' && !video.is_approved ? 'rgba(249,115,22,0.1)' : 'rgba(234,179,8,0.1)',
                                            color: video.status === 'processed' && video.is_approved ? '#22c55e' : video.status === 'processed' && !video.is_approved ? '#f97316' : '#eab308',
                                        }"
                                    >{{ video.status === 'processed' && video.is_approved ? 'Published' : video.status === 'processed' && !video.is_approved ? 'Needs Moderation' : video.status }}</span>
                                </div>
                            </div>
                            <Link v-if="canEdit" :href="`/videos/${video.id}/edit`" class="p-2 rounded-lg hover:opacity-80" style="color: var(--color-text-muted);" title="Edit video">
                                <Edit class="w-4 h-4" />
                            </Link>
                            <Link v-else :href="`/videos/${video.id}/status`" class="p-2 rounded-lg hover:opacity-80" style="color: var(--color-text-muted);" title="View status">
                                <Clock class="w-4 h-4" />
                            </Link>
                        </div>
                    </div>
                    <div v-else class="p-8 text-center">
                        <Video class="w-10 h-10 mx-auto mb-2" style="color: var(--color-text-muted);" />
                        <p class="text-sm" style="color: var(--color-text-secondary);">{{ t('dashboard.no_videos') || 'No videos yet' }}</p>
                    </div>
                </div>

                <!-- Top Videos -->
                <div class="card">
                    <div class="p-4 border-b" style="border-color: var(--color-border);">
                        <h2 class="font-semibold flex items-center gap-2" style="color: var(--color-text-primary);">
                            <TrendingUp class="w-4 h-4" style="color: var(--color-accent);" />
                            {{ t('dashboard.top_performing') || 'Top Performing' }}
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
                        <p class="text-sm" style="color: var(--color-text-secondary);">{{ t('dashboard.upload_to_see') || 'Upload videos to see analytics' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
