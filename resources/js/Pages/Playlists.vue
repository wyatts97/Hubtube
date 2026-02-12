<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { z } from 'zod';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, Plus, X } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { useFormValidation } from '@/Composables/useFormValidation';

const { t } = useI18n();

const props = defineProps({
    playlists: Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const showCreateModal = ref(false);

const schema = z.object({
    title: z.string().min(1, 'Title is required.').max(120, 'Title must be 120 characters or less.'),
    description: z.string().max(500, 'Description must be 500 characters or less.').optional().or(z.literal('')),
});

const { defineField, errors, submit, resetForm, isSubmitting } = useFormValidation(schema, {
    title: '',
    description: '',
});

const [title, titleAttrs] = defineField('title');
const [description, descriptionAttrs] = defineField('description');

const createPlaylist = submit('post', '/playlists', {
    onSuccess: () => {
        showCreateModal.value = false;
        resetForm();
    },
});
</script>

<template>
    <Head :title="t('playlist.your_playlists') || 'Playlists'" />

    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('playlist.your_playlists') || 'Your Playlists' }}</h1>
                <p class="mt-1" style="color: var(--color-text-secondary);">{{ t('playlist.organize_desc') || 'Organize your favorite videos' }}</p>
            </div>
            <button @click="showCreateModal = true" class="btn btn-primary gap-2">
                <Plus class="w-4 h-4" />
                {{ t('playlist.new_playlist') || 'New Playlist' }}
            </button>
        </div>

        <div v-if="playlists?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="playlist in playlists.data"
                :key="playlist.id"
                :href="`/playlist/${playlist.slug}`"
                class="card overflow-hidden hover:ring-2 transition-all"
                style="--tw-ring-color: var(--color-accent);"
            >
                <div class="aspect-video flex items-center justify-center" style="background-color: var(--color-bg-secondary);">
                    <img 
                        v-if="playlist.thumbnail" 
                        :src="playlist.thumbnail" 
                        :alt="playlist.title"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                    <ListVideo v-else class="w-12 h-12" style="color: var(--color-text-muted);" />
                </div>
                <div class="p-3">
                    <h3 class="font-medium truncate" style="color: var(--color-text-primary);">{{ playlist.title }}</h3>
                    <p class="text-sm" style="color: var(--color-text-secondary);">{{ playlist.videos_count || 0 }} {{ t('common.videos') || 'videos' }}</p>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-12">
            <ListVideo class="w-16 h-16 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('playlist.no_playlists') || 'No playlists yet' }}</p>
            <p class="mt-2" style="color: var(--color-text-muted);">{{ t('playlist.no_playlists_desc') || 'Create a playlist to organize your favorite videos' }}</p>
            <button @click="showCreateModal = true" class="btn btn-primary mt-4 gap-2">
                <Plus class="w-4 h-4" />
                {{ t('playlist.create_playlist') || 'Create Playlist' }}
            </button>
        </div>

        <!-- Create Playlist Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="showCreateModal = false"></div>
            <div
                v-motion
                :initial="{ opacity: 0, y: 12 }"
                :enter="{ opacity: 1, y: 0, transition: { duration: 0.2 } }"
                :leave="{ opacity: 0, y: 12, transition: { duration: 0.15 } }"
                class="card p-6 w-full max-w-md relative z-10"
            >
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold" style="color: var(--color-text-primary);">{{ t('playlist.create_playlist') || 'Create Playlist' }}</h2>
                    <button @click="showCreateModal = false" class="p-1 rounded" style="color: var(--color-text-secondary);">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="createPlaylist" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('common.title') || 'Title' }}</label>
                        <input v-model="title" v-bind="titleAttrs" type="text" class="input" required />
                        <p v-if="errors.title" class="text-red-500 text-sm mt-1">{{ errors.title }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('common.description') || 'Description' }}</label>
                        <textarea v-model="description" v-bind="descriptionAttrs" rows="3" class="input resize-none"></textarea>
                        <p v-if="errors.description" class="text-red-500 text-sm mt-1">{{ errors.description }}</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showCreateModal = false" class="btn btn-ghost">{{ t('common.cancel') || 'Cancel' }}</button>
                        <button type="submit" :disabled="isSubmitting" class="btn btn-primary">{{ t('common.create') || 'Create' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
