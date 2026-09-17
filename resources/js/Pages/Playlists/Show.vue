<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SeoHead from '@/Components/SeoHead.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import { useFetch } from '@/Composables/useFetch';
import { useToast } from '@/Composables/useToast';
import {
    ArrowLeft, Play, Heart, GripVertical, ArrowUp, ArrowDown,
    Trash2, ListOrdered, Globe, Link2, Lock, Check,
} from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import GridAdSlot from '@/Components/GridAdSlot.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import { useGridAds } from '@/Composables/useGridAds';

const { t, localizedUrl } = useI18n();

const props = defineProps({
    playlist: Object,
    isFavorited: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
    adSettings: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

const { gridAds, shouldShowAd, getSponsoredCard, adCellClass } = useGridAds(props);

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { post, put, del } = useFetch();
const toast = useToast();

const favorited = ref(props.isFavorited);
const favoritesCount = ref(props.playlist.favorited_by_count || 0);
const favoriting = ref(false);

const isOwner = computed(() => user.value && user.value.id === props.playlist.user_id);

const privacyMeta = {
    public: { icon: Globe, label: () => t('video.public') },
    unlisted: { icon: Link2, label: () => t('video.unlisted') },
    private: { icon: Lock, label: () => t('video.private') },
};

const privacy = computed(() => privacyMeta[props.playlist.privacy] || privacyMeta.public);

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

/*
|--------------------------------------------------------------------------
| Reordering
|--------------------------------------------------------------------------
|
| Turning on reorder mode swaps the grid for a compact list: dragging a
| thumbnail around a four-column grid is fiddly, and the list gives each row
| up/down buttons, which is also the only way to reorder from a keyboard.
| Nothing is saved until "Save order" — the server takes the whole ordering in
| one request.
|
*/

const reordering = ref(false);
const items = ref([...(props.playlist.videos || [])]);
const dirty = ref(false);
const savingOrder = ref(false);
const dragIndex = ref(null);

const startReorder = () => {
    items.value = [...(props.playlist.videos || [])];
    dirty.value = false;
    reordering.value = true;
};

const cancelReorder = () => {
    reordering.value = false;
    dirty.value = false;
    items.value = [...(props.playlist.videos || [])];
};

const move = (from, to) => {
    if (to < 0 || to >= items.value.length || from === to) return;
    const next = [...items.value];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);
    items.value = next;
    dirty.value = true;
};

const onDragStart = (index) => { dragIndex.value = index; };

const onDragOver = (index) => {
    if (dragIndex.value === null || dragIndex.value === index) return;
    move(dragIndex.value, index);
    dragIndex.value = index;
};

const onDragEnd = () => { dragIndex.value = null; };

const saveOrder = async () => {
    if (savingOrder.value) return;

    savingOrder.value = true;
    const { ok, data } = await put(`/playlists/${props.playlist.id}/order`, {
        video_ids: items.value.map((video) => video.id),
    });
    savingOrder.value = false;

    if (!ok) {
        toast.error(data?.error || t('common.error'));
        return;
    }

    toast.success(t('playlist.order_saved'));
    dirty.value = false;
    reordering.value = false;
    router.reload({ only: ['playlist'] });
};

/*
|--------------------------------------------------------------------------
| Removing a video
|--------------------------------------------------------------------------
*/

const pendingRemove = ref(null);

const confirmRemove = async () => {
    const video = pendingRemove.value;
    pendingRemove.value = null;
    if (!video) return;

    // The endpoint answers with JSON (the watch page's save menu calls the same
    // one), so remove it here and then re-read the playlist prop rather than
    // treating the response as an Inertia visit.
    const { ok, data } = await del(`/playlists/${props.playlist.id}/videos`, { video_id: video.id });

    if (!ok) {
        toast.error(data?.error || t('common.error'));
        return;
    }

    items.value = items.value.filter((item) => item.id !== video.id);
    toast.success(t('playlist.removed'));
    router.reload({ only: ['playlist'] });
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <Link href="/playlists" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80 text-text-secondary">
                <ArrowLeft class="w-4 h-4" />
                {{ t('playlist.back_to_playlists') }}
            </Link>

            <!-- Playlist Header -->
            <div class="card p-6 mb-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <h1 class="page-title">{{ playlist.title }}</h1>
                        <p v-if="playlist.description" class="mt-2 text-text-secondary">{{ playlist.description }}</p>
                        <div class="flex items-center gap-4 mt-3 flex-wrap">
                            <span class="text-sm text-text-muted">
                                {{ playlist.videos?.length || playlist.videos_count || 0 }} {{ t('common.videos') }}
                            </span>
                            <!-- by_user carries a {name} placeholder, so it cannot
                                 be used as a bare label next to a linked
                                 username — the link supplies the name. -->
                            <span v-if="playlist.user" class="text-sm text-text-muted">
                                {{ t('playlist.by') }}
                                <Link :href="`/channel/${playlist.user.username}`" class="text-accent-text">{{ playlist.user.username }}</Link>
                            </span>
                            <span v-if="favoritesCount > 0" class="text-sm text-text-muted">
                                {{ t('playlist.favorites_count', { count: favoritesCount, n: favoritesCount }) }}
                            </span>
                            <span v-if="isOwner" class="inline-flex items-center gap-1 text-sm text-text-muted">
                                <component :is="privacy.icon" class="w-3.5 h-3.5" />
                                {{ privacy.label() }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Favorite Button (only for non-owners) -->
                        <button
                            v-if="user && !isOwner"
                            @click="toggleFavorite"
                            :disabled="favoriting"
                            class="btn gap-2"
                            :class="favorited ? 'btn-primary' : 'btn-secondary'"
                        >
                            <Heart class="w-4 h-4" :fill="favorited ? 'currentColor' : 'none'" />
                            {{ favorited ? (t('playlist.favorited')) : (t('playlist.favorite')) }}
                        </button>
                        <button
                            v-if="canEdit && playlist.videos?.length > 1 && !reordering"
                            @click="startReorder"
                            class="btn btn-secondary gap-2"
                        >
                            <ListOrdered class="w-4 h-4" />
                            {{ t('playlist.reorder') }}
                        </button>
                        <template v-if="reordering">
                            <button @click="cancelReorder" class="btn btn-ghost">{{ t('common.cancel') }}</button>
                            <button
                                @click="saveOrder"
                                class="btn btn-primary gap-2"
                                :disabled="!dirty || savingOrder"
                            >
                                <Check class="w-4 h-4" />
                                {{ savingOrder ? t('common.loading') : t('playlist.save_order') }}
                            </button>
                        </template>
                        <Link v-if="playlist.videos?.length && !reordering" :href="firstVideoHref" class="btn btn-primary gap-2">
                            <Play class="w-4 h-4" />
                            {{ t('playlist.play_all') }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Reorder mode: a compact, keyboard-operable list -->
            <ul v-if="reordering" class="space-y-2">
                <li
                    v-for="(video, idx) in items"
                    :key="video.id"
                    class="card flex items-center gap-3 p-2"
                    draggable="true"
                    @dragstart="onDragStart(idx)"
                    @dragover.prevent="onDragOver(idx)"
                    @dragend="onDragEnd"
                >
                    <GripVertical class="w-4 h-4 shrink-0 cursor-grab text-text-muted" aria-hidden="true" />
                    <span class="w-6 text-center text-sm text-text-muted">{{ idx + 1 }}</span>
                    <div class="w-24 h-14 rounded-lg overflow-hidden shrink-0 bg-bg-secondary">
                        <img v-if="video.thumbnail_url" :src="video.thumbnail_url" :alt="video.title" class="w-full h-full object-cover" loading="lazy" />
                    </div>
                    <span class="flex-1 min-w-0 text-sm font-medium truncate text-text-primary">{{ video.title }}</span>
                    <button
                        class="p-2 rounded-lg hover:opacity-80 text-text-muted disabled:opacity-40"
                        :disabled="idx === 0"
                        :aria-label="t('playlist.move_up')"
                        @click="move(idx, idx - 1)"
                    >
                        <ArrowUp class="w-4 h-4" />
                    </button>
                    <button
                        class="p-2 rounded-lg hover:opacity-80 text-text-muted disabled:opacity-40"
                        :disabled="idx === items.length - 1"
                        :aria-label="t('playlist.move_down')"
                        @click="move(idx, idx + 1)"
                    >
                        <ArrowDown class="w-4 h-4" />
                    </button>
                    <button
                        class="p-2 rounded-lg text-red-400 hover:text-red-300"
                        :aria-label="t('playlist.remove_video')"
                        @click="pendingRemove = video"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </li>
            </ul>

            <!-- Videos -->
            <div v-else-if="playlist.videos?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template v-for="(video, idx) in playlist.videos" :key="video.id">
                    <div class="relative group">
                        <VideoCard :video="video" :href="getPlaylistVideoHref(video, idx)" />
                        <button
                            v-if="canEdit"
                            class="absolute top-2 end-2 p-1.5 rounded-full opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity bg-black/70 text-white"
                            :aria-label="t('playlist.remove_video')"
                            @click.prevent="pendingRemove = video"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                    <div v-if="shouldShowAd(idx, playlist.videos.length)" class="rounded-xl p-2" :class="adCellClass">
                        <GridAdSlot :ads="gridAds" />
                    </div>
                    <SponsoredVideoCard v-if="getSponsoredCard(idx)" :card="getSponsoredCard(idx)" />
                </template>
            </div>

            <div v-else class="text-center py-16">
                <Play class="w-12 h-12 mx-auto mb-4 text-text-muted" />
                <p class="text-lg text-text-secondary">{{ t('playlist.empty') }}</p>
                <p class="mt-1 text-text-muted">{{ t('playlist.add_videos') }}</p>
            </div>
        </div>

        <BaseDialog
            :model-value="pendingRemove !== null"
            variant="alert"
            :title="t('playlist.remove_video')"
            @update:model-value="pendingRemove = null"
        >
            <p class="text-sm text-text-secondary">{{ t('playlist.remove_video_body') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button class="btn btn-secondary" @click="pendingRemove = null">{{ t('common.cancel') }}</button>
                <button class="btn btn-primary" @click="confirmRemove">{{ t('common.remove') }}</button>
            </div>
        </BaseDialog>
    </AppLayout>
</template>
