<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import CommentSection from '@/Components/CommentSection.vue';
import { ThumbsUp, ThumbsDown, Share2, Flag, Bell, BellOff } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    relatedVideos: Array,
    userLike: String,
    isSubscribed: Boolean,
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
                    <video
                        controls
                        autoplay
                        class="w-full h-full"
                        :poster="video.thumbnail_url"
                    >
                        <source :src="video.video_url" type="video/mp4" />
                        Your browser does not support the video tag.
                    </video>
                </div>

                <!-- Video Info -->
                <div class="mt-4">
                    <h1 class="text-xl font-bold text-white">{{ video.title }}</h1>
                    
                    <div class="flex flex-wrap items-center justify-between gap-4 mt-4">
                        <!-- Channel Info -->
                        <div class="flex items-center gap-4">
                            <Link :href="`/channel/${video.user.username}`" class="flex items-center gap-3">
                                <div class="w-10 h-10 avatar">
                                    <img v-if="video.user.avatar" :src="video.user.avatar" :alt="video.user.username" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white font-medium">
                                        {{ video.user.username.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-white">{{ video.user.username }}</p>
                                    <p class="text-sm text-dark-400">{{ video.user.subscriber_count }} subscribers</p>
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
                            <div class="flex items-center bg-dark-800 rounded-full">
                                <button
                                    @click="handleLike"
                                    :class="[
                                        'flex items-center gap-2 px-4 py-2 rounded-l-full hover:bg-dark-700',
                                        liked ? 'text-primary-500' : 'text-dark-300'
                                    ]"
                                >
                                    <ThumbsUp class="w-5 h-5" :fill="liked ? 'currentColor' : 'none'" />
                                    <span>{{ likesCount }}</span>
                                </button>
                                <div class="w-px h-6 bg-dark-700"></div>
                                <button
                                    @click="handleDislike"
                                    :class="[
                                        'flex items-center gap-2 px-4 py-2 rounded-r-full hover:bg-dark-700',
                                        disliked ? 'text-primary-500' : 'text-dark-300'
                                    ]"
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
                        <p class="text-dark-400 text-sm mb-2">
                            {{ formattedViews }} views • {{ new Date(video.published_at).toLocaleDateString() }}
                        </p>
                        <p class="text-dark-200 whitespace-pre-wrap">{{ video.description }}</p>
                        
                        <div v-if="video.tags && video.tags.length" class="flex flex-wrap gap-2 mt-4">
                            <span
                                v-for="tag in video.tags"
                                :key="tag"
                                class="px-2 py-1 bg-dark-700 rounded text-sm text-dark-300"
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
                <h3 class="font-medium text-white mb-4">Related Videos</h3>
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
