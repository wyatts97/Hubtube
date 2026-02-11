<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { useFetch } from '@/Composables/useFetch';
import { ArrowLeft, Play, Lock, Globe, EyeOff, Heart } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    playlist: Object,
    isFavorited: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { post } = useFetch();

const favorited = ref(props.isFavorited);
const favoritesCount = ref(props.playlist.favorited_by_count || 0);
const favoriting = ref(false);

const isOwner = computed(() => user.value && user.value.id === props.playlist.user_id);

const privacyIcon = () => {
    switch (props.playlist.privacy) {
        case 'private': return Lock;
        case 'unlisted': return EyeOff;
        default: return Globe;
    }
};

const toggleFavorite = async () => {
    if (!user.value) { router.visit('/login'); return; }
    if (isOwner.value) return;
    favoriting.value = true;
    const { ok, data } = await post(`/playlists/${props.playlist.id}/favorite`);
    if (ok && data) {
        favorited.value = data.isFavorited;
        favoritesCount.value = data.favoritesCount;
    }
    favoriting.value = false;
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
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <Link href="/playlists" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                {{ t('playlist.back_to_playlists') || 'Back to Playlists' }}
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
                                {{ playlist.videos?.length || playlist.videos_count || 0 }} {{ t('common.videos') || 'videos' }}
                            </span>
                            <span v-if="playlist.user" class="text-sm" style="color: var(--color-text-muted);">
                                by <Link :href="`/channel/${playlist.user.username}`" style="color: var(--color-accent);">{{ playlist.user.username }}</Link>
                            </span>
                            <span v-if="favoritesCount > 0" class="text-sm" style="color: var(--color-text-muted);">
                                {{ favoritesCount }} {{ favoritesCount === 1 ? 'favorite' : 'favorites' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Favorite Button (only for non-owners) -->
                        <button
                            v-if="user && !isOwner"
                            @click="toggleFavorite"
                            :disabled="favoriting"
                            class="btn gap-2"
                            :class="favorited ? 'btn-primary' : 'btn-secondary'"
                        >
                            <Heart class="w-4 h-4" :fill="favorited ? 'currentColor' : 'none'" />
                            {{ favorited ? (t('playlist.favorited') || 'Favorited') : (t('playlist.favorite') || 'Favorite') }}
                        </button>
                        <button v-if="playlist.videos?.length" class="btn btn-primary gap-2">
                            <Play class="w-4 h-4" />
                            {{ t('playlist.play_all') || 'Play All' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Videos -->
            <div v-if="playlist.videos?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCard v-for="video in playlist.videos" :key="video.id" :video="video" />
            </div>

            <div v-else class="text-center py-16">
                <Play class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('playlist.empty') || 'This playlist is empty' }}</p>
                <p class="mt-1" style="color: var(--color-text-muted);">{{ t('playlist.add_videos') || 'Add videos to get started' }}</p>
            </div>
        </div>
    </AppLayout>
</template>
