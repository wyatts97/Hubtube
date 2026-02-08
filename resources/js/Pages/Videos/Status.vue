<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useFetch } from '@/Composables/useFetch';
import { Loader2, CheckCircle, ShieldCheck, Trash2, Clock, XCircle, Edit, Eye } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    canEdit: Boolean,
});

const { get } = useFetch();

const videoStatus = ref(props.video.status);
const thumbnailUrl = ref(props.video.thumbnail_url);
let pollTimer = null;

const pollProcessingStatus = async () => {
    const { ok, data } = await get(`/videos/${props.video.id}/processing-status`);
    if (ok && data) {
        videoStatus.value = data.status;
        if (data.thumbnail_url) {
            thumbnailUrl.value = data.thumbnail_url;
        }
        if (data.status === 'processed' || data.status === 'failed') {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }
};

onMounted(() => {
    if (videoStatus.value === 'pending' || videoStatus.value === 'processing') {
        pollTimer = setInterval(pollProcessingStatus, 5000);
        pollProcessingStatus();
    }
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

const deleteVideo = () => {
    if (confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
        router.delete(`/videos/${props.video.id}`);
    }
};

const statusConfig = computed(() => {
    switch (videoStatus.value) {
        case 'pending':
            return {
                icon: Clock,
                title: 'Waiting to Process',
                description: 'Your video is queued and will begin processing shortly.',
                color: '#eab308',
                bgColor: 'rgba(234, 179, 8, 0.1)',
                animate: false,
            };
        case 'processing':
            return {
                icon: Loader2,
                title: 'Processing Your Video',
                description: 'Your video is being processed. This includes generating thumbnails, previews, and optimizing quality. This may take a few minutes.',
                color: '#3b82f6',
                bgColor: 'rgba(59, 130, 246, 0.1)',
                animate: true,
            };
        case 'processed':
            return {
                icon: ShieldCheck,
                title: 'Processing Complete — Awaiting Moderation',
                description: 'Your video has been processed successfully and is now awaiting review by a moderator. It will be published once approved.',
                color: '#22c55e',
                bgColor: 'rgba(34, 197, 94, 0.1)',
                animate: false,
            };
        case 'failed':
            return {
                icon: XCircle,
                title: 'Processing Failed',
                description: 'Something went wrong while processing your video. You can try deleting it and uploading again, or contact support.',
                color: '#ef4444',
                bgColor: 'rgba(239, 68, 68, 0.1)',
                animate: false,
            };
        default:
            return {
                icon: Clock,
                title: 'Unknown Status',
                description: 'Please check back later.',
                color: '#a3a3a3',
                bgColor: 'rgba(163, 163, 163, 0.1)',
                animate: false,
            };
    }
});

const isPublished = computed(() => {
    return videoStatus.value === 'processed' && props.video.is_approved;
});
</script>

<template>
    <Head :title="`Video Status: ${video.title}`" />

    <AppLayout>
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold mb-6" style="color: var(--color-text-primary);">Video Status</h1>

            <!-- Video Info Card -->
            <div class="card p-4 mb-6">
                <div class="flex items-start gap-4">
                    <div class="w-48 aspect-video rounded-lg overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-secondary);">
                        <img
                            v-if="thumbnailUrl"
                            :src="thumbnailUrl"
                            :alt="video.title"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center" style="color: var(--color-text-muted);">
                            <Loader2 v-if="videoStatus === 'pending' || videoStatus === 'processing'" class="w-8 h-8 animate-spin" />
                            <span v-else class="text-sm">No thumbnail</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-semibold text-lg truncate" style="color: var(--color-text-primary);">{{ video.title }}</h2>
                        <p v-if="video.description" class="text-sm mt-1 line-clamp-2" style="color: var(--color-text-muted);">{{ video.description }}</p>
                        <p class="text-sm mt-2" style="color: var(--color-text-muted);">
                            Uploaded {{ new Date(video.created_at).toLocaleDateString() }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Status Card -->
            <div class="card p-6 mb-6">
                <div class="flex items-start gap-4">
                    <div
                        class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                        :style="{ backgroundColor: statusConfig.bgColor }"
                    >
                        <component
                            :is="statusConfig.icon"
                            class="w-6 h-6"
                            :class="{ 'animate-spin': statusConfig.animate }"
                            :style="{ color: statusConfig.color }"
                        />
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg" :style="{ color: statusConfig.color }">
                            {{ statusConfig.title }}
                        </h3>
                        <p class="mt-1" style="color: var(--color-text-secondary);">
                            {{ statusConfig.description }}
                        </p>
                    </div>
                </div>

                <!-- Progress bar for pending/processing -->
                <div v-if="videoStatus === 'pending' || videoStatus === 'processing'" class="mt-4">
                    <div class="w-full rounded-full h-2 overflow-hidden" style="background-color: var(--color-bg-secondary);">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="videoStatus === 'processing' ? 'animate-pulse' : ''"
                            :style="{
                                width: videoStatus === 'processing' ? '60%' : '10%',
                                backgroundColor: statusConfig.color,
                            }"
                        ></div>
                    </div>
                    <p class="text-xs mt-2" style="color: var(--color-text-muted);">
                        This page updates automatically. You can safely leave and come back later.
                    </p>
                </div>
            </div>

            <!-- Moderation Notice -->
            <div v-if="videoStatus === 'processed' && !video.is_approved" class="card p-4 mb-6">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="w-5 h-5 flex-shrink-0" style="color: var(--color-text-secondary);" />
                    <p class="text-sm" style="color: var(--color-text-secondary);">
                        Your video will be visible to others after it has been reviewed and approved by a moderator. This usually happens within 24 hours.
                    </p>
                </div>
            </div>

            <!-- Published Notice -->
            <div v-if="isPublished" class="card p-4 mb-6" style="border: 1px solid rgba(34, 197, 94, 0.3);">
                <div class="flex items-center gap-3">
                    <CheckCircle class="w-5 h-5 flex-shrink-0 text-green-500" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-green-500">Your video is live!</p>
                        <a
                            :href="`/${video.slug}`"
                            class="text-sm mt-0.5 inline-flex items-center gap-1 hover:opacity-80"
                            style="color: var(--color-accent);"
                        >
                            <Eye class="w-3.5 h-3.5" />
                            View your video
                        </a>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <button
                    @click="deleteVideo"
                    class="btn bg-red-600 hover:bg-red-700 text-white"
                >
                    <Trash2 class="w-4 h-4 mr-2" />
                    Delete Video
                </button>

                <!-- If user gets upgraded to pro/admin later, show edit link -->
                <a
                    v-if="canEdit"
                    :href="`/videos/${video.id}/edit`"
                    class="btn btn-primary"
                >
                    <Edit class="w-4 h-4 mr-2" />
                    Edit Video
                </a>
            </div>
        </div>
    </AppLayout>
</template>
