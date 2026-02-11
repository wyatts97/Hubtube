<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useFetch } from '@/Composables/useFetch';
import { X, Save, Trash2, Image, Loader2, CheckCircle, ShieldCheck } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    video: Object,
    categories: Array,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const requiresModeration = computed(() => !user.value?.is_admin);

const { get, post } = useFetch();

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
const customThumbnailPreview = ref(null);
const videoStatus = ref(props.video.status);
const generatedThumbnails = ref([]);
const selectedThumbIndex = ref(null);
const selectingThumb = ref(false);
let pollTimer = null;

const pollProcessingStatus = async () => {
    const { ok, data } = await get(`/videos/${props.video.id}/processing-status`);
    if (ok && data) {
        videoStatus.value = data.status;
        if (data.thumbnail_url && !thumbnailPreview.value) {
            thumbnailPreview.value = data.thumbnail_url;
        }
        if (data.thumbnails?.length) {
            generatedThumbnails.value = data.thumbnails;
        }
        // Stop polling once processed or failed
        if (data.status === 'processed' || data.status === 'failed') {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }
};

const selectThumbnail = async (index) => {
    selectingThumb.value = true;
    const { ok, data } = await post(`/videos/${props.video.id}/select-thumbnail`, { index });
    if (ok && data) {
        thumbnailPreview.value = data.thumbnail_url;
        selectedThumbIndex.value = index;
        form.thumbnail = null; // Clear custom upload since we selected a generated one
    }
    selectingThumb.value = false;
};

onMounted(() => {
    // Start polling if video is still processing
    if (videoStatus.value === 'pending' || videoStatus.value === 'processing') {
        pollTimer = setInterval(pollProcessingStatus, 5000);
        // Also poll immediately
        pollProcessingStatus();
    } else {
        // Already processed — fetch thumbnails once
        pollProcessingStatus();
    }
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

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
        customThumbnailPreview.value = URL.createObjectURL(file);
        thumbnailPreview.value = customThumbnailPreview.value;
        selectedThumbIndex.value = null; // Clear generated selection
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
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('video.edit_video') || 'Edit Video' }}</h1>
                <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColors[video.status]]">
                    {{ video.status.charAt(0).toUpperCase() + video.status.slice(1) }}
                </span>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Processing Status Banner -->
                <div v-if="videoStatus === 'pending' || videoStatus === 'processing'" class="card p-4">
                    <div class="flex items-center gap-3">
                        <Loader2 class="w-5 h-5 animate-spin" style="color: var(--color-accent);" />
                        <div class="flex-1">
                            <p class="font-medium" style="color: var(--color-text-primary);">
                                {{ videoStatus === 'pending' ? 'Waiting to process...' : 'Processing video...' }}
                            </p>
                            <p class="text-sm mt-0.5" style="color: var(--color-text-muted);">
                                Your video is being processed. Thumbnails will appear below when ready.
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 w-full rounded-full h-2 overflow-hidden" style="background-color: var(--color-bg-secondary);">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="videoStatus === 'processing' ? 'animate-pulse' : ''"
                            :style="{
                                width: videoStatus === 'processing' ? '60%' : '10%',
                                backgroundColor: 'var(--color-accent)',
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Moderation Notice -->
                <div v-if="requiresModeration && (videoStatus === 'pending' || videoStatus === 'processing')" class="card p-4">
                    <div class="flex items-center gap-3">
                        <ShieldCheck class="w-5 h-5 shrink-0" style="color: var(--color-text-secondary);" />
                        <p class="text-sm" style="color: var(--color-text-secondary);">
                            Your video will be posted after moderation and approval.
                        </p>
                    </div>
                </div>

                <!-- Video Preview -->
                <div class="card p-4">
                    <div class="flex flex-col sm:flex-row items-start gap-4">
                        <div class="w-full sm:w-64 aspect-video rounded-lg overflow-hidden shrink-0" style="background-color: var(--color-bg-secondary);">
                            <img 
                                v-if="thumbnailPreview" 
                                :src="thumbnailPreview" 
                                :alt="video.title"
                                class="w-full h-full object-cover"
                            />
                            <video
                                v-else-if="video.video_url"
                                :src="video.video_url"
                                class="w-full h-full object-cover"
                                preload="metadata"
                                muted
                            ></video>
                            <div v-else class="w-full h-full flex items-center justify-center" style="color: var(--color-text-muted);">
                                No thumbnail
                            </div>
                        </div>
                        <div class="flex-1 w-full">
                            <p class="font-medium text-lg" style="color: var(--color-text-primary);">{{ video.title }}</p>
                            <p class="text-sm mt-1" style="color: var(--color-text-muted);">
                                {{ video.views_count.toLocaleString() }} views
                            </p>
                            <p class="text-sm" style="color: var(--color-text-muted);">
                                Uploaded {{ new Date(video.created_at).toLocaleDateString() }}
                            </p>
                            <span :class="['mt-2 inline-block px-3 py-1 rounded-full text-xs font-medium', statusColors[videoStatus]]">
                                {{ videoStatus.charAt(0).toUpperCase() + videoStatus.slice(1) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Thumbnail Selection -->
                <div class="card p-6">
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">{{ t('video.thumbnail') || 'Thumbnail' }}</h2>
                    
                    <!-- Generated Thumbnails -->
                    <div v-if="generatedThumbnails.length" class="mb-6">
                        <p class="text-sm mb-2" style="color: var(--color-text-secondary);">Choose from generated thumbnails:</p>
                        <div class="grid grid-cols-2 gap-3" style="max-width: 32rem;">
                            <button
                                v-for="(thumb, index) in generatedThumbnails.slice(0, 4)"
                                :key="index"
                                type="button"
                                @click="selectThumbnail(index)"
                                :disabled="selectingThumb"
                                class="relative aspect-video rounded-lg overflow-hidden border-2 transition-all hover:opacity-90"
                                :style="{
                                    borderColor: selectedThumbIndex === index ? 'var(--color-accent)' : 'var(--color-border)',
                                }"
                            >
                                <img :src="thumb" class="w-full h-full object-cover" />
                                <div
                                    v-if="selectedThumbIndex === index"
                                    class="absolute inset-0 flex items-center justify-center"
                                    style="background-color: rgba(0,0,0,0.4);"
                                >
                                    <CheckCircle class="w-6 h-6 text-white" />
                                </div>
                            </button>
                        </div>
                    </div>
                    <div v-else-if="videoStatus === 'pending' || videoStatus === 'processing'" class="mb-6">
                        <p class="text-sm" style="color: var(--color-text-muted);">
                            Thumbnails will be generated once processing completes...
                        </p>
                    </div>

                    <!-- Custom Upload -->
                    <div class="pt-4" style="border-top: 1px solid var(--color-border);">
                        <p class="text-sm mb-3" style="color: var(--color-text-secondary);">Or upload your own image:</p>
                        <label class="flex items-center gap-4 cursor-pointer group">
                            <div class="w-32 aspect-video rounded-lg overflow-hidden shrink-0 group-hover:opacity-80 transition-opacity" style="background-color: var(--color-bg-secondary);">
                                <img 
                                    v-if="customThumbnailPreview" 
                                    :src="customThumbnailPreview" 
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <Image class="w-6 h-6" style="color: var(--color-text-muted);" />
                                </div>
                            </div>
                            <div>
                                <span class="btn btn-secondary text-sm">{{ t('video.upload_thumbnail') || 'Upload Custom Thumbnail' }}</span>
                                <p class="text-xs mt-1" style="color: var(--color-text-muted);">JPG, PNG or WebP, max 5MB</p>
                            </div>
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
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">{{ t('video.video_details') || 'Video Details' }}</h2>
                    
                    <div>
                        <label for="title" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('upload.video_title') || 'Title' }}</label>
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
                        <label for="description" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('upload.video_description') || 'Description' }}</label>
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
                            <label for="category" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('video.category') || 'Category' }}</label>
                            <select id="category" v-model="form.category_id" class="input">
                                <option value="">Select category</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="privacy" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('video.privacy') || 'Privacy' }}</label>
                            <select id="privacy" v-model="form.privacy" class="input">
                                <option value="public">Public</option>
                                <option value="unlisted">Unlisted</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('video.tags') || 'Tags' }}</label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span
                                v-for="(tag, index) in form.tags"
                                :key="index"
                                class="flex items-center gap-1 px-2 py-1 rounded text-sm" style="background-color: var(--color-bg-secondary); color: var(--color-text-secondary);"
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
                            :placeholder="t('upload.add_tag') || 'Add tag and press Enter'"
                            @keydown.enter.prevent="addTag"
                        />
                    </div>
                </div>

                <!-- Monetization -->
                <div class="card p-6 space-y-4">
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">{{ t('video.monetization') || 'Monetization' }}</h2>
                    
                    <div class="flex items-center gap-3">
                        <input
                            id="monetization"
                            v-model="form.monetization_enabled"
                            type="checkbox"
                            class="w-4 h-4 rounded bg-dark-700 border-dark-600"
                        />
                        <label for="monetization" style="color: var(--color-text-secondary);">Enable monetization for this video</label>
                    </div>

                    <div v-if="form.monetization_enabled" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="price" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Purchase Price ($)</label>
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
                            <label for="rent_price" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Rent Price ($)</label>
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
                        {{ t('video.delete_video') || 'Delete Video' }}
                    </button>
                    
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn btn-primary"
                    >
                        <Save class="w-4 h-4 mr-2" />
                        <span v-if="form.processing">{{ t('common.loading') || 'Saving...' }}</span>
                        <span v-else>{{ t('settings.save_changes') || 'Save Changes' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
