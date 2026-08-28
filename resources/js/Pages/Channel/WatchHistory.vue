<script setup>
import { History } from 'lucide-vue-next';
import ChannelLayout from '@/Layouts/ChannelLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import VideoCard from '@/Components/VideoCard.vue';
import ChannelPaginator from '@/Components/Channel/ChannelPaginator.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { useI18n } from '@/Composables/useI18n';
import { useVideoGrid } from '@/Composables/useVideoGrid';

const props = defineProps({
    channel: Object,
    videos: Object,
    activeTab: String,
    isOwner: Boolean,
    isSubscribed: Boolean,
    notificationsEnabled: Boolean,
    subscriberCount: Number,
    showLikedVideos: Boolean,
    showWatchHistory: Boolean,
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { gridClass } = useVideoGrid();
</script>

<template>
    <SeoHead :seo="seo" :title="`${channel.display_name} — ${t('channel.recently_watched')}`" />

    <ChannelLayout v-bind="props">
        <div v-if="videos.data.length" :class="gridClass">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <EmptyState v-else :icon="History" :title="t('channel.no_watch_history')" />

        <ChannelPaginator :paginator="videos" :only="['videos']" />
    </ChannelLayout>
</template>
