<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import CommentSection from '@/Components/CommentSection.vue';
import VideoPlayer from '@/Components/VideoPlayer.vue';
import { ThumbsUp, ThumbsDown, Share2, Flag, Bell, BellOff } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    relatedVideos: Array,
    userLike: String,
    isSubscribed: Boolean,
    sidebarAd: Object,
});

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

const handleLike = async () => {
    if (!user.value) {
        router.visit('/login');
        return;
    }
    
    const response = await fetch(`/videos/${props.video.id}/like`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    });
    const data = await response.json();
    liked.value = data.liked;
    disliked.value = data.disliked;
    likesCount.value = data.likesCount;
    dislikesCount.value = data.dislikesCount;
};

const handleDislike = async () => {
    if (!user.value) {
        router.visit('/login');
        return;
    }
    
    const response = await fetch(`/videos/${props.video.id}/dislike`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    });
    const data = await response.json();
    liked.value = data.liked;
    disliked.value = data.disliked;
    likesCount.value = data.likesCount;
    dislikesCount.value = data.dislikesCount;
};

const handleSubscribe = async () => {
    if (!user.value) {
        router.visit('/login');
        return;
    }
    
    const method = subscribed.value ? 'DELETE' : 'POST';
    await fetch(`/channel/${props.video.user.id}/subscribe`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
    });
    subscribed.value = !subscribed.value;
};

// Report modal
const showReportModal = ref(false);
const reportReason = ref('');
const reportDescription = ref('');
const reportSubmitting = ref(false);
const reportSuccess = ref(false);

const submitReport = async () => {
    if (!reportReason.value || !user.value) return;
    reportSubmitting.value = true;
    try {
        const res = await fetch('/reports', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                reportable_type: 'video',
                reportable_id: props.video.id,
                reason: reportReason.value,
                description: reportDescription.value,
            }),
        });
        if (res.ok) {
            reportSuccess.value = true;
            setTimeout(() => { showReportModal.value = false; reportSuccess.value = false; reportReason.value = ''; reportDescription.value = ''; }, 1500);
        }
    } catch (e) { /* silent */ }
    reportSubmitting.value = false;
};

const handleShare = async () => {
    const url = window.location.href;
    if (navigator.share) {
        try { await navigator.share({ title: props.video.title, url }); } catch (e) { /* cancelled */ }
    } else if (navigator.clipboard) {
        await navigator.clipboard.writeText(url);
        alert('Link copied to clipboard!');
    }
};

const formattedViews = computed(() => {
    const views = props.video.views_count;
    return views.toLocaleString();
});
</script>

<template>
    <Head :title="video.title">
        <meta name="description" :content="video.description?.substring(0, 160) || video.title" />
        <meta property="og:title" :content="video.title" />
        <meta property="og:description" :content="video.description?.substring(0, 160) || video.title" />
        <meta property="og:image" :content="video.thumbnail_url" />
        <meta property="og:type" content="video.other" />
        <meta property="og:video:duration" :content="String(video.duration || 0)" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="video.title" />
        <meta name="twitter:description" :content="video.description?.substring(0, 160) || video.title" />
        <meta name="twitter:image" :content="video.thumbnail_url" />
    </Head>

    <AppLayout>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Content -->
            <div class="flex-1">
                <!-- Video Player -->
                <div class="aspect-video bg-black rounded-xl overflow-hidden relative">
                    <VideoPlayer
                        :src="video.video_url"
                        :poster="video.thumbnail_url"
                        :qualities="video.qualities_available || []"
                        :hls-playlist="hlsPlaylistUrl"
                        :autoplay="false"
                        :preview-thumbnails="video.preview_thumbnails_url || ''"
                    />
                </div>

                <!-- Video Info -->
                <div class="mt-4">
                    <div class="flex items-start justify-between gap-4">
                        <h1 class="text-xl font-bold flex-1" style="color: var(--color-text-primary);">{{ video.title }}</h1>
                        <span class="text-sm font-medium whitespace-nowrap" style="color: var(--color-text-secondary);">{{ formattedViews }} views</span>
                    </div>
                    
                    <!-- Tags - Horizontally Scrollable -->
                    <div v-if="video.tags && video.tags.length" class="mt-3 -mx-1 px-1 overflow-x-auto scrollbar-hide">
                        <div class="flex gap-2 pb-2" style="min-width: max-content;">
                            <span
                                v-for="tag in video.tags"
                                :key="tag"
                                class="px-3 py-1 rounded-full text-sm font-medium whitespace-nowrap cursor-pointer hover:opacity-80 transition-opacity"
                                style="background-color: var(--color-bg-tertiary); color: var(--color-text-secondary);"
                            >
                                #{{ tag }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap items-center justify-between gap-4 mt-4">
                        <!-- Channel Info -->
                        <div class="flex items-center gap-4">
                            <Link :href="`/channel/${video.user.username}`" class="flex items-center gap-3">
                                <div class="w-10 h-10 avatar">
                                    <img v-if="video.user.avatar" :src="video.user.avatar" :alt="video.user.username" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-white font-medium" style="background-color: var(--color-accent);">
                                        {{ video.user.username.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium" style="color: var(--color-text-primary);">{{ video.user.username }}</p>
                                    <p class="text-sm" style="color: var(--color-text-muted);">{{ video.user.subscriber_count }} subscribers</p>
                                </div>
                            </Link>
                            
                            <button
                                v-if="user && user.id !== video.user.id"
                                @click="handleSubscribe"
                                :class="[
                                    'btn',
                                    subscribed ? 'btn-secondary' : 'btn-primary'
                                ]"
                            >
                                {{ subscribed ? 'Subscribed' : 'Subscribe' }}
                            </button>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <div class="flex items-center rounded-full" style="background-color: var(--color-bg-card);">
                                <button
                                    @click="handleLike"
                                    class="flex items-center gap-2 px-4 py-2 rounded-l-full hover:opacity-80"
                                    :style="{ color: liked ? '#22c55e' : 'var(--color-text-secondary)' }"
                                >
                                    <ThumbsUp class="w-5 h-5" :fill="liked ? 'currentColor' : 'none'" />
                                    <span>{{ likesCount }}</span>
                                </button>
                                <div class="w-px h-6" style="background-color: var(--color-border);"></div>
                                <button
                                    @click="handleDislike"
                                    class="flex items-center gap-2 px-4 py-2 rounded-r-full hover:opacity-80"
                                    :style="{ color: disliked ? '#ef4444' : 'var(--color-text-secondary)' }"
                                >
                                    <ThumbsDown class="w-5 h-5" :fill="disliked ? 'currentColor' : 'none'" />
                                </button>
                            </div>

                            <button @click="handleShare" class="btn btn-secondary gap-2">
                                <Share2 class="w-5 h-5" />
                                <span class="hidden sm:inline">Share</span>
                            </button>

                            <button @click="user ? (showReportModal = true) : router.visit('/login')" class="btn btn-secondary gap-2">
                                <Flag class="w-5 h-5" />
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
            <div class="lg:w-96">
                <!-- Ad Space - Only show if enabled and has code -->
                <div v-if="sidebarAd?.enabled && sidebarAd?.code" class="mb-6">
                    <div class="ad-container flex items-center justify-center">
                        <div v-html="sidebarAd.code" class="flex items-center justify-center"></div>
                    </div>
                </div>

                <h3 class="font-medium mb-4" style="color: var(--color-text-primary);">Related Videos</h3>
                <div class="space-y-4">
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
</template>
