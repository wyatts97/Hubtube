<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { useFetch } from '@/Composables/useFetch';
import { ArrowLeft, Play, Heart } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t, localizedUrl } = useI18n();

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

const getPlaylistVideoHref = (video, index) => {
    const baseUrl = localizedUrl(`/${video.slug}`);
    const params = new URLSearchParams({
        playlist: props.playlist.slug,
        index: String(index),
    });
    return `${baseUrl}?${params.toString()}`;
};

const firstVideoHref = computed(() => {
    const firstVideo = props.playlist.videos?.[0];
    if (!firstVideo) return '#';
    return getPlaylistVideoHref(firstVideo, 0);
});

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
            <Link href="/playlists" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80 text-text-secondary">
                <ArrowLeft class="w-4 h-4" />
                {{ t('playlist.back_to_playlists') || 'Back to Playlists' }}
            </Link>

            <!-- Playlist Header -->
            <div class="card p-6 mb-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-text-primary">{{ playlist.title }}</h1>
                        <p v-if="playlist.description" class="mt-2 text-text-secondary">{{ playlist.description }}</p>
                        <div class="flex items-center gap-4 mt-3">
                            <span class="text-sm text-text-muted">
                                {{ playlist.videos?.length || playlist.videos_count || 0 }} {{ t('common.videos') || 'videos' }}
                            </span>
                            <span v-if="playlist.user" class="text-sm text-text-muted">
                                by <Link :href="`/channel/${playlist.user.username}`" class="text-accent">{{ playlist.user.username }}</Link>
                            </span>
                            <span v-if="favoritesCount > 0" class="text-sm text-text-muted">
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
                        <Link v-if="playlist.videos?.length" :href="firstVideoHref" class="btn btn-primary gap-2">
                            <Play class="w-4 h-4" />
                            {{ t('playlist.play_all') || 'Play All' }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Videos -->
            <div v-if="playlist.videos?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <VideoCard
                    v-for="(video, idx) in playlist.videos"
                    :key="video.id"
                    :video="video"
                    :href="getPlaylistVideoHref(video, idx)"
                />
            </div>

            <div v-else class="text-center py-16">
                <Play class="w-12 h-12 mx-auto mb-4 text-text-muted" />
                <p class="text-lg text-text-secondary">{{ t('playlist.empty') || 'This playlist is empty' }}</p>
                <p class="mt-1 text-text-muted">{{ t('playlist.add_videos') || 'Add videos to get started' }}</p>
            </div>
        </div>
    </AppLayout>
</template>
