<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useFetch } from '@/Composables/useFetch';
import { sanitizeHtml } from '@/Composables/useSanitize';
import { useToast } from '@/Composables/useToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import CommentSection from '@/Components/CommentSection.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
import EmbeddedVideoPlayer from '@/Components/EmbeddedVideoPlayer.vue';
import VideoAdPlayer from '@/Components/VideoAdPlayer.vue';
import { ThumbsUp, ThumbsDown, Share2, Flag, Bell, BellOff, Eye, ListVideo, Plus, Check, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    relatedVideos: Array,
    userLike: String,
    isSubscribed: Boolean,
    sidebarAd: Object,
    bannerAbovePlayer: { type: Object, default: () => ({}) },
    bannerBelowPlayer: { type: Object, default: () => ({}) },
    userPlaylists: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

const toast = useToast();

// Ad system refs
const adPlayerRef = ref(null);
const videoWrapperRef = ref(null);
const preRollDone = ref(false);
const postRollDone = ref(false);

// Get the underlying <video> element from the wrapper
const getVideoElement = () => {
    if (!videoWrapperRef.value) return null;
    return videoWrapperRef.value.querySelector('video');
};

// Ad event handlers
const onAdStarted = (placement) => {
    const video = getVideoElement();
    if (video && !video.paused) video.pause();
};

const onAdEnded = (placement) => {
    if (placement === 'pre_roll') {
        preRollDone.value = true;
        const video = getVideoElement();
        if (video) video.play().catch(() => {});
    } else if (placement === 'mid_roll') {
        const video = getVideoElement();
        if (video) video.play().catch(() => {});
    }
    // post_roll: video already ended, nothing to resume
};

const onAdRequestPause = () => {
    const video = getVideoElement();
    if (video && !video.paused) video.pause();
};

const onAdRequestPlay = () => {
    const video = getVideoElement();
    if (video) video.play().catch(() => {});
};

// Setup video event listeners for ad triggers
const setupAdTriggers = () => {
    const video = getVideoElement();
    if (!video || !adPlayerRef.value) return;

    // Pre-roll: try to play before video starts
    if (!preRollDone.value) {
        const played = adPlayerRef.value.triggerPreRoll();
        if (!played) preRollDone.value = true;
    }

    // Mid-roll: check on timeupdate
    video.addEventListener('timeupdate', () => {
        if (adPlayerRef.value && !adPlayerRef.value.isPlaying) {
            adPlayerRef.value.checkMidRoll(video.currentTime);
        }
    });

    // Post-roll: trigger when video ends
    video.addEventListener('ended', () => {
        if (!postRollDone.value && adPlayerRef.value) {
            const played = adPlayerRef.value.triggerPostRoll();
            postRollDone.value = true;
        }
    });
};

// Wait for video element to be ready, then setup ad triggers
const waitForVideoAndSetupAds = () => {
    const checkInterval = setInterval(() => {
        const video = getVideoElement();
        if (video) {
            clearInterval(checkInterval);
            // Small delay to let Plyr initialize
            setTimeout(setupAdTriggers, 500);
        }
    }, 200);
    // Safety: stop checking after 10s
    setTimeout(() => clearInterval(checkInterval), 10000);
};

const hlsPlaylistUrl = computed(() => {
    if (props.video.qualities_available?.length > 1 && !props.video.qualities_available?.includes('original') || 
        (props.video.qualities_available?.length > 1 && props.video.qualities_available?.some(q => q !== 'original'))) {
        // Construct HLS master playlist URL
        const videoPath = props.video.video_path;
        if (videoPath) {
            const basePath = videoPath.substring(0, videoPath.lastIndexOf('/'));
            return `/storage/${basePath}/processed/master.m3u8`;
        }
    }
    return '';
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

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
    }
};

const handleSubscribe = async () => {
    if (!user.value) { router.visit('/login'); return; }
    subscribing.value = true;
    const fn = subscribed.value ? del : post;
    const { ok } = await fn(`/channel/${props.video.user.id}/subscribe`);
    if (ok) subscribed.value = !subscribed.value;
    subscribing.value = false;
};

// Save to Playlist
const showPlaylistMenu = ref(false);
const playlists = ref(props.userPlaylists.map(p => ({ ...p })));
const savingPlaylist = ref(null);
const newPlaylistTitle = ref('');
const creatingPlaylist = ref(false);

const toggleVideoInPlaylist = async (playlist) => {
    if (savingPlaylist.value === playlist.id) return;
    savingPlaylist.value = playlist.id;
    if (playlist.has_video) {
        const { ok } = await del(`/playlists/${playlist.id}/videos`, { body: JSON.stringify({ video_id: props.video.id }) });
        if (ok) {
            playlist.has_video = false;
            toast.success(`Removed from "${playlist.title}"`);
        }
    } else {
        const { ok } = await post(`/playlists/${playlist.id}/videos`, { video_id: props.video.id });
        if (ok) {
            playlist.has_video = true;
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
        privacy: 'private',
    });
    if (ok && data) {
        const newPl = { ...data, has_video: false, videos_count: 0 };
        playlists.value.push(newPl);
        newPlaylistTitle.value = '';
        await toggleVideoInPlaylist(newPl);
    }
    creatingPlaylist.value = false;
};

// Close playlist menu on outside click
const closePlaylistMenu = (e) => {
    if (!e.target.closest('.playlist-menu-area')) {
        showPlaylistMenu.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closePlaylistMenu);
    // Setup ad triggers after component mounts (only for non-embedded videos)
    if (!props.video.is_embedded) {
        waitForVideoAndSetupAds();
    }
});
onUnmounted(() => {
    document.removeEventListener('click', closePlaylistMenu);
});

// Report modal
const showReportModal = ref(false);
const reportReason = ref('');
const reportDescription = ref('');
const reportSubmitting = ref(false);
const reportSuccess = ref(false);

const submitReport = async () => {
    if (!reportReason.value || !user.value) return;
    reportSubmitting.value = true;
    const { ok } = await post('/reports', {
        reportable_type: 'video',
        reportable_id: props.video.id,
        reason: reportReason.value,
        description: reportDescription.value,
    });
    if (ok) {
        reportSuccess.value = true;
        setTimeout(() => { showReportModal.value = false; reportSuccess.value = false; reportReason.value = ''; reportDescription.value = ''; }, 1500);
    }
    reportSubmitting.value = false;
};

// Share modal
const showShareModal = ref(false);
const linkCopied = ref(false);

const shareUrl = computed(() => window.location.href);

const handleShare = () => {
    showShareModal.value = true;
    linkCopied.value = false;
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        linkCopied.value = true;
        toast.success('Link copied to clipboard');
        setTimeout(() => { linkCopied.value = false; }, 2000);
    } catch (e) {
        toast.error('Failed to copy link');
    }
};

const shareToSocial = (platform) => {
    const url = encodeURIComponent(shareUrl.value);
    const title = encodeURIComponent(props.video.title);
    const urls = {
        twitter: `https://twitter.com/intent/tweet?url=${url}&text=${title}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
        reddit: `https://reddit.com/submit?url=${url}&title=${title}`,
        whatsapp: `https://wa.me/?text=${title}%20${url}`,
        telegram: `https://t.me/share/url?url=${url}&text=${title}`,
    };
    if (urls[platform]) {
        window.open(urls[platform], '_blank', 'width=600,height=500');
    }
};

const formattedViews = computed(() => {
    const views = props.video.views_count;
    return views.toLocaleString();
});
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Content -->
            <div class="flex-1">
                <!-- Banner Ad Above Player -->
                <div v-if="bannerAbovePlayer?.enabled" class="flex justify-center mb-2">
                    <!-- Desktop banner (728x90) -->
                    <div class="hidden md:block">
                        <div v-if="bannerAbovePlayer.type === 'html' && bannerAbovePlayer.html" v-html="sanitizeHtml(bannerAbovePlayer.html)"></div>
                        <a v-else-if="bannerAbovePlayer.type === 'image' && bannerAbovePlayer.image" :href="bannerAbovePlayer.link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerAbovePlayer.image" alt="Advertisement" style="max-width: 728px; max-height: 90px;" class="rounded" />
                        </a>
                    </div>
                    <!-- Mobile banner (300x100 / 300x50) -->
                    <div class="md:hidden">
                        <div v-if="bannerAbovePlayer.mobile_type === 'html' && bannerAbovePlayer.mobile_html" v-html="sanitizeHtml(bannerAbovePlayer.mobile_html)"></div>
                        <a v-else-if="bannerAbovePlayer.mobile_type === 'image' && bannerAbovePlayer.mobile_image" :href="bannerAbovePlayer.mobile_link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerAbovePlayer.mobile_image" alt="Advertisement" style="max-width: 300px; max-height: 100px;" class="rounded" />
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
                <div v-else ref="videoWrapperRef" class="aspect-video bg-black rounded-xl overflow-hidden relative">
                    <VideoPlayer
                        :src="video.video_url"
                        :poster="video.thumbnail_url"
                        :qualities="video.qualities_available || []"
                        :hls-playlist="hlsPlaylistUrl"
                        :autoplay="false"
                        :preview-thumbnails="video.preview_thumbnails_url || ''"
                    />
                    <VideoAdPlayer
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
                        <div v-if="bannerBelowPlayer.type === 'html' && bannerBelowPlayer.html" v-html="sanitizeHtml(bannerBelowPlayer.html)"></div>
                        <a v-else-if="bannerBelowPlayer.type === 'image' && bannerBelowPlayer.image" :href="bannerBelowPlayer.link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerBelowPlayer.image" alt="Advertisement" style="max-width: 728px; max-height: 90px;" class="rounded" />
                        </a>
                    </div>
                    <!-- Mobile banner (300x100 / 300x50) -->
                    <div class="md:hidden">
                        <div v-if="bannerBelowPlayer.mobile_type === 'html' && bannerBelowPlayer.mobile_html" v-html="sanitizeHtml(bannerBelowPlayer.mobile_html)"></div>
                        <a v-else-if="bannerBelowPlayer.mobile_type === 'image' && bannerBelowPlayer.mobile_image" :href="bannerBelowPlayer.mobile_link || '#'" target="_blank" rel="noopener noreferrer">
                            <img :src="bannerBelowPlayer.mobile_image" alt="Advertisement" style="max-width: 300px; max-height: 100px;" class="rounded" />
                        </a>
                    </div>
                </div>

                <!-- Video Info -->
                <div class="mt-4">
                    <div class="flex items-start justify-between gap-2 sm:gap-4">
                        <h1 class="text-base sm:text-xl font-bold flex-1 line-clamp-2 sm:line-clamp-none" style="color: var(--color-text-primary);">{{ video.title }}</h1>
                        <span class="text-xs sm:text-sm font-medium whitespace-nowrap flex items-center gap-1 sm:gap-1.5 shrink-0" style="color: var(--color-text-secondary);"><Eye class="w-3.5 h-3.5 sm:w-4 sm:h-4" /> {{ formattedViews }} views</span>
                    </div>
                    
                    <!-- Tags - Horizontally Scrollable -->
                    <div v-if="video.tags && video.tags.length" class="mt-3 -mx-1 px-1 overflow-x-auto scrollbar-hide">
                        <div class="flex gap-2 pb-2" style="min-width: max-content;">
                            <Link
                                v-for="tag in video.tags"
                                :key="tag"
                                :href="`/tag/${encodeURIComponent(tag)}`"
                                class="px-3 py-1 rounded-full text-sm font-medium whitespace-nowrap cursor-pointer hover:opacity-80 transition-opacity"
                                style="background-color: var(--color-bg-tertiary); color: var(--color-text-secondary);"
                            >
                                #{{ tag }}
                            </Link>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center justify-between gap-3 sm:gap-4 mt-3 sm:mt-4">
                        <!-- Channel Info -->
                        <div class="flex items-center gap-3 sm:gap-4 w-full sm:w-auto">
                            <Link :href="`/channel/${video.user.username}`" class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 avatar shrink-0">
                                    <img v-if="video.user.avatar" :src="video.user.avatar" :alt="video.user.username" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-white font-medium" style="background-color: var(--color-accent);">
                                        {{ video.user.username.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-sm sm:text-base truncate" style="color: var(--color-text-primary);">{{ video.user.username }}</p>
                                    <p class="text-xs sm:text-sm" style="color: var(--color-text-muted);">{{ video.user.subscriber_count }} subscribers</p>
                                </div>
                            </Link>
                            
                            <button
                                v-if="user && user.id !== video.user.id"
                                @click="handleSubscribe"
                                :disabled="subscribing"
                                :class="[
                                    'btn text-sm sm:text-base',
                                    subscribed ? 'btn-secondary' : 'btn-primary'
                                ]"
                            >
                                <Loader2 v-if="subscribing" class="w-4 h-4 animate-spin" />
                                <template v-else>{{ subscribed ? 'Subscribed' : 'Subscribe' }}</template>
                            </button>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1.5 sm:gap-2 w-full sm:w-auto overflow-x-auto scrollbar-hide -mx-1 px-1">
                            <div class="flex items-center rounded-full shrink-0" style="background-color: var(--color-bg-card);">
                                <button
                                    @click="handleLike"
                                    class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 rounded-l-full hover:opacity-80"
                                    :style="{ color: liked ? '#22c55e' : 'var(--color-text-secondary)' }"
                                >
                                    <ThumbsUp class="w-4 h-4 sm:w-5 sm:h-5" :fill="liked ? 'currentColor' : 'none'" />
                                    <span class="text-sm">{{ likesCount }}</span>
                                </button>
                                <div class="w-px h-6" style="background-color: var(--color-border);"></div>
                                <button
                                    @click="handleDislike"
                                    class="flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 rounded-r-full hover:opacity-80"
                                    :style="{ color: disliked ? '#ef4444' : 'var(--color-text-secondary)' }"
                                >
                                    <ThumbsDown class="w-4 h-4 sm:w-5 sm:h-5" :fill="disliked ? 'currentColor' : 'none'" />
                                    <span class="text-sm">{{ dislikesCount }}</span>
                                </button>
                            </div>

                            <button @click="handleShare" class="btn btn-secondary gap-1.5 sm:gap-2 shrink-0 text-sm">
                                <Share2 class="w-4 h-4 sm:w-5 sm:h-5" />
                                <span class="hidden sm:inline">Share</span>
                            </button>

                            <!-- Save to Playlist -->
                            <div class="relative playlist-menu-area shrink-0">
                                <button @click.stop="user ? (showPlaylistMenu = !showPlaylistMenu) : router.visit('/login')" class="btn btn-secondary gap-1.5 sm:gap-2 text-sm">
                                    <ListVideo class="w-4 h-4 sm:w-5 sm:h-5" />
                                    <span class="hidden sm:inline">Save</span>
                                </button>
                                <div
                                    v-if="showPlaylistMenu"
                                    class="absolute right-0 sm:right-0 top-full mt-2 w-[calc(100vw-2rem)] sm:w-72 max-w-72 rounded-xl shadow-xl z-50 overflow-hidden"
                                    style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
                                >
                                    <div class="p-3 font-medium text-sm" style="border-bottom: 1px solid var(--color-border); color: var(--color-text-primary);">Save to playlist</div>
                                    <div class="max-h-60 overflow-y-auto">
                                        <button
                                            v-for="pl in playlists"
                                            :key="pl.id"
                                            @click="toggleVideoInPlaylist(pl)"
                                            :disabled="savingPlaylist === pl.id"
                                            class="flex items-center gap-3 w-full px-3 py-2.5 text-left text-sm hover:opacity-80 transition-colors"
                                            style="color: var(--color-text-secondary);"
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
                                            <span v-else class="text-xs shrink-0" style="color: var(--color-text-muted);">{{ pl.videos_count }} videos</span>
                                        </button>
                                        <div v-if="!playlists.length" class="px-3 py-4 text-center text-sm" style="color: var(--color-text-muted);">No playlists yet</div>
                                    </div>
                                    <div class="p-2" style="border-top: 1px solid var(--color-border);">
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model="newPlaylistTitle"
                                                type="text"
                                                placeholder="New playlist name..."
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
                                    </div>
                                </div>
                            </div>

                            <button @click="user ? (showReportModal = true) : router.visit('/login')" class="btn btn-secondary gap-1.5 sm:gap-2 shrink-0 text-sm">
                                <Flag class="w-4 h-4 sm:w-5 sm:h-5" />
                                <span class="hidden sm:inline">Report</span>
                            </button>

                        </div>
                    </div>

                    <!-- Description -->
                    <div class="card p-4 mt-4">
                        <p class="text-sm mb-2" style="color: var(--color-text-muted);">
                            {{ new Date(video.published_at).toLocaleDateString() }}
                        </p>
                        <p class="whitespace-pre-wrap" style="color: var(--color-text-secondary);">{{ video.description }}</p>
                    </div>

                    <!-- Comments Section -->
                    <CommentSection :video-id="video.id" />
                </div>
            </div>

            <!-- Sidebar -->
            <div class="w-full lg:w-96 lg:shrink-0">
                <!-- Ad Space - Only show if enabled and has code -->
                <div v-if="sidebarAd?.enabled && sidebarAd?.code" class="mb-6">
                    <div class="ad-container flex items-center justify-center">
                        <div v-html="sanitizeHtml(sidebarAd.code)" class="flex items-center justify-center"></div>
                    </div>
                </div>

                <h3 class="font-medium mb-4" style="color: var(--color-text-primary);">Related Videos</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">
                    <VideoCard
                        v-for="relatedVideo in relatedVideos"
                        :key="relatedVideo.id"
                        :video="relatedVideo"
                    />
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Report Modal -->
    <Teleport to="body">
        <div v-if="showReportModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background-color: rgba(0,0,0,0.6);" @click.self="showReportModal = false">
            <div class="w-full max-w-md card p-6 shadow-xl" style="background-color: var(--color-bg-card);">
                <h3 class="text-lg font-bold mb-4" style="color: var(--color-text-primary);">Report Video</h3>

                <div v-if="reportSuccess" class="text-center py-4">
                    <p class="text-green-500 font-medium">Report submitted successfully!</p>
                </div>

                <form v-else @submit.prevent="submitReport" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">Reason</label>
                        <select v-model="reportReason" class="input" required>
                            <option value="" disabled>Select a reason</option>
                            <option value="spam">Spam or misleading</option>
                            <option value="harassment">Harassment or bullying</option>
                            <option value="illegal">Illegal content</option>
                            <option value="copyright">Copyright violation</option>
                            <option value="underage">Underage content</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Details (optional)</label>
                        <textarea v-model="reportDescription" class="input" rows="3" placeholder="Provide additional details..." maxlength="2000"></textarea>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="showReportModal = false" class="btn btn-secondary">Cancel</button>
                        <button type="submit" :disabled="reportSubmitting || !reportReason" class="btn btn-primary">
                            {{ reportSubmitting ? 'Submitting...' : 'Submit Report' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Share Modal -->
    <Teleport to="body">
        <div v-if="showShareModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background-color: rgba(0,0,0,0.6);" @click.self="showShareModal = false">
            <div class="w-full max-w-md card p-6 shadow-xl" style="background-color: var(--color-bg-card);">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold" style="color: var(--color-text-primary);">Share Video</h3>
                    <button @click="showShareModal = false" class="p-1 rounded hover:bg-white/10">
                        <svg class="w-5 h-5" style="color: var(--color-text-secondary);" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Social Share Buttons -->
                <div class="grid grid-cols-5 gap-3 mb-5">
                    <button @click="shareToSocial('twitter')" class="flex flex-col items-center gap-1.5 p-3 rounded-lg hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #1DA1F2;">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </div>
                        <span class="text-xs" style="color: var(--color-text-secondary);">X</span>
                    </button>
                    <button @click="shareToSocial('facebook')" class="flex flex-col items-center gap-1.5 p-3 rounded-lg hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #1877F2;">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </div>
                        <span class="text-xs" style="color: var(--color-text-secondary);">Facebook</span>
                    </button>
                    <button @click="shareToSocial('reddit')" class="flex flex-col items-center gap-1.5 p-3 rounded-lg hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #FF4500;">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>
                        </div>
                        <span class="text-xs" style="color: var(--color-text-secondary);">Reddit</span>
                    </button>
                    <button @click="shareToSocial('whatsapp')" class="flex flex-col items-center gap-1.5 p-3 rounded-lg hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #25D366;">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </div>
                        <span class="text-xs" style="color: var(--color-text-secondary);">WhatsApp</span>
                    </button>
                    <button @click="shareToSocial('telegram')" class="flex flex-col items-center gap-1.5 p-3 rounded-lg hover:bg-white/5 transition-colors">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #0088cc;">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        </div>
                        <span class="text-xs" style="color: var(--color-text-secondary);">Telegram</span>
                    </button>
                </div>

                <!-- Copy Link -->
                <div class="flex items-center gap-2">
                    <input 
                        type="text" 
                        :value="shareUrl" 
                        readonly 
                        class="input flex-1 text-sm"
                        @click="$event.target.select()"
                    />
                    <button @click="copyLink" class="btn btn-primary whitespace-nowrap text-sm px-4">
                        {{ linkCopied ? 'Copied!' : 'Copy' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
