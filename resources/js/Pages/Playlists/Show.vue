<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { ArrowLeft, Play, Lock, Globe, EyeOff, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    playlist: Object,
});

const privacyIcon = () => {
    switch (props.playlist.privacy) {
        case 'private': return Lock;
        case 'unlisted': return EyeOff;
        default: return Globe;
    }
};

const removeVideo = (videoId) => {
    if (!confirm('Remove this video from the playlist?')) return;
    router.delete(`/playlists/${props.playlist.id}/videos`, {
        data: { video_id: videoId },
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="playlist.title" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <Link href="/playlists" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                Back to Playlists
            </Link>

            <!-- Playlist Header -->
            <div class="card p-6 mb-6">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <component :is="privacyIcon()" class="w-4 h-4" style="color: var(--color-text-muted);" />
                            <span class="text-xs uppercase tracking-wide" style="color: var(--color-text-muted);">{{ playlist.privacy }}</span>
                        </div>
                        <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ playlist.title }}</h1>
                        <p v-if="playlist.description" class="mt-2" style="color: var(--color-text-secondary);">{{ playlist.description }}</p>
                        <div class="flex items-center gap-4 mt-3">
                            <span class="text-sm" style="color: var(--color-text-muted);">
                                {{ playlist.videos?.length || playlist.video_count || 0 }} videos
                            </span>
                            <span v-if="playlist.user" class="text-sm" style="color: var(--color-text-muted);">
                                by <Link :href="`/channel/${playlist.user.username}`" style="color: var(--color-accent);">{{ playlist.user.username }}</Link>
                            </span>
                        </div>
                    </div>
                    <button v-if="playlist.videos?.length" class="btn btn-primary gap-2">
                        <Play class="w-4 h-4" />
                        Play All
                    </button>
                </div>
            </div>

            <!-- Videos -->
            <div v-if="playlist.videos?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCard v-for="video in playlist.videos" :key="video.id" :video="video" />
            </div>

            <div v-else class="text-center py-16">
                <Play class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                <p class="text-lg" style="color: var(--color-text-secondary);">This playlist is empty</p>
                <p class="mt-1" style="color: var(--color-text-muted);">Add videos to get started</p>
            </div>
        </div>
    </AppLayout>
</template>
