<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import VideoCardSkeleton from '@/Components/VideoCardSkeleton.vue';
import { History, Trash2 } from 'lucide-vue-next';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

defineProps({
    videos: Object,
});

const isInitialLoad = ref(true);
onMounted(() => { setTimeout(() => { isInitialLoad.value = false; }, 100); });

const { del } = useFetch();

const clearHistory = async () => {
    if (!confirm(t('history.clear_confirm') || 'Are you sure you want to clear your watch history?')) return;
    
    const { ok } = await del('/history', null);
    if (ok) {
        router.reload();
    }
};
</script>

<template>
    <Head :title="t('history.title') || 'Watch History'" />

    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('history.title') || 'Watch History' }}</h1>
                <p class="mt-1" style="color: var(--color-text-secondary);">{{ t('history.description') || 'Videos you\'ve watched recently' }}</p>
            </div>
            <button v-if="videos?.data?.length" @click="clearHistory" class="btn btn-ghost text-red-400 gap-2">
                <Trash2 class="w-4 h-4" />
                {{ t('history.clear') || 'Clear History' }}
            </button>
        </div>

        <!-- Skeleton Loading -->
        <div v-if="isInitialLoad" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCardSkeleton v-for="i in 8" :key="'skeleton-' + i" />
        </div>

        <div v-else-if="videos?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard v-for="video in videos.data" :key="video.id" :video="video" />
        </div>

        <div v-else class="text-center py-12">
            <History class="w-16 h-16 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('history.empty') || 'No watch history yet' }}</p>
            <p class="mt-2" style="color: var(--color-text-muted);">{{ t('history.empty_desc') || 'Videos you watch will appear here' }}</p>
            <Link href="/" class="btn btn-primary mt-4">
                {{ t('common.browse_videos') || 'Browse Videos' }}
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="videos?.links?.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <a
                    v-if="link.url"
                    :href="link.url"
                    class="px-4 py-2 rounded-lg text-sm"
                    :style="link.active 
                        ? { backgroundColor: 'var(--color-accent)', color: 'white' } 
                        : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
