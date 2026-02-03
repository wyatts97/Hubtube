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

const formattedViews = computed(() => {
    const views = props.video.views_count;
    return views.toLocaleString();
});
</script>

<template>
    <Head :title="video.title" />

    <AppLayout>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Main Content -->
            <div class="flex-1">
                <!-- Video Player -->
                <div class="aspect-video bg-black rounded-xl overflow-hidden">
                    <VideoPlayer
                        :src="video.video_url"
                        :poster="video.thumbnail_url"
                        :qualities="video.qualities_available || []"
                        :hls-playlist="hlsPlaylistUrl"
                        :autoplay="true"
                    />
                </div>

                <!-- Video Info -->
                <div class="mt-4">
                    <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">{{ video.title }}</h1>
                    
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
                                    :style="{ color: liked ? 'var(--color-accent)' : 'var(--color-text-secondary)' }"
                                >
                                    <ThumbsUp class="w-5 h-5" :fill="liked ? 'currentColor' : 'none'" />
                                    <span>{{ likesCount }}</span>
                                </button>
                                <div class="w-px h-6" style="background-color: var(--color-border);"></div>
                                <button
                                    @click="handleDislike"
                                    class="flex items-center gap-2 px-4 py-2 rounded-r-full hover:opacity-80"
                                    :style="{ color: disliked ? 'var(--color-accent)' : 'var(--color-text-secondary)' }"
                                >
                                    <ThumbsDown class="w-5 h-5" :fill="disliked ? 'currentColor' : 'none'" />
                                </button>
                            </div>

                            <button class="btn btn-secondary gap-2">
                                <Share2 class="w-5 h-5" />
                                <span class="hidden sm:inline">Share</span>
                            </button>

                            <button class="btn btn-secondary gap-2">
                                <Flag class="w-5 h-5" />
                                <span class="hidden sm:inline">Report</span>
                            </button>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="card p-4 mt-4">
                        <p class="text-sm mb-2" style="color: var(--color-text-muted);">
                            {{ formattedViews }} views • {{ new Date(video.published_at).toLocaleDateString() }}
                        </p>
                        <p class="whitespace-pre-wrap" style="color: var(--color-text-secondary);">{{ video.description }}</p>
                        
                        <div v-if="video.tags && video.tags.length" class="flex flex-wrap gap-2 mt-4">
                            <span
                                v-for="tag in video.tags"
                                :key="tag"
                                class="px-2 py-1 rounded text-sm"
                                style="background-color: var(--color-bg-secondary); color: var(--color-text-secondary);"
                            >
                                #{{ tag }}
                            </span>
                        </div>
                    </div>

                    <!-- Comments Section -->
                    <CommentSection :video-id="video.id" />
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:w-96">
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
</template>
