<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Upload, X, Video } from 'lucide-vue-next';

const props = defineProps({
    categories: Array,
});

const dragActive = ref(false);
const videoPreview = ref(null);

const form = useForm({
    title: '',
    description: '',
    category_id: '',
    privacy: 'public',
    age_restricted: true,
    tags: [],
    video_file: null,
});

const tagInput = ref('');

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

const handleDrop = (e) => {
    dragActive.value = false;
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('video/')) {
        handleFile(file);
    }
};

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        handleFile(file);
    }
};

const handleFile = (file) => {
    form.video_file = file;
    videoPreview.value = URL.createObjectURL(file);
    
    if (!form.title) {
        form.title = file.name.replace(/\.[^/.]+$/, '');
    }
};

const removeVideo = () => {
    form.video_file = null;
    videoPreview.value = null;
};

const submit = () => {
    form.post('/upload', {
        forceFormData: true,
    });
};

const fileSizeFormatted = computed(() => {
    if (!form.video_file) return '';
    const bytes = form.video_file.size;
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }
    if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    }
    return (bytes / 1024).toFixed(2) + ' KB';
});
</script>

<template>
    <Head title="Upload Video" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <h1 class="text-2xl font-bold mb-6" style="color: var(--color-text-primary);">Upload Video</h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Video Upload Area -->
                <div
                    v-if="!form.video_file"
                    @dragover.prevent="dragActive = true"
                    @dragleave.prevent="dragActive = false"
                    @drop.prevent="handleDrop"
                    class="card border-2 border-dashed p-12 text-center transition-colors"
                    :style="{ borderColor: dragActive ? 'var(--color-accent)' : 'var(--color-border)' }"
                >
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: var(--color-bg-secondary);">
                        <Upload class="w-8 h-8" style="color: var(--color-text-muted);" />
                    </div>
                    <p class="text-lg font-medium mb-2" style="color: var(--color-text-primary);">Drag and drop video file</p>
                    <p class="mb-4" style="color: var(--color-text-muted);">or click to browse</p>
                    <label class="btn btn-primary cursor-pointer">
                        Select File
                        <input
                            type="file"
                            accept="video/*"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                    </label>
                    <p class="text-sm mt-4" style="color: var(--color-text-muted);">
                        Supported formats: MP4, MOV, AVI, MKV, WebM
                    </p>
                </div>

                <!-- Video Preview -->
                <div v-else class="card p-4">
                    <div class="flex items-start gap-4">
                        <div class="w-48 aspect-video rounded-lg overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-secondary);">
                            <video :src="videoPreview" class="w-full h-full object-cover"></video>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium" style="color: var(--color-text-primary);">{{ form.video_file.name }}</p>
                            <p class="text-sm" style="color: var(--color-text-muted);">{{ fileSizeFormatted }}</p>
                        </div>
                        <button type="button" @click="removeVideo" class="p-2 rounded-full hover:opacity-80" style="background-color: var(--color-bg-secondary);">
                            <X class="w-5 h-5" style="color: var(--color-text-muted);" />
                        </button>
                    </div>
                    <p v-if="form.errors.video_file" class="text-red-500 text-sm mt-2">{{ form.errors.video_file }}</p>
                </div>

                <!-- Video Details -->
                <div class="card p-6 space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Title</label>
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
                        <label for="description" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Description</label>
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
                            <label for="category" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Category</label>
                            <select id="category" v-model="form.category_id" class="input">
                                <option value="">Select category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="privacy" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Privacy</label>
                            <select id="privacy" v-model="form.privacy" class="input">
                                <option value="public">Public</option>
                                <option value="unlisted">Unlisted</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Tags</label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span
                                v-for="(tag, index) in form.tags"
                                :key="index"
                                class="flex items-center gap-1 px-2 py-1 rounded text-sm"
                                style="background-color: var(--color-bg-secondary); color: var(--color-text-primary);"
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

                <div class="flex justify-end gap-4">
                    <button type="button" class="btn btn-secondary">Save as Draft</button>
                    <button
                        type="submit"
                        :disabled="form.processing || !form.video_file"
                        class="btn btn-primary"
                    >
                        <span v-if="form.processing">Uploading...</span>
                        <span v-else>Upload Video</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
