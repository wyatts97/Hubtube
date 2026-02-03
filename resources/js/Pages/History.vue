<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { History, Trash2 } from 'lucide-vue-next';

defineProps({
    videos: Object,
});

const clearHistory = async () => {
    if (!confirm('Are you sure you want to clear your watch history?')) return;
    
    await fetch('/history', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
    });
    
    window.location.reload();
};
</script>

<template>
    <Head title="Watch History" />

    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Watch History</h1>
                <p class="text-dark-400 mt-1">Videos you've watched recently</p>
            </div>
            <button v-if="videos?.data?.length" @click="clearHistory" class="btn btn-ghost text-red-400 gap-2">
                <Trash2 class="w-4 h-4" />
                Clear History
            </button>
        </div>

        <div v-if="videos?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <History class="w-16 h-16 text-dark-600 mx-auto mb-4" />
            <p class="text-dark-400 text-lg">No watch history yet</p>
            <p class="text-dark-500 mt-2">Videos you watch will appear here</p>
            <Link href="/" class="btn btn-primary mt-4">
                Browse Videos
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="videos?.links?.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <a
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm',
                        link.active 
                            ? 'bg-primary-600 text-white' 
                            : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                    ]"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
