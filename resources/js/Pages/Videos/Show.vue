<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { DropdownMenuItem } from 'reka-ui';
import { useFetch } from '@/Composables/useFetch';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import AdSlot from '@/Components/AdSlot.vue';
import { useToast } from '@/Composables/useToast';
import { useI18n } from '@/Composables/useI18n';
import { useTranslation } from '@/Composables/useTranslation';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import CommentSection from '@/Components/CommentSection.vue';
import ShareModal from '@/Components/ShareModal.vue';
import ReportModal from '@/Components/ReportModal.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
import EmbeddedVideoPlayer from '@/Components/EmbeddedVideoPlayer.vue';
import VideoAdPlayer from '@/Components/VideoAdPlayer.vue';
import { ThumbsUp, ThumbsDown, Share2, Flag, Bell, BellOff, Eye, ListVideo, Plus, Check, Loader2, Folder, Hash, Play, Shuffle, Repeat, SkipBack, SkipForward, ChevronLeft, ChevronRight, Download } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    translatedTags: { type: Array, default: null },
    relatedVideos: Array,
    userLike: String,
    isSubscribed: Boolean,
    sidebarAd: Object,
    bannerAbovePlayer: { type: Object, default: () => ({}) },
    bannerBelowPlayer: { type: Object, default: () => ({}) },
    playlistContext: { type: Object, default: null },
    userPlaylists: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    videoAdsEnabled: { type: Boolean, default: true },
});

const toast = useToast();
const { t, localizedUrl } = useI18n();

// Ad system refs
const adPlayerRef = ref(null);
const videoPlayerRef = ref(null);
const preRollDone = ref(false);
const postRollDone = ref(false);

// Get the Vidstack player instance exposed by VideoPlayer
const getPlayerElement = () => videoPlayerRef.value?.getPlayer() ?? null;

// Get the underlying <video> element (used for ad event listeners)
const getVideoElement = () => getPlayerElement()?.querySelector('video') ?? null;

// Ad event handlers — use media-player API for pause/play
const onAdStarted = (placement) => {
    const player = getPlayerElement();
    if (player && !player.paused) player.pause();
};

const resumePlayer = (player) => {
    player.play().catch((err) => {
        console.warn('[Show.vue] Failed to resume video after ad:', err);
        toast.error(t('video.tap_play_to_resume'));
    });
};

const onAdEnded = (placement) => {
    if (placement === 'pre_roll') {
        preRollDone.value = true;
        const player = getPlayerElement();
        if (player) resumePlayer(player);
    } else if (placement === 'mid_roll') {
        const player = getPlayerElement();
        if (player) resumePlayer(player);
    } else if (placement === 'post_roll') {
        goToNextPlaylistVideo();
    }
    // post_roll: video already ended, nothing to resume
};

const onAdRequestPause = () => {
    const player = getPlayerElement();
    if (player && !player.paused) player.pause();
};

const onAdRequestPlay = () => {
    const player = getPlayerElement();
    if (player) resumePlayer(player);
};

// Ad setup cleanup — remove listeners on unmount
let adVideoEl = null;
let adTimeupdateHandler = null;
let adEndedHandler = null;

const cleanupAdSetup = () => {
    if (adVideoEl && (adTimeupdateHandler || adEndedHandler)) {
        if (adTimeupdateHandler) adVideoEl.removeEventListener('timeupdate', adTimeupdateHandler);
        if (adEndedHandler) adVideoEl.removeEventListener('ended', adEndedHandler);
        adVideoEl = null;
        adTimeupdateHandler = null;
        adEndedHandler = null;
    }
};

// Setup video event listeners for ad triggers
const setupAdTriggers = async () => {
    const video = getVideoElement();
    if (!video || !adPlayerRef.value) return;

    // Pre-roll: wait for ads to load, then try to play before video starts
    if (!preRollDone.value) {
        const played = await adPlayerRef.value.triggerPreRoll();
        if (!played) preRollDone.value = true;
    }

    // Store refs for cleanup; remove any previous listeners
    cleanupAdSetup();
    adVideoEl = video;

    adTimeupdateHandler = () => {
        if (adPlayerRef.value && !adPlayerRef.value.isPlaying) {
            adPlayerRef.value.checkMidRoll(video.currentTime);
        }
    };
    adEndedHandler = async () => {
        let postRollPlayed = false;
        if (!postRollDone.value && adPlayerRef.value) {
            const played = await adPlayerRef.value.triggerPostRoll();
            postRollPlayed = !!played;
            postRollDone.value = true;
        }
        if (!postRollPlayed) {
            goToNextPlaylistVideo();
        }
    };

    video.addEventListener('timeupdate', adTimeupdateHandler);
    video.addEventListener('ended', adEndedHandler);
};

// Fired by VideoPlayer's `ready` event (Vidstack's own `can-play` event) once the
// player is genuinely ready to play — no DOM polling needed.
const onPlayerReady = () => {
    setupAdTriggers();
};

const hlsPlaylistUrl = computed(() => props.video.hls_playlist_url || '');

const page = usePage();
const user = computed(() => page.props.auth?.user);
const canDownload = computed(() => {
    return user.value && (user.value.is_pro || user.value.id === props.video?.user_id || user.value.is_admin);
});

const playlistMode = ref('play'); // play | shuffle | loop
const playlistContext = computed(() => props.playlistContext || null);
const playlistVideos = computed(() => playlistContext.value?.videos || []);
const hasPlaylistContext = computed(() => !!playlistContext.value && playlistVideos.value.length > 0);
const playlistRailRef = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const touchStartX = ref(0);
const touchStartScrollLeft = ref(0);
const currentPlaylistIndex = computed(() => {
    const idx = Number(playlistContext.value?.currentIndex ?? -1);
    return Number.isInteger(idx) ? idx : -1;
});

const buildPlaylistVideoHref = (item, index) => {
    if (!playlistContext.value || !item?.slug) return '#';
    const baseUrl = localizedUrl(`/${item.slug}`);
    const params = new URLSearchParams({
        playlist: playlistContext.value.slug,
        index: String(index),
    });
    return `${baseUrl}?${params.toString()}`;
};

const goToPlaylistIndex = (index) => {
    if (!hasPlaylistContext.value || index < 0 || index >= playlistVideos.value.length) return;
    const targetVideo = playlistVideos.value[index];
    const href = buildPlaylistVideoHref(targetVideo, index);
    router.visit(href, {
        preserveScroll: true,
        preserveState: false,
    });
};

const getNextPlaylistIndex = () => {
    if (!hasPlaylistContext.value) return null;

    const total = playlistVideos.value.length;
    const current = currentPlaylistIndex.value;
    if (total === 0 || current < 0) return null;

    if (playlistMode.value === 'loop') {
        return current;
    }

    if (playlistMode.value === 'shuffle') {
        if (total === 1) return current;
        let next = current;
        while (next === current) {
            next = Math.floor(Math.random() * total);
        }
        return next;
    }

    if (current + 1 < total) {
        return current + 1;
    }

    return null;
};

const goToNextPlaylistVideo = () => {
    const nextIndex = getNextPlaylistIndex();
    if (nextIndex === null) return;
    goToPlaylistIndex(nextIndex);
};

const goToPreviousPlaylistVideo = () => {
    if (!hasPlaylistContext.value) return;
    const previousIndex = currentPlaylistIndex.value - 1;
    if (previousIndex >= 0) {
        goToPlaylistIndex(previousIndex);
    }
};

const playAllFromStart = () => {
    playlistMode.value = 'play';
    goToPlaylistIndex(0);
};

const toggleShuffleMode = () => {
    playlistMode.value = playlistMode.value === 'shuffle' ? 'play' : 'shuffle';
};

const toggleLoopMode = () => {
    playlistMode.value = playlistMode.value === 'loop' ? 'play' : 'loop';
};

const updatePlaylistRailButtons = () => {
    const el = playlistRailRef.value;
    if (!el) {
        canScrollLeft.value = false;
        canScrollRight.value = false;
        return;
    }

    canScrollLeft.value = el.scrollLeft > 4;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
};

const scrollPlaylistRail = (direction) => {
    const el = playlistRailRef.value;
    if (!el) return;
    const amount = Math.max(Math.floor(el.clientWidth * 0.85), 220);
    el.scrollBy({
        left: direction === 'left' ? -amount : amount,
        behavior: 'smooth',
    });
    setTimeout(updatePlaylistRailButtons, 250);
};

const onPlaylistRailTouchStart = (event) => {
    const touch = event.touches?.[0];
    if (!touch) return;
    touchStartX.value = touch.clientX;
    touchStartScrollLeft.value = playlistRailRef.value?.scrollLeft || 0;
};

const onPlaylistRailTouchMove = (event) => {
    const touch = event.touches?.[0];
    const el = playlistRailRef.value;
    if (!touch || !el) return;
    const delta = touch.clientX - touchStartX.value;
    el.scrollLeft = touchStartScrollLeft.value - delta;
    updatePlaylistRailButtons();
};

const getPlaylistItemAriaLabel = (playlistVideo, index) => {
    return `${t('playlist.open_video')} ${index + 1}: ${playlistVideo.title}`;
};

const liked = ref(props.userLike === 'like');
const disliked = ref(props.userLike === 'dislike');
const likesCount = ref(props.video.likes_count);
const dislikesCount = ref(props.video.dislikes_count);
const subscribed = ref(props.isSubscribed);
const subscribing = ref(false);

const { post, del } = useFetch();

const handleLike = async () => {
    if (!user.value) { router.visit('/login'); return; }
    const { ok, data } = await post(`/videos/${props.video.id}/like`);
    if (ok && data) {
        liked.value = data.liked;
        disliked.value = data.disliked;
        likesCount.value = data.likesCount;
        dislikesCount.value = data.dislikesCount;
    } else if (!ok) {
        toast.error(data?.message || t('common.error'));
    }
};

const handleDislike = async () => {
    if (!user.value) { router.visit('/login'); return; }
    const { ok, data } = await post(`/videos/${props.video.id}/dislike`);
    if (ok && data) {
        liked.value = data.liked;
        disliked.value = data.disliked;
        likesCount.value = data.likesCount;
        dislikesCount.value = data.dislikesCount;
    } else if (!ok) {
        toast.error(data?.message || t('common.error'));
    }
};

const handleSubscribe = async () => {
    if (!user.value) { router.visit('/login'); return; }
    subscribing.value = true;
    const fn = subscribed.value ? (url) => del(url, null) : (url) => post(url, {});
    const { ok, data } = await fn(`/channel/${props.video.user.id}/subscribe`);
    if (ok) {
        subscribed.value = !subscribed.value;
    } else {
        toast.error(data?.message || t('common.error'));
    }
    subscribing.value = false;
};

// Save to Playlist
const playlists = ref(props.userPlaylists.map(p => ({ ...p })));
const savingPlaylist = ref(null);
const newPlaylistTitle = ref('');
const creatingPlaylist = ref(false);

const toggleVideoInPlaylist = async (playlist) => {
    if (savingPlaylist.value === playlist.id) return;
    savingPlaylist.value = playlist.id;
    const idx = playlists.value.findIndex(p => p.id === playlist.id);
    if (playlist.has_video) {
        const { ok } = await del(`/playlists/${playlist.id}/videos`, { video_id: props.video.id });
        if (ok) {
            if (idx !== -1) {
                playlists.value[idx] = { ...playlists.value[idx], has_video: false, videos_count: Math.max(0, (playlist.videos_count || 0) - 1) };
            }
            toast.success(`Removed from "${playlist.title}"`);
        }
    } else {
        const { ok } = await post(`/playlists/${playlist.id}/videos`, { video_id: props.video.id });
        if (ok) {
            if (idx !== -1) {
                playlists.value[idx] = { ...playlists.value[idx], has_video: true, videos_count: (playlist.videos_count || 0) + 1 };
            }
            toast.success(`Added to "${playlist.title}"`);
        }
    }
    savingPlaylist.value = null;
};

const createAndAddPlaylist = async () => {
    if (!newPlaylistTitle.value.trim() || creatingPlaylist.value) return;
    creatingPlaylist.value = true;
    const { ok, data } = await post('/playlists', {
        title: newPlaylistTitle.value.trim(),
        description: '',
    });
    if (ok && data) {
        const newPl = { ...data, has_video: false, videos_count: 0 };
        playlists.value.push(newPl);
        newPlaylistTitle.value = '';
        await toggleVideoInPlaylist(newPl);
    }
    creatingPlaylist.value = false;
};

onMounted(() => {
    // Ad triggers are set up via the VideoPlayer `ready` event (see onPlayerReady)
    // rather than here, since the player element doesn't exist until it mounts.
    window.addEventListener('resize', updatePlaylistRailButtons);
    setTimeout(updatePlaylistRailButtons, 0);
});
onUnmounted(() => {
    window.removeEventListener('resize', updatePlaylistRailButtons);
    cleanupAdSetup();
});

watch([hasPlaylistContext, currentPlaylistIndex], () => {
    setTimeout(updatePlaylistRailButtons, 0);
});

// Report modal
const showReportModal = ref(false);

// Share modal
const showShareModal = ref(false);
const shareUrl = computed(() => window.location.href);

const handleShare = () => {
    showShareModal.value = true;
};

const formattedViews = computed(() => {
    const views = props.video.views_count;
    return views.toLocaleString();
});

// ── Translation ──
const { isTranslated, translateItem, translateBatch, getTranslated } = useTranslation();

const translatedTitle = ref(props.video.title);
const translatedDescription = ref(props.video.description);

// Auto-translate video content when locale is non-default
onMounted(async () => {
    if (isTranslated.value) {
        // Translate main video
        const result = await translateItem('video', props.video.id, ['title', 'description']);
        if (result) {
            if (result.title) translatedTitle.value = result.title;
            if (result.description) translatedDescription.value = result.description;
        }

        // Translate related video titles
        if (props.relatedVideos?.length) {
            const ids = props.relatedVideos.map(v => v.id);
            await translateBatch('video', ids, ['title']);
        }
    }
});

const getRelatedTitle = (video) => {
    if (!isTranslated.value) return video.title;
    return getTranslated('video', video.id, 'title', video.title);
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="flex flex-col xl:flex-row gap-6">
            <!-- Main Content -->
            <div class="flex-1 min-w-0">
                <!-- Banner Ad Above Player -->
                <div v-if="bannerAbovePlayer?.enabled" class="flex justify-center mb-2">
                    <!-- Desktop banner (728x90) -->
                    <div class="hidden md:block">
                        <AdSlot v-if="bannerAbovePlayer.html" :html="bannerAbovePlayer.html" />
                        <a v-else-if="bannerAbovePlayer.image" :href="bannerAbovePlayer.link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerAbovePlayer.image" alt="Advertisement" style="max-width: 728px; max-height: 90px;" class="rounded" loading="lazy" decoding="async" />
                        </a>
                    </div>
                    <!-- Mobile banner (300x100 / 300x50) -->
                    <div class="md:hidden">
                        <AdSlot v-if="bannerAbovePlayer.mobile_html" :html="bannerAbovePlayer.mobile_html" />
                        <AdSlot v-else-if="bannerAbovePlayer.html" :html="bannerAbovePlayer.html" />
                        <a v-else-if="bannerAbovePlayer.mobile_image" :href="bannerAbovePlayer.mobile_link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerAbovePlayer.mobile_image" alt="Advertisement" style="max-width: 300px; max-height: 100px;" class="rounded" loading="lazy" decoding="async" />
                        </a>
                    </div>
                </div>

                <!-- Video Player -->
                <div v-if="video.is_embedded" class="aspect-video bg-black rounded-xl overflow-hidden relative">
                    <EmbeddedVideoPlayer
                        :video="video"
                        :autoplay="false"
                        :show-info="false"
                    />
                </div>
                <div v-else class="aspect-video bg-black rounded-xl overflow-hidden relative">
                    <VideoPlayer
                        ref="videoPlayerRef"
                        :src="video.video_url"
                        :poster="video.thumbnail_url"
                        :title="seo.thumbnailAlt || video.title"
                        :hls-playlist="hlsPlaylistUrl"
                        :autoplay="false"
                        :preview-thumbnails="video.preview_thumbnails_url || ''"
                        @ready="onPlayerReady"
                    />
                    <VideoAdPlayer
                        v-if="videoAdsEnabled"
                        ref="adPlayerRef"
                        :category-id="video.category_id"
                        :video-duration="video.duration || 0"
                        @ad-started="onAdStarted"
                        @ad-ended="onAdEnded"
                        @ad-skipped="onAdEnded"
                        @request-pause="onAdRequestPause"
                        @request-play="onAdRequestPlay"
                    />
                </div>

                <!-- Banner Ad Below Player -->
                <div v-if="bannerBelowPlayer?.enabled" class="flex justify-center mt-2">
                    <!-- Desktop banner (728x90) -->
                    <div class="hidden md:block">
                        <AdSlot v-if="bannerBelowPlayer.html" :html="bannerBelowPlayer.html" />
                        <a v-else-if="bannerBelowPlayer.image" :href="bannerBelowPlayer.link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerBelowPlayer.image" alt="Advertisement" style="max-width: 728px; max-height: 90px;" class="rounded" loading="lazy" decoding="async" />
                        </a>
                    </div>
                    <!-- Mobile banner (300x100 / 300x50) -->
                    <div class="md:hidden">
                        <AdSlot v-if="bannerBelowPlayer.mobile_html" :html="bannerBelowPlayer.mobile_html" />
                        <AdSlot v-else-if="bannerBelowPlayer.html" :html="bannerBelowPlayer.html" />
                        <a v-else-if="bannerBelowPlayer.mobile_image" :href="bannerBelowPlayer.mobile_link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerBelowPlayer.mobile_image" alt="Advertisement" style="max-width: 300px; max-height: 100px;" class="rounded" loading="lazy" decoding="async" />
                        </a>
                    </div>
                </div>

                <div v-if="hasPlaylistContext" class="card p-3 sm:p-4 mt-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                        <div>
                            <p class="text-xs sm:text-sm text-text-muted">{{ t('playlist.label') }}</p>
                            <h3 class="font-semibold text-sm sm:text-base text-text-primary">
                                {{ playlistContext.title }}
                                <span class="text-text-muted">({{ currentPlaylistIndex + 1 }}/{{ playlistContext.videoCount }})</span>
                            </h3>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                @click="playAllFromStart"
                                class="btn btn-secondary gap-1.5 text-xs sm:text-sm px-2.5 py-1.5"
                                :title="t('playlist.play_all')"
                                :aria-label="t('playlist.play_all')"
                            >
                                <Play class="w-3.5 h-3.5" />
                                <span>{{ t('playlist.play_all') }}</span>
                            </button>
                            <button
                                @click="toggleShuffleMode"
                                class="btn gap-1.5 text-xs sm:text-sm px-2.5 py-1.5"
                                :class="playlistMode === 'shuffle' ? 'btn-primary' : 'btn-secondary'"
                                :title="t('playlist.shuffle')"
                                :aria-label="t('playlist.shuffle')"
                            >
                                <Shuffle class="w-3.5 h-3.5" />
                                <span>{{ t('playlist.shuffle') }}</span>
                            </button>
                            <button
                                @click="toggleLoopMode"
                                class="btn gap-1.5 text-xs sm:text-sm px-2.5 py-1.5"
                                :class="playlistMode === 'loop' ? 'btn-primary' : 'btn-secondary'"
                                :title="t('playlist.loop')"
                                :aria-label="t('playlist.loop')"
                            >
                                <Repeat class="w-3.5 h-3.5" />
                                <span>{{ t('playlist.loop') }}</span>
                            </button>
                            <button
                                @click="goToPreviousPlaylistVideo"
                                :disabled="currentPlaylistIndex <= 0"
                                class="btn btn-secondary p-2"
                                :title="t('playlist.previous')"
                                :aria-label="t('playlist.previous')"
                            >
                                <SkipBack class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click="goToNextPlaylistVideo"
                                :disabled="getNextPlaylistIndex() === null"
                                class="btn btn-secondary p-2"
                                :title="t('playlist.next')"
                                :aria-label="t('playlist.next')"
                            >
                                <SkipForward class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <button
                            v-if="canScrollLeft"
                            type="button"
                            class="absolute start-1 top-1/2 -translate-y-1/2 z-10 btn btn-secondary p-2 shadow"
                            @click="scrollPlaylistRail('left')"
                            :title="t('playlist.scroll_left')"
                            :aria-label="t('playlist.scroll_left')"
                        >
                            <ChevronLeft class="w-4 h-4" />
                        </button>

                        <div
                            ref="playlistRailRef"
                            class="flex gap-3 overflow-x-auto pb-1 px-8 sm:px-10 scrollbar-hide scroll-smooth"
                            @scroll.passive="updatePlaylistRailButtons"
                            @touchstart.passive="onPlaylistRailTouchStart"
                            @touchmove.passive="onPlaylistRailTouchMove"
                            @touchend.passive="updatePlaylistRailButtons"
                        >
                            <Link
                                v-for="(playlistVideo, idx) in playlistVideos"
                                :key="playlistVideo.id"
                                :href="buildPlaylistVideoHref(playlistVideo, idx)"
                                class="group shrink-0 w-56 sm:w-64 rounded-lg overflow-hidden border transition-all"
                                :style="idx === currentPlaylistIndex
                                    ? { borderColor: 'var(--color-accent)', boxShadow: '0 0 0 1px var(--color-accent) inset' }
                                    : { borderColor: 'var(--color-border)' }"
                                :title="playlistVideo.title"
                                :aria-label="getPlaylistItemAriaLabel(playlistVideo, idx)"
                            >
                                <div class="relative aspect-video bg-black">
                                    <img
                                        :src="playlistVideo.thumbnail_url || '/assets/default_avatar.webp'"
                                        :alt="playlistVideo.thumbnail_alt || playlistVideo.title"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <span class="absolute top-2 start-2 text-[11px] px-1.5 py-0.5 rounded bg-black/80 text-white">{{ idx + 1 }}</span>
                                    <span v-if="playlistVideo.duration_formatted" class="absolute bottom-2 end-2 text-[11px] px-1.5 py-0.5 rounded bg-black/80 text-white">{{ playlistVideo.duration_formatted }}</span>
                                </div>
                                <div class="p-2.5">
                                    <p class="text-xs font-medium line-clamp-2 text-text-primary">{{ playlistVideo.title }}</p>
                                    <p class="text-[11px] mt-1 text-text-muted">{{ playlistVideo.user?.username || t('playlist.unknown_creator') }}</p>
                                </div>
                            </Link>
                        </div>

                        <button
                            v-if="canScrollRight"
                            type="button"
                            class="absolute end-1 top-1/2 -translate-y-1/2 z-10 btn btn-secondary p-2 shadow"
                            @click="scrollPlaylistRail('right')"
                            :title="t('playlist.scroll_right')"
                            :aria-label="t('playlist.scroll_right')"
                        >
                            <ChevronRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Video Info -->
                <div class="mt-4">
                    <div class="flex items-start justify-between gap-2 sm:gap-4">
                        <h1 class="text-base sm:text-xl font-bold flex-1 line-clamp-2 sm:line-clamp-none text-text-primary">{{ translatedTitle }}</h1>
                        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0 text-xs sm:text-sm font-medium whitespace-nowrap text-text-secondary">
                            <Eye class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                            <span>{{ t('video.views', { count: formattedViews, n: video.views_count }) }}</span>
                            <span class="text-text-muted">•</span>
                            <span>{{ video.published_at ? new Date(video.published_at).toLocaleDateString() : new Date(video.created_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-4 mt-3 sm:mt-4">
                        <!-- Channel Info -->
                        <div class="flex items-center gap-2 sm:gap-4 min-w-0">
                            <Link :href="`/channel/${video.user.username}`" class="flex items-center gap-2 sm:gap-3 min-w-0">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 avatar shrink-0">
                                    <img :src="video.user.avatar_url || video.user.avatar || '/assets/default_avatar.webp'" :alt="video.user.avatar_alt || video.user.username" class="w-full h-full object-cover" loading="lazy" decoding="async" />
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-xs sm:text-base truncate text-text-primary">{{ video.user.username }}</p>
                                    <p class="text-[10px] sm:text-sm hidden sm:block text-text-muted">{{ video.user.subscriber_count }} {{ t('common.subscribers') }}</p>
                                </div>
                            </Link>
                            
                            <button
                                v-if="user && user.id !== video.user.id"
                                @click="handleSubscribe"
                                :disabled="subscribing"
                                :class="[
                                    'btn text-xs sm:text-base px-2.5 py-1.5 sm:px-4 sm:py-2',
                                    subscribed ? 'btn-secondary' : 'btn-primary'
                                ]"
                            >
                                <Loader2 v-if="subscribing" class="w-3.5 h-3.5 sm:w-4 sm:h-4 animate-spin" />
                                <template v-else>{{ subscribed ? (t('common.subscribed')) : (t('common.subscribe')) }}</template>
                            </button>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1 sm:gap-2 overflow-x-auto sm:overflow-visible scrollbar-hide">
                            <button
                                @click="handleLike"
                                class="btn btn-secondary gap-1 sm:gap-2 shrink-0 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2"
                                :style="{ color: liked ? '#22c55e' : undefined }"
                            >
                                <ThumbsUp class="w-3.5 h-3.5 sm:w-5 sm:h-5" :fill="liked ? 'currentColor' : 'none'" />
                                <span>{{ likesCount }}</span>
                            </button>
                            <button
                                @click="handleDislike"
                                class="btn btn-secondary gap-1 sm:gap-2 shrink-0 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2"
                                :style="{ color: disliked ? '#ef4444' : undefined }"
                            >
                                <ThumbsDown class="w-3.5 h-3.5 sm:w-5 sm:h-5" :fill="disliked ? 'currentColor' : 'none'" />
                                <span>{{ dislikesCount }}</span>
                            </button>

                            <button @click="handleShare" class="btn btn-secondary gap-1 sm:gap-2 shrink-0 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2">
                                <Share2 class="w-3.5 h-3.5 sm:w-5 sm:h-5" />
                                <span class="hidden sm:inline">{{ t('common.share') }}</span>
                            </button>

                            <a
                                v-if="canDownload"
                                :href="`/videos/${props.video.id}/download`"
                                class="btn btn-secondary gap-1 sm:gap-2 shrink-0 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2"
                                download
                            >
                                <Download class="w-3.5 h-3.5 sm:w-5 sm:h-5" />
                                <span class="hidden sm:inline">{{ t('common.download') }}</span>
                            </a>

                            <!--
                                Save to Playlist. Every row uses `@select.prevent`:
                                Reka closes a menu on item-select by default, but this
                                panel is a multi-toggle checklist plus an inline create
                                form, so no interaction inside it should close the menu.
                            -->
                            <div class="shrink-0">
                                <BaseDropdown
                                    v-if="user"
                                    :side-offset="8"
                                    content-class="w-[calc(100vw-2rem)] sm:w-72 max-w-72 rounded-xl shadow-xl overflow-hidden"
                                >
                                    <template #trigger>
                                        <button class="btn btn-secondary gap-1 sm:gap-2 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2">
                                            <ListVideo class="w-3.5 h-3.5 sm:w-5 sm:h-5" />
                                            <span class="hidden sm:inline">{{ t('common.save') }}</span>
                                        </button>
                                    </template>

                                    <div class="p-3 font-medium text-sm border-b border-border text-text-primary">{{ t('video.save_to_playlist') }}</div>
                                    <div class="max-h-60 overflow-y-auto scrollbar-hide">
                                        <DropdownMenuItem
                                            v-for="pl in playlists"
                                            :key="pl.id"
                                            :disabled="savingPlaylist === pl.id"
                                            class="flex items-center gap-3 w-full px-3 py-2.5 text-start text-sm transition-colors text-text-secondary cursor-pointer outline-none data-[highlighted]:bg-bg-secondary data-[disabled]:opacity-60"
                                            @select.prevent="toggleVideoInPlaylist(pl)"
                                        >
                                            <div
                                                class="w-5 h-5 rounded flex items-center justify-center shrink-0"
                                                :style="pl.has_video
                                                    ? { backgroundColor: 'var(--color-accent)', color: 'white' }
                                                    : { border: '2px solid var(--color-border)' }"
                                            >
                                                <Check v-if="pl.has_video" class="w-3.5 h-3.5" />
                                            </div>
                                            <span class="truncate flex-1">{{ pl.title }}</span>
                                            <Loader2 v-if="savingPlaylist === pl.id" class="w-4 h-4 animate-spin shrink-0" />
                                            <span v-else class="text-xs shrink-0 text-text-muted">{{ pl.videos_count }} videos</span>
                                        </DropdownMenuItem>
                                        <div v-if="!playlists.length" class="px-3 py-4 text-center text-sm text-text-muted">{{ t('playlist.no_playlists') }}</div>
                                    </div>
                                    <!--
                                        Reka's menu typeahead ignores keydowns whose target is an
                                        input/textarea, so typing a playlist name here does not
                                        hijack focus into the list above.
                                    -->
                                    <DropdownMenuItem
                                        class="p-2 border-t border-border outline-none"
                                        @select.prevent
                                    >
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model="newPlaylistTitle"
                                                type="text"
                                                :placeholder="t('playlist.new_name')"
                                                class="input text-sm flex-1"
                                                @keydown.enter.prevent="createAndAddPlaylist"
                                            />
                                            <button
                                                @click="createAndAddPlaylist"
                                                :disabled="!newPlaylistTitle.trim() || creatingPlaylist"
                                                class="btn btn-primary p-2"
                                            >
                                                <Loader2 v-if="creatingPlaylist" class="w-4 h-4 animate-spin" />
                                                <Plus v-else class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </DropdownMenuItem>
                                </BaseDropdown>

                                <button v-else @click="router.visit('/login')" class="btn btn-secondary gap-1 sm:gap-2 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2">
                                    <ListVideo class="w-3.5 h-3.5 sm:w-5 sm:h-5" />
                                    <span class="hidden sm:inline">{{ t('common.save') }}</span>
                                </button>
                            </div>

                            <button @click="user ? (showReportModal = true) : router.visit('/login')" class="btn btn-secondary gap-1 sm:gap-2 shrink-0 text-xs sm:text-sm px-2 sm:px-4 py-1.5 sm:py-2">
                                <Flag class="w-3.5 h-3.5 sm:w-5 sm:h-5" />
                                <span class="hidden sm:inline">{{ t('common.report') }}</span>
                            </button>

                        </div>
                    </div>

                    <!-- Description -->
                    <div class="card p-4 mt-4">
                        <p class="whitespace-pre-wrap text-text-secondary">{{ translatedDescription }}</p>
                    </div>

                    <!-- Category & Tags -->
                    <div v-if="video.category || (video.tags && video.tags.length)" class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-3 px-1">
                        <!-- Category -->
                        <Link
                            v-if="video.category"
                            :href="localizedUrl(`/category/${video.category.slug}`)"
                            class="inline-flex items-center gap-1.5 text-sm hover:opacity-80 transition-opacity text-text-secondary"
                        >
                            <Folder class="w-3.5 h-3.5 text-accent" />
                            <span>{{ video.category.name }}</span>
                        </Link>

                        <!-- Separator -->
                        <span v-if="video.category && video.tags?.length" class="text-xs" style="color: var(--color-border);">|</span>

                        <!-- Tags -->
                        <div v-if="video.tags && video.tags.length" class="flex flex-wrap items-center gap-1.5">
                            <Link
                                v-for="(tag, idx) in video.tags"
                                :key="tag"
                                :href="localizedUrl(`/tag/${encodeURIComponent(tag)}`)"
                                class="inline-flex items-center gap-0.5 text-sm hover:opacity-80 transition-opacity text-text-muted"
                            >
                                <Hash class="w-3 h-3" /><span>{{ translatedTags?.[idx] || tag }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Comments Section -->
                    <CommentSection :video-id="video.id" />
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full xl:w-80 xl:shrink-0">
                <!-- Ad Space - Only show if enabled and has code -->
                <div v-if="sidebarAd?.enabled && (sidebarAd?.code || sidebarAd?.mobileCode)" class="mb-6">
                    <div class="ad-container flex items-center justify-center">
                        <AdSlot :html="sidebarAd.code" class="hidden sm:flex items-center justify-center" />
                        <AdSlot :html="sidebarAd.mobileCode || sidebarAd.code" class="sm:hidden flex items-center justify-center" />
                    </div>
                </div>

                <h3 class="font-medium mb-4 text-text-primary">{{ t('video.related') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-4">
                    <VideoCard
                        v-for="relatedVideo in relatedVideos"
                        :key="relatedVideo.id"
                        :video="relatedVideo"
                    />
                </div>
            </div>
        </div>
    </AppLayout>

    <ShareModal v-model="showShareModal" :url="shareUrl" :title="props.video.title" />
    <ReportModal v-model="showReportModal" :reportable-id="props.video.id" reportable-type="video" />
</template>
