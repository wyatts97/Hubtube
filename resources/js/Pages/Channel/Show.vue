<script setup>
import { Link } from '@inertiajs/vue3';
import { Video } from 'lucide-vue-next';
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

const { t, localizedUrl } = useI18n();
const { gridClass } = useVideoGrid();
</script>

<template>
    <SeoHead :seo="seo" />

    <ChannelLayout v-bind="props">
        <div v-if="videos.data.length" :class="gridClass">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <EmptyState
            v-else
            :icon="Video"
            :title="t('channel.no_videos')"
            :description="t('channel.no_videos_desc')"
        >
            <template v-if="isOwner" #action>
                <Link :href="localizedUrl('/upload')" class="btn btn-primary gap-2">
                    <Video class="w-4 h-4" />
                    {{ t('dashboard.upload_video') }}
                </Link>
            </template>
        </EmptyState>

        <ChannelPaginator :paginator="videos" />
    </ChannelLayout>
</template>
