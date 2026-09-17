<script setup>
/**
 * The viewer's own playlists.
 *
 * Creating, renaming, re-privacying and deleting all happen here; ordering the
 * videos inside a playlist happens on the playlist page itself. Watch Later is
 * marked is_default: it cannot be renamed or deleted, but its privacy is the
 * owner's to change like any other.
 */
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, Plus, MoreVertical, Pencil, Trash2, Globe, Link2, Lock } from 'lucide-vue-next';
import { DropdownMenuItem } from 'reka-ui';
import { useI18n } from '@/Composables/useI18n';
import { useFetch } from '@/Composables/useFetch';
import { useToast } from '@/Composables/useToast';
import SeoHead from '@/Components/SeoHead.vue';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import Pagination from '@/Components/Pagination.vue';

const { t } = useI18n();
const { put } = useFetch();
const toast = useToast();

const props = defineProps({
    playlists: Object,
    privacyOptions: { type: Array, default: () => ['public', 'unlisted', 'private'] },
});

const isInitialLoad = ref(true);
onMounted(() => { setTimeout(() => { isInitialLoad.value = false; }, 100); });

const privacyMeta = {
    public: { icon: Globe, label: () => t('video.public') },
    unlisted: { icon: Link2, label: () => t('video.unlisted') },
    private: { icon: Lock, label: () => t('video.private') },
};

const privacyOf = (playlist) => privacyMeta[playlist.privacy] || privacyMeta.public;

// ── Create ──────────────────────────────────────────────────────────────────

const showCreateModal = ref(false);

const form = useForm({
    title: '',
    description: '',
    privacy: 'public',
});

const createPlaylist = () => {
    form.post('/playlists', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

// ── Edit ────────────────────────────────────────────────────────────────────

const editing = ref(null);
const editForm = ref({ title: '', description: '', privacy: 'public' });
const saving = ref(false);

const startEdit = (playlist) => {
    editing.value = playlist;
    editForm.value = {
        title: playlist.title,
        description: playlist.description || '',
        privacy: playlist.privacy || 'public',
    };
};

const saveEdit = async () => {
    if (saving.value || !editing.value) return;

    saving.value = true;
    const { ok, data } = await put(`/playlists/${editing.value.id}`, editForm.value);
    saving.value = false;

    if (!ok) {
        toast.error(data?.message || t('common.error'));
        return;
    }

    editing.value = null;
    // The card list comes from the server, so re-read it rather than patching
    // a copy that would drift from the paginator.
    router.reload({ only: ['playlists'] });
};

// ── Delete ──────────────────────────────────────────────────────────────────

const pendingDelete = ref(null);

const confirmDelete = () => {
    const playlist = pendingDelete.value;
    pendingDelete.value = null;
    if (!playlist) return;

    router.delete(`/playlists/${playlist.id}`, { preserveScroll: true });
};

const goToPage = (pageNum) => {
    router.get('/playlists', { page: pageNum }, { preserveState: true, preserveScroll: true });
};

const hasPages = computed(() => (props.playlists?.last_page || 1) > 1);
</script>

<template>
    <SeoHead :title="t('playlist.your_playlists')" />

    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="page-title">{{ t('playlist.your_playlists') }}</h1>
                <p class="mt-1 text-text-secondary">{{ t('playlist.organize_desc') }}</p>
            </div>
            <button @click="showCreateModal = true" class="btn btn-primary gap-2">
                <Plus class="w-4 h-4" />
                {{ t('playlist.new_playlist') }}
            </button>
        </div>

        <!-- Skeleton Loading -->
        <div v-if="isInitialLoad" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="i in 8" :key="'skeleton-' + i" class="card overflow-hidden">
                <div class="aspect-video skeleton bg-bg-secondary"></div>
                <div class="p-3 space-y-2">
                    <div class="skeleton skeleton-text w-3/4"></div>
                    <div class="skeleton skeleton-text-sm w-1/2"></div>
                </div>
            </div>
        </div>

        <div v-else-if="playlists?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="playlist in playlists.data"
                :key="playlist.id"
                class="card overflow-hidden hover:ring-2 transition-all relative"
                style="--tw-ring-color: var(--color-accent);"
            >
                <Link :href="`/playlist/${playlist.slug}`" class="block">
                    <div class="aspect-video flex items-center justify-center bg-bg-secondary">
                        <img
                            v-if="playlist.thumbnail"
                            :src="playlist.thumbnail"
                            :alt="playlist.title"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                        <ListVideo v-else class="w-12 h-12 text-text-muted" />
                    </div>
                    <div class="p-3 pe-10">
                        <h3 class="font-medium truncate text-text-primary">{{ playlist.title }}</h3>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="text-sm text-text-secondary">{{ playlist.videos_count || 0 }} {{ t('common.videos') }}</p>
                            <span class="inline-flex items-center gap-1 text-xs text-text-muted">
                                <component :is="privacyOf(playlist).icon" class="w-3 h-3" />
                                {{ privacyOf(playlist).label() }}
                            </span>
                        </div>
                    </div>
                </Link>

                <div class="absolute end-2 bottom-2">
                    <BaseDropdown content-class="min-w-40 p-1">
                        <template #trigger>
                            <button
                                class="p-2 rounded-full hover:opacity-80 text-text-muted"
                                :aria-label="t('common.more')"
                            >
                                <MoreVertical class="w-4 h-4" />
                            </button>
                        </template>

                        <DropdownMenuItem
                            class="flex items-center gap-2 w-full px-3 py-2 text-start text-sm rounded-lg cursor-pointer outline-none text-text-secondary data-[highlighted]:bg-bg-secondary"
                            @select="startEdit(playlist)"
                        >
                            <Pencil class="w-4 h-4" />
                            {{ t('common.edit') }}
                        </DropdownMenuItem>
                        <!-- Watch Later is the one list that is always there. -->
                        <DropdownMenuItem
                            v-if="!playlist.is_default"
                            class="flex items-center gap-2 w-full px-3 py-2 text-start text-sm rounded-lg cursor-pointer outline-none text-red-400 data-[highlighted]:bg-bg-secondary"
                            @select="pendingDelete = playlist"
                        >
                            <Trash2 class="w-4 h-4" />
                            {{ t('common.delete') }}
                        </DropdownMenuItem>
                    </BaseDropdown>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-12">
            <ListVideo class="w-16 h-16 mx-auto mb-4 text-text-muted" />
            <p class="text-lg text-text-secondary">{{ t('playlist.no_playlists') }}</p>
            <p class="mt-2 text-text-muted">{{ t('playlist.no_playlists_desc') }}</p>
            <button @click="showCreateModal = true" class="btn btn-primary mt-4 gap-2">
                <Plus class="w-4 h-4" />
                {{ t('playlist.create_playlist') }}
            </button>
        </div>

        <Pagination
            v-if="hasPages"
            :current-page="playlists.current_page"
            :last-page="playlists.last_page"
            @page-change="goToPage"
        />

        <!-- Create Playlist Modal -->
        <BaseDialog v-model="showCreateModal" :title="t('playlist.create_playlist')">
            <form @submit.prevent="createPlaylist" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('common.title') }}</label>
                    <input v-model="form.title" type="text" class="input" required />
                    <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('common.description') }}</label>
                    <textarea v-model="form.description" rows="3" class="input resize-none"></textarea>
                    <p v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('video.privacy') }}</label>
                    <select v-model="form.privacy" class="input">
                        <option v-for="option in privacyOptions" :key="option" :value="option">
                            {{ privacyMeta[option].label() }}
                        </option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="btn btn-ghost">{{ t('common.cancel') }}</button>
                    <button type="submit" :disabled="form.processing" class="btn btn-primary">{{ t('common.create') }}</button>
                </div>
            </form>
        </BaseDialog>

        <!-- Edit Playlist Modal -->
        <BaseDialog
            :model-value="editing !== null"
            :title="t('playlist.edit_playlist')"
            @update:model-value="editing = null"
        >
            <form v-if="editing" @submit.prevent="saveEdit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('common.title') }}</label>
                    <input
                        v-model="editForm.title"
                        type="text"
                        class="input"
                        :disabled="editing.is_default"
                        required
                    />
                    <p v-if="editing.is_default" class="text-xs mt-1 text-text-muted">{{ t('playlist.default_fixed_name') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('common.description') }}</label>
                    <textarea v-model="editForm.description" rows="3" class="input resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('video.privacy') }}</label>
                    <select v-model="editForm.privacy" class="input">
                        <option v-for="option in privacyOptions" :key="option" :value="option">
                            {{ privacyMeta[option].label() }}
                        </option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="editing = null" class="btn btn-ghost">{{ t('common.cancel') }}</button>
                    <button type="submit" :disabled="saving" class="btn btn-primary">{{ t('common.save') }}</button>
                </div>
            </form>
        </BaseDialog>

        <!-- Delete confirmation -->
        <BaseDialog
            :model-value="pendingDelete !== null"
            variant="alert"
            :title="t('playlist.delete_title')"
            @update:model-value="pendingDelete = null"
        >
            <p class="text-sm text-text-secondary">{{ t('playlist.delete_body') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button class="btn btn-secondary" @click="pendingDelete = null">{{ t('common.cancel') }}</button>
                <button class="btn btn-primary" @click="confirmDelete">{{ t('common.delete') }}</button>
            </div>
        </BaseDialog>
    </AppLayout>
</template>
