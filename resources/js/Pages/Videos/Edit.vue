<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { X, Save, Trash2, Image } from 'lucide-vue-next';

const props = defineProps({
    video: Object,
    categories: Array,
});

const form = useForm({
    title: props.video.title,
    description: props.video.description || '',
    category_id: props.video.category_id || '',
    privacy: props.video.privacy,
    age_restricted: props.video.age_restricted,
    tags: props.video.tags || [],
    monetization_enabled: props.video.monetization_enabled,
    price: props.video.price || '',
    rent_price: props.video.rent_price || '',
    thumbnail: null,
});

const tagInput = ref('');
const thumbnailPreview = ref(props.video.thumbnail_url);

const addTag = () => {
    const tag = tagInput.value.trim().replace(/^#/, '');
    if (tag && !form.tags.includes(tag) && form.tags.length < 20) {
        form.tags.push(tag);
        tagInput.value = '';
    }
};

const removeTag = (index) => {
    form.tags.splice(index, 1);
};

const handleThumbnailSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.thumbnail = file;
        thumbnailPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(`/videos/${props.video.id}`, {
        forceFormData: true,
    });
};

const deleteVideo = () => {
    if (confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
        router.delete(`/videos/${props.video.id}`);
    }
};

const statusColors = {
    pending: 'bg-yellow-500/20 text-yellow-400',
    processing: 'bg-blue-500/20 text-blue-400',
    processed: 'bg-green-500/20 text-green-400',
    failed: 'bg-red-500/20 text-red-400',
};
</script>

<template>
    <Head :title="`Edit: ${video.title}`" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-white">Edit Video</h1>
                <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColors[video.status]]">
                    {{ video.status.charAt(0).toUpperCase() + video.status.slice(1) }}
                </span>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Video Preview -->
                <div class="card p-4">
                    <div class="flex items-start gap-4">
                        <div class="w-64 aspect-video bg-dark-800 rounded-lg overflow-hidden flex-shrink-0">
                            <img 
                                v-if="video.thumbnail_url" 
                                :src="video.thumbnail_url" 
                                :alt="video.title"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-dark-500">
                                No thumbnail
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-white text-lg">{{ video.title }}</p>
                            <p class="text-dark-400 text-sm mt-1">
                                {{ video.views_count.toLocaleString() }} views
                            </p>
                            <p class="text-dark-500 text-sm">
                                Uploaded {{ new Date(video.created_at).toLocaleDateString() }}
                            </p>
                            <div v-if="video.video_url" class="mt-3">
                                <a 
                                    :href="video.video_url" 
                                    target="_blank"
                                    class="text-primary-400 hover:text-primary-300 text-sm"
                                >
                                    View Original File
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Thumbnail -->
                <div class="card p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Custom Thumbnail</h2>
                    <div class="flex items-center gap-4">
                        <div class="w-40 aspect-video bg-dark-800 rounded-lg overflow-hidden">
                            <img 
                                v-if="thumbnailPreview" 
                                :src="thumbnailPreview" 
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <Image class="w-8 h-8 text-dark-500" />
                            </div>
                        </div>
                        <label class="btn btn-secondary cursor-pointer">
                            Upload Thumbnail
                            <input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleThumbnailSelect"
                            />
                        </label>
                    </div>
                    <p v-if="form.errors.thumbnail" class="text-red-500 text-sm mt-2">{{ form.errors.thumbnail }}</p>
                </div>

                <!-- Video Details -->
                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-white mb-4">Video Details</h2>
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-dark-300 mb-1">Title</label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="input"
                            maxlength="200"
                            required
                        />
                        <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-dark-300 mb-1">Description</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="input resize-none"
                            maxlength="5000"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-dark-300 mb-1">Category</label>
                            <select id="category" v-model="form.category_id" class="input">
                                <option value="">Select category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="privacy" class="block text-sm font-medium text-dark-300 mb-1">Privacy</label>
                            <select id="privacy" v-model="form.privacy" class="input">
                                <option value="public">Public</option>
                                <option value="unlisted">Unlisted</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-dark-300 mb-1">Tags</label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span
                                v-for="(tag, index) in form.tags"
                                :key="index"
                                class="flex items-center gap-1 px-2 py-1 bg-dark-700 rounded text-sm"
                            >
                                #{{ tag }}
                                <button type="button" @click="removeTag(index)" class="hover:text-red-400">
                                    <X class="w-3 h-3" />
                                </button>
                            </span>
                        </div>
                        <input
                            v-model="tagInput"
                            type="text"
                            class="input"
                            placeholder="Add tag and press Enter"
                            @keydown.enter.prevent="addTag"
                        />
                    </div>
                </div>

                <!-- Monetization -->
                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold text-white mb-4">Monetization</h2>
                    
                    <div class="flex items-center gap-3">
                        <input
                            id="monetization"
                            v-model="form.monetization_enabled"
                            type="checkbox"
                            class="w-4 h-4 rounded bg-dark-700 border-dark-600"
                        />
                        <label for="monetization" class="text-dark-300">Enable monetization for this video</label>
                    </div>

                    <div v-if="form.monetization_enabled" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="price" class="block text-sm font-medium text-dark-300 mb-1">Purchase Price ($)</label>
                            <input
                                id="price"
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                min="0"
                                max="1000"
                                class="input"
                                placeholder="0.00"
                            />
                        </div>

                        <div>
                            <label for="rent_price" class="block text-sm font-medium text-dark-300 mb-1">Rent Price ($)</label>
                            <input
                                id="rent_price"
                                v-model="form.rent_price"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                class="input"
                                placeholder="0.00"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button
                        type="button"
                        @click="deleteVideo"
                        class="btn bg-red-600 hover:bg-red-700 text-white"
                    >
                        <Trash2 class="w-4 h-4 mr-2" />
                        Delete Video
                    </button>
                    
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn btn-primary"
                    >
                        <Save class="w-4 h-4 mr-2" />
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
