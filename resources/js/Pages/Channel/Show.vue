<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed } from 'vue';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { sanitizeHtml } from '@/Composables/useSanitize';

const { t } = useI18n();
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Bell, BellOff, Share2, Flag, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    channel: Object,
    videos: Object,
    isSubscribed: Boolean,
    subscriberCount: Number,
    seo: { type: Object, default: () => ({}) },
    bannerAd: { type: Object, default: () => ({}) },
});

const bannerEnabled = computed(() => {
    const e = props.bannerAd?.enabled;
    return e === true || e === 'true' || e === 1 || e === '1';
});
const desktopBannerHtml = computed(() => {
    if (props.bannerAd?.type === 'image' && props.bannerAd?.image) {
        const img = `<img src="${props.bannerAd.image}" alt="Ad" style="max-width:728px;height:auto;">`;
        return props.bannerAd.link ? `<a href="${props.bannerAd.link}" target="_blank" rel="sponsored noopener">${img}</a>` : img;
    }
    return sanitizeHtml(props.bannerAd?.code || '');
});
const mobileBannerHtml = computed(() => {
    if (props.bannerAd?.mobileType === 'image' && props.bannerAd?.mobileImage) {
        const img = `<img src="${props.bannerAd.mobileImage}" alt="Ad" style="max-width:300px;height:auto;">`;
        return props.bannerAd.mobileLink ? `<a href="${props.bannerAd.mobileLink}" target="_blank" rel="sponsored noopener">${img}</a>` : img;
    }
    return sanitizeHtml(props.bannerAd?.mobileCode || props.bannerAd?.code || '');
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
    const { ok } = await fn(`/channel/${props.channel.id}/subscribe`);
    if (ok) {
        if (subscribed.value) {
            subCount.value--;
        } else {
            subCount.value++;
        }
        subscribed.value = !subscribed.value;
    }
    subscribing.value = false;
};

const tabs = computed(() => [
    { name: t('channel.videos') || 'Videos', href: `/channel/${props.channel.username}` },
    { name: t('channel.shorts') || 'Shorts', href: `/channel/${props.channel.username}/shorts` },
    { name: t('channel.playlists') || 'Playlists', href: `/channel/${props.channel.username}/playlists` },
    { name: t('channel.about') || 'About', href: `/channel/${props.channel.username}/about` },
]);
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <!-- Channel Banner -->
        <div class="relative h-32 sm:h-48 md:h-64 rounded-lg sm:rounded-xl overflow-hidden mb-4 sm:mb-6" style="background: linear-gradient(to right, var(--color-accent), var(--color-accent)); opacity: 0.85;">
            <img 
                v-if="channel.channel?.banner" 
                :src="channel.channel.banner" 
                :alt="channel.username"
                class="w-full h-full object-cover"
            />
        </div>

        <!-- Channel Info -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6 mb-6 sm:mb-8">
            <div class="w-20 h-20 sm:w-24 sm:h-24 md:w-32 md:h-32 avatar shrink-0">
                <img 
                    :src="channel.avatar_url || channel.avatar || '/images/default_avatar.webp'" 
                    :alt="channel.username"
                    class="w-full h-full object-cover"
                />
            </div>
            
            <div class="flex-1 min-w-0">
                <h1 class="text-xl sm:text-2xl md:text-3xl font-bold" style="color: var(--color-text-primary);">
                    {{ channel.username }}
                    <span v-if="channel.is_verified" class="ml-2" style="color: var(--color-accent);">✓</span>
                </h1>
                <p class="mt-1 text-sm sm:text-base" style="color: var(--color-text-secondary);">
                    @{{ channel.username }} • {{ subCount.toLocaleString() }} {{ t('common.subscribers') || 'subscribers' }} • {{ videos.total }} {{ t('common.videos') || 'videos' }}
                </p>
                <p v-if="channel.channel?.description" class="mt-2 line-clamp-2" style="color: var(--color-text-secondary);">
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
                <button class="btn btn-secondary">
                    <Share2 class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-4 sm:mb-6" style="border-bottom: 1px solid var(--color-border);">
            <nav class="flex gap-4 sm:gap-6 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="tab.href"
                    class="pb-3 px-1 border-b-2 border-transparent transition-colors hover:opacity-80 whitespace-nowrap shrink-0 text-sm sm:text-base"
                    style="color: var(--color-text-secondary);"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!-- Banner Ad -->
        <div v-if="bannerEnabled && (desktopBannerHtml || mobileBannerHtml)" class="mb-4 flex justify-center">
            <div class="hidden sm:block" v-html="desktopBannerHtml"></div>
            <div class="sm:hidden" v-html="mobileBannerHtml"></div>
        </div>

        <!-- Videos Grid -->
        <div v-if="videos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard
                v-for="video in videos.data"
                :key="video.id"
                :video="video"
            />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('channel.no_videos') || 'No videos yet' }}</p>
            <p class="mt-2" style="color: var(--color-text-muted);">{{ t('channel.no_videos_desc') || "This channel hasn't uploaded any videos" }}</p>
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
