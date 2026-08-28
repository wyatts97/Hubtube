<script setup>
/**
 * The shared shell for every channel tab: banner hero, identity block,
 * actions, stat strip, tab bar, and the sticky About/links/stats sidebar.
 *
 * Each of the six Channel pages used to render its own header and tab strip.
 * They had drifted apart — only Show.vue had a subscribe button, only Show.vue
 * showed stats, and the smaller pages used a different avatar size and a stale
 * colour palette. Everything shared now lives here; the pages supply a body.
 */
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, BellRing, Flag, Loader2, MoreVertical, Share2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BannerAd from '@/Components/UI/BannerAd.vue';
import ChannelSidebar from '@/Components/Channel/ChannelSidebar.vue';
import ProBadge from '@/Components/ProBadge.vue';
import ReportModal from '@/Components/ReportModal.vue';
import ShareModal from '@/Components/ShareModal.vue';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import { DropdownMenuItem } from 'reka-ui';
import { useChannelTabs } from '@/Composables/useChannelTabs';
import { useFetch } from '@/Composables/useFetch';
import { formatViews } from '@/Composables/useFormatters';
import { useI18n } from '@/Composables/useI18n';
import { useToast } from '@/Composables/useToast';

/**
 * Pages pass their whole prop bag with v-bind="props" so each tab stays
 * terse. The extras a given tab carries (videos, playlists, seo, ...) are not
 * declared here, so without this they would fall through onto AppLayout and
 * end up stringified as DOM attributes.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    channel: { type: Object, required: true },
    activeTab: { type: String, required: true },
    isOwner: { type: Boolean, default: false },
    isSubscribed: { type: Boolean, default: false },
    notificationsEnabled: { type: Boolean, default: true },
    subscriberCount: { type: Number, default: 0 },
    showLikedVideos: { type: Boolean, default: false },
    showWatchHistory: { type: Boolean, default: false },
    bannerAd: { type: Object, default: () => ({}) },
});

const { t, localizedUrl } = useI18n();
const toast = useToast();
const { post, del } = useFetch();
const page = usePage();

const authUser = computed(() => page.props.auth?.user);

const tabs = useChannelTabs(
    computed(() => props.channel),
    computed(() => props.activeTab),
    {
        liked: computed(() => props.showLikedVideos),
        history: computed(() => props.showWatchHistory),
    },
);

const subscribed = ref(props.isSubscribed);
const subCount = ref(props.subscriberCount);
const subscribing = ref(false);

const handleSubscribe = async () => {
    if (!authUser.value) {
        router.visit(localizedUrl('/login'));
        return;
    }

    subscribing.value = true;
    const request = subscribed.value ? del : post;
    const { ok, data } = await request(`/channel/${props.channel.id}/subscribe`);

    if (ok) {
        subscribed.value = !subscribed.value;
        // Trust the server's count when it sends one — the optimistic +/-1
        // drifts if the same account subscribes from two tabs.
        subCount.value = typeof data?.subscriberCount === 'number'
            ? data.subscriberCount
            : subCount.value + (subscribed.value ? 1 : -1);
    } else {
        toast.error(data?.message || t('common.error'));
    }

    subscribing.value = false;
};

/**
 * Per-subscription notification opt-in. The endpoint and the
 * subscriptions.notifications_enabled column already existed with no UI.
 */
const notifying = ref(props.notificationsEnabled);
const togglingNotifications = ref(false);

const toggleNotifications = async () => {
    togglingNotifications.value = true;
    const { ok, data } = await post(`/channel/${props.channel.id}/notifications`);

    if (ok) {
        notifying.value = data?.notificationsEnabled ?? !notifying.value;
    } else {
        toast.error(data?.error || t('common.error'));
    }

    togglingNotifications.value = false;
};

const showShare = ref(false);
const showReport = ref(false);

const shareUrl = computed(() =>
    typeof window !== 'undefined' ? window.location.href : `/channel/${props.channel.username}`,
);

const stats = computed(() => props.channel.stats || {});
</script>

<template>
    <AppLayout>
        <!-- Banner hero. This is the page's LCP element, so it is eager. -->
        <div class="relative w-full aspect-[6/1] md:aspect-[8/1] overflow-hidden rounded-card bg-bg-secondary">
            <img
                v-if="channel.banner_url"
                :src="channel.banner_url"
                :alt="channel.display_name"
                class="absolute inset-0 w-full h-full object-cover"
                loading="eager"
                fetchpriority="high"
                decoding="async"
            />
            <div
                v-else
                class="absolute inset-0 bg-gradient-to-br from-bg-card to-bg-secondary"
                aria-hidden="true"
            />
            <div
                class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-bg-primary/80 to-transparent"
                aria-hidden="true"
            />
        </div>

        <!-- Identity + actions -->
        <div class="flex flex-col sm:flex-row sm:items-end gap-4 px-1">
            <div class="w-20 h-20 sm:w-28 sm:h-28 avatar shrink-0 -mt-10 sm:-mt-14 ring-4 ring-bg-primary relative z-10">
                <img
                    :src="channel.avatar_url || '/images/default_avatar.webp'"
                    :alt="channel.display_name"
                    class="w-full h-full object-cover"
                    loading="eager"
                    decoding="async"
                />
            </div>

            <div class="flex-1 min-w-0 sm:pb-1">
                <h1 class="flex items-center gap-2 text-xl sm:text-2xl md:text-3xl font-bold text-text-primary">
                    <span class="truncate">{{ channel.display_name }}</span>
                    <span v-if="channel.is_verified" class="text-accent shrink-0" :title="t('common.verified')">&#10003;</span>
                    <ProBadge v-if="channel.is_pro" size="md" />
                </h1>
                <p class="mt-0.5 text-sm sm:text-base text-text-secondary">@{{ channel.username }}</p>
            </div>

            <div class="flex items-center gap-2 sm:pb-1">
                <button
                    v-if="authUser && !isOwner"
                    @click="handleSubscribe"
                    :disabled="subscribing"
                    :class="['btn', subscribed ? 'btn-secondary' : 'btn-primary']"
                >
                    <Loader2 v-if="subscribing" class="w-4 h-4 animate-spin" />
                    <template v-else>{{ subscribed ? t('common.subscribed') : t('common.subscribe') }}</template>
                </button>

                <button
                    v-if="authUser && !isOwner && subscribed"
                    @click="toggleNotifications"
                    :disabled="togglingNotifications"
                    class="btn btn-secondary px-2.5"
                    :aria-pressed="notifying"
                    :title="notifying ? t('channel.notifications_on') : t('channel.notifications_off')"
                >
                    <Loader2 v-if="togglingNotifications" class="w-4 h-4 animate-spin" />
                    <BellRing v-else-if="notifying" class="w-4 h-4" />
                    <Bell v-else class="w-4 h-4" />
                </button>

                <BaseDropdown align="end" content-class="min-w-44 p-1">
                    <template #trigger>
                        <button class="btn btn-ghost px-2.5" :aria-label="t('common.more')">
                            <MoreVertical class="w-4 h-4" />
                        </button>
                    </template>
                    <DropdownMenuItem
                        @select="showShare = true"
                        class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-sm rounded-lg text-text-primary outline-none data-[highlighted]:bg-bg-hover"
                    >
                        <Share2 class="w-4 h-4" />
                        {{ t('common.share') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="authUser && !isOwner"
                        @select="showReport = true"
                        class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-sm rounded-lg text-text-primary outline-none data-[highlighted]:bg-bg-hover"
                    >
                        <Flag class="w-4 h-4" />
                        {{ t('common.report') }}
                    </DropdownMenuItem>
                </BaseDropdown>
            </div>
        </div>

        <!-- Stat strip -->
        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 py-3 border-y border-border text-sm text-text-muted">
            <span>
                <span class="font-semibold text-text-primary">{{ formatViews(subCount) }}</span>
                {{ t('common.subscribers') }}
            </span>
            <span>
                <span class="font-semibold text-text-primary">{{ formatViews(stats.videos || 0) }}</span>
                {{ t('common.videos') }}
            </span>
            <span v-if="stats.views">
                <span class="font-semibold text-text-primary">{{ formatViews(stats.views) }}</span>
                {{ t('common.views') }}
            </span>
        </div>

        <!-- Tabs -->
        <div class="border-b border-border">
            <nav class="flex gap-4 sm:gap-6 overflow-x-auto scrollbar-hide -mx-1 px-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="tab.href"
                    :aria-current="tab.active ? 'page' : undefined"
                    :class="[
                        'pb-3 pt-3 px-1 border-b-2 transition-colors whitespace-nowrap shrink-0 text-sm sm:text-base',
                        tab.active
                            ? 'border-accent text-text-primary font-medium'
                            : 'border-transparent text-text-secondary hover:text-text-primary',
                    ]"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <BannerAd :config="bannerAd" />

        <!-- Sidebar collapses above the content below lg -->
        <ChannelSidebar :channel="channel" variant="mobile" class="lg:hidden mt-4" />

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
            <div class="lg:col-span-3 min-w-0">
                <slot />
            </div>
            <ChannelSidebar
                :channel="channel"
                variant="desktop"
                class="hidden lg:block lg:col-span-1 sticky top-20"
            />
        </div>

        <ShareModal v-model="showShare" :url="shareUrl" :title="channel.display_name" />
        <ReportModal
            v-if="authUser && !isOwner"
            v-model="showReport"
            :reportable-id="channel.id"
            reportable-type="user"
        />
    </AppLayout>
</template>
