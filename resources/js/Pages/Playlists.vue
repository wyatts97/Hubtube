<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, Plus, X } from 'lucide-vue-next';

const props = defineProps({
    playlists: Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const showCreateModal = ref(false);
const form = useForm({
    title: '',
    description: '',
    is_public: true,
});

const createPlaylist = () => {
    form.post('/playlists', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Playlists" />

    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Your Playlists</h1>
                <p class="text-dark-400 mt-1">Organize your favorite videos</p>
            </div>
            <button @click="showCreateModal = true" class="btn btn-primary gap-2">
                <Plus class="w-4 h-4" />
                New Playlist
            </button>
        </div>

        <div v-if="playlists?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="playlist in playlists.data"
                :key="playlist.id"
                :href="`/playlist/${playlist.slug}`"
                class="card overflow-hidden hover:ring-2 hover:ring-primary-500 transition-all"
            >
                <div class="aspect-video bg-dark-800 flex items-center justify-center">
                    <img 
                        v-if="playlist.thumbnail" 
                        :src="playlist.thumbnail" 
                        :alt="playlist.title"
                        class="w-full h-full object-cover"
                    />
                    <ListVideo v-else class="w-12 h-12 text-dark-600" />
                </div>
                <div class="p-3">
                    <h3 class="font-medium text-white truncate">{{ playlist.title }}</h3>
                    <p class="text-sm text-dark-400">{{ playlist.videos_count || 0 }} videos</p>
                    <p class="text-xs text-dark-500 mt-1">
                        {{ playlist.is_public ? 'Public' : 'Private' }}
                    </p>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-12">
            <ListVideo class="w-16 h-16 text-dark-600 mx-auto mb-4" />
            <p class="text-dark-400 text-lg">No playlists yet</p>
            <p class="text-dark-500 mt-2">Create a playlist to organize your favorite videos</p>
            <button @click="showCreateModal = true" class="btn btn-primary mt-4 gap-2">
                <Plus class="w-4 h-4" />
                Create Playlist
            </button>
        </div>

        <!-- Create Playlist Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="showCreateModal = false"></div>
            <div class="card p-6 w-full max-w-md relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-white">Create Playlist</h2>
                    <button @click="showCreateModal = false" class="p-1 hover:bg-dark-800 rounded">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <form @submit.prevent="createPlaylist" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-dark-300 mb-1">Title</label>
                        <input v-model="form.title" type="text" class="input" required />
                        <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-dark-300 mb-1">Description</label>
                        <textarea v-model="form.description" rows="3" class="input resize-none"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="form.is_public" type="checkbox" id="is_public" class="w-4 h-4 rounded" />
                        <label for="is_public" class="text-dark-300">Make playlist public</label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showCreateModal = false" class="btn btn-ghost">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
