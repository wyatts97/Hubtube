<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed } from 'vue';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { useToast } from '@/Composables/useToast';
import { useVideoGrid } from '@/Composables/useVideoGrid';
import BannerAd from '@/Components/UI/BannerAd.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Bell, BellOff, Flag, Loader2, Video, Eye, Calendar } from 'lucide-vue-next';

const { t } = useI18n();
const toast = useToast();
const { gridClass } = useVideoGrid();

const props = defineProps({
    channel: Object,
    videos: Object,
    isSubscribed: Boolean,
    subscriberCount: Number,
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const subscribed = ref(props.isSubscribed);
const subCount = ref(props.subscriberCount);

const { post, del } = useFetch();
const subscribing = ref(false);

const handleSubscribe = async () => {
    if (!user.value) {
        router.visit('/login');
        return;
    }
    
    subscribing.value = true;
    const fn = subscribed.value ? del : post;
    const { ok, data } = await fn(`/channel/${props.channel.id}/subscribe`);
    if (ok) {
        if (subscribed.value) {
            subCount.value--;
        } else {
            subCount.value++;
        }
        subscribed.value = !subscribed.value;
    } else {
        toast.error(data?.message || t('common.error') || 'Something went wrong');
    }
    subscribing.value = false;
};

const tSafe = (key, fallback) => {
    const val = t(key);
    return val === key ? fallback : val;
};

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
    items.push({ name: tSafe('channel.about', 'About'), href: `/channel/${props.channel.username}/about` });
    return items;
});
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <!-- Channel Info -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6 mb-6 sm:mb-8 pt-2 sm:pt-4">
            <div class="w-20 h-20 sm:w-24 sm:h-24 md:w-32 md:h-32 avatar shrink-0">
                <img 
                    :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" 
                    :alt="channel.username"
                    class="w-full h-full object-cover"
                    loading="lazy"
                    decoding="async"
                />
            </div>
            
            <div class="flex-1 min-w-0">
                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-text-primary">
                    {{ channel.username }}
                    <span v-if="channel.is_verified" class="ml-2 text-accent">✓</span>
                </h1>
                <p class="mt-1 text-sm sm:text-base text-text-secondary">
                    @{{ channel.username }}
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <Video class="w-4 h-4" />
                        {{ videos.total }} {{ t('common.videos') || 'videos' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <Bell class="w-4 h-4" />
                        <span class="font-medium text-text-primary">{{ subCount.toLocaleString() }}</span>&nbsp;{{ t('common.subscribers') || 'subscribers' }}
                    </span>
                    <span v-if="channel.channel?.total_views" class="inline-flex items-center gap-1.5">
                        <Eye class="w-4 h-4" />
                        {{ Number(channel.channel.total_views).toLocaleString() }} {{ t('common.views') || 'views' }}
                    </span>
                </div>
                <p v-if="channel.channel?.description" class="mt-2 line-clamp-2 text-text-secondary">
                    {{ channel.channel.description }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    v-if="user && user.id !== channel.id"
                    @click="handleSubscribe"
                    :disabled="subscribing"
                    :class="[
                        'btn',
                        subscribed ? 'btn-secondary' : 'btn-primary'
                    ]"
                >
                    <Loader2 v-if="subscribing" class="w-4 h-4 animate-spin" />
                    <template v-else>{{ subscribed ? (t('common.subscribed') || 'Subscribed') : (t('common.subscribe') || 'Subscribe') }}</template>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-4 sm:mb-6 border-b border-border">
            <nav class="flex gap-4 sm:gap-6 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="tab.href"
                    class="pb-3 px-1 border-b-2 border-transparent transition-colors hover:opacity-80 whitespace-nowrap shrink-0 text-sm sm:text-base text-text-secondary"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!-- Banner Ad -->
        <BannerAd :config="bannerAd" />

        <!-- Videos Grid -->
        <div v-if="videos.data.length" :class="gridClass">
            <VideoCard
                v-for="video in videos.data"
                :key="video.id"
                :video="video"
            />
        </div>

        <div v-else class="text-center py-16">
            <Video class="w-14 h-14 mx-auto mb-4 text-text-muted" />
            <p class="text-lg font-semibold text-text-secondary">{{ t('channel.no_videos') || 'No videos yet' }}</p>
            <p class="mt-2 text-sm text-text-muted">{{ t('channel.no_videos_desc') || "This channel hasn't uploaded any videos" }}</p>
            <Link v-if="user && user.id === channel.id" href="/upload" class="btn btn-primary mt-5 gap-2">
                <Video class="w-4 h-4" />
                {{ t('dashboard.upload_video') || 'Upload Your First Video' }}
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="px-4 py-2 rounded-lg text-sm"
                    :style="link.active 
                        ? { backgroundColor: 'var(--color-accent)', color: 'white' } 
                        : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                    v-html="link.label"
                    preserve-scroll
                />
            </template>
        </div>
    </AppLayout>
</template>
