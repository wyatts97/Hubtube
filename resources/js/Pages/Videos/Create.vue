<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Upload, X, Video, FileVideo, CheckCircle, AlertCircle, Smartphone, Calendar } from 'lucide-vue-next';

const props = defineProps({
    categories: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const canSchedule = computed(() => currentUser.value?.is_admin || currentUser.value?.is_pro);

// Detect short upload mode from URL query param
const urlParams = new URLSearchParams(window.location.search);
const isShortMode = ref(urlParams.get('type') === 'short');

const dragActive = ref(false);
const videoPreview = ref(null);
const uploadProgress = ref(0);
const uploadStatus = ref('idle'); // idle, uploading, processing, success, error
const uploadError = ref('');
const videoDuration = ref(null);
const videoWidth = ref(null);
const videoHeight = ref(null);

const enableScheduling = ref(false);

const form = useForm({
    title: '',
    description: '',
    category_id: '',
    privacy: 'public',
    age_restricted: true,
    tags: [],
    video_file: null,
    is_short: isShortMode.value,
    scheduled_at: '',
});

const minScheduleDate = computed(() => {
    const d = new Date();
    d.setMinutes(d.getMinutes() + 5);
    return d.toISOString().slice(0, 16);
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
    const files = e.dataTransfer.files;
    
    if (files.length > 1) {
        uploadError.value = 'Please drop only one video file at a time.';
        return;
    }
    
    const file = files[0];
    if (file && file.type.startsWith('video/')) {
        handleFile(file);
    } else {
        uploadError.value = 'Please drop a valid video file.';
    }
};

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        handleFile(file);
    }
};

const handleFile = (file) => {
    uploadError.value = '';
    
    // Create a temporary video element to read metadata
    const video = document.createElement('video');
    video.preload = 'metadata';
    const objectUrl = URL.createObjectURL(file);
    
    video.onloadedmetadata = () => {
        videoDuration.value = video.duration;
        videoWidth.value = video.videoWidth;
        videoHeight.value = video.videoHeight;
        URL.revokeObjectURL(objectUrl);
        
        // Validate shorts constraints
        if (isShortMode.value) {
            // Must be vertical (height > width)
            if (video.videoWidth >= video.videoHeight) {
                uploadError.value = `Shorts must be vertical (portrait) video. Your video is ${video.videoWidth}×${video.videoHeight} which is landscape/square. Please use a 9:16 vertical video.`;
                resetFileState();
                return;
            }
            
            // Max 60 seconds
            if (video.duration > 60) {
                const dur = Math.ceil(video.duration);
                uploadError.value = `Shorts must be 60 seconds or less. Your video is ${dur} seconds long.`;
                resetFileState();
                return;
            }
        }
        
        // All good — set the file
        form.video_file = file;
        videoPreview.value = URL.createObjectURL(file);
        
        if (!form.title) {
            form.title = file.name.replace(/\.[^/.]+$/, '');
        }
    };
    
    video.onerror = () => {
        uploadError.value = 'Could not read video metadata. Please try a different file.';
        URL.revokeObjectURL(objectUrl);
    };
    
    video.src = objectUrl;
};

const resetFileState = () => {
    form.video_file = null;
    videoPreview.value = null;
    videoDuration.value = null;
    videoWidth.value = null;
    videoHeight.value = null;
};

const removeVideo = () => {
    resetFileState();
    uploadProgress.value = 0;
    uploadStatus.value = 'idle';
    uploadError.value = '';
};

const submit = () => {
    uploadStatus.value = 'uploading';
    uploadProgress.value = 0;
    uploadError.value = '';
    
    form.post('/upload', {
        forceFormData: true,
        onProgress: (progress) => {
            uploadProgress.value = Math.round(progress.percentage);
        },
        onSuccess: () => {
            uploadStatus.value = 'success';
        },
        onError: (errors) => {
            uploadStatus.value = 'error';
            uploadError.value = Object.values(errors).flat().join(', ');
        },
        onFinish: () => {
            if (uploadStatus.value === 'uploading') {
                uploadStatus.value = 'processing';
            }
        },
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

const durationFormatted = computed(() => {
    if (!videoDuration.value) return '';
    const duration = Math.floor(videoDuration.value);
    const hours = Math.floor(duration / 3600);
    const minutes = Math.floor((duration % 3600) / 60);
    const seconds = duration % 60;
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

// Cleanup on unmount
onUnmounted(() => {
    if (videoPreview.value) {
        URL.revokeObjectURL(videoPreview.value);
    }
});
</script>

<template>
    <Head :title="isShortMode ? 'Upload Short' : 'Upload Video'" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <div v-if="isShortMode" class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: var(--color-accent);">
                    <Smartphone class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold" style="color: var(--color-text-primary);">{{ isShortMode ? 'Upload Short' : 'Upload Video' }}</h1>
                    <p v-if="isShortMode" class="text-sm mt-0.5" style="color: var(--color-text-muted);">Vertical video, 60 seconds max</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Video Upload Area -->
                <div
                    v-if="!form.video_file"
                    @dragover.prevent="dragActive = true"
                    @dragleave.prevent="dragActive = false"
                    @drop.prevent="handleDrop"
                    class="card border-2 border-dashed p-6 sm:p-12 text-center transition-colors"
                    :style="{ borderColor: dragActive ? 'var(--color-accent)' : 'var(--color-border)' }"
                >
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: var(--color-bg-secondary);">
                        <Upload class="w-8 h-8" style="color: var(--color-text-muted);" />
                    </div>
                    <p class="text-lg font-medium mb-2" style="color: var(--color-text-primary);">{{ isShortMode ? 'Drag and drop your short' : 'Drag and drop video file' }}</p>
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
                    <p v-if="isShortMode" class="text-sm mt-1" style="color: var(--color-accent);">
                        Must be vertical (9:16) and 60 seconds or less
                    </p>
                </div>

                <!-- Video Preview -->
                <div v-else class="card p-4">
                    <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
                        <div class="w-full sm:w-48 aspect-video rounded-lg overflow-hidden flex-shrink-0 relative" style="background-color: var(--color-bg-secondary);">
                            <video :src="videoPreview" class="w-full h-full object-cover"></video>
                            <div v-if="durationFormatted" class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded text-xs font-medium bg-black/80 text-white">
                                {{ durationFormatted }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 w-full">
                            <div class="flex items-center gap-2">
                                <FileVideo class="w-5 h-5 flex-shrink-0" style="color: var(--color-accent);" />
                                <p class="font-medium truncate" style="color: var(--color-text-primary);">{{ form.video_file.name }}</p>
                            </div>
                            <p class="text-sm mt-1" style="color: var(--color-text-muted);">
                                {{ fileSizeFormatted }}
                                <span v-if="durationFormatted"> • {{ durationFormatted }}</span>
                            </p>
                            
                            <!-- Upload Progress Bar -->
                            <div v-if="uploadStatus === 'uploading'" class="mt-3">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span style="color: var(--color-text-secondary);">Uploading...</span>
                                    <span style="color: var(--color-accent);">{{ uploadProgress }}%</span>
                                </div>
                                <div class="h-2 rounded-full overflow-hidden" style="background-color: var(--color-bg-secondary);">
                                    <div 
                                        class="h-full rounded-full transition-all duration-300 ease-out"
                                        :style="{ width: uploadProgress + '%', backgroundColor: 'var(--color-accent)' }"
                                    ></div>
                                </div>
                            </div>
                            
                            <!-- Processing Status -->
                            <div v-else-if="uploadStatus === 'processing'" class="mt-3 flex items-center gap-2">
                                <div class="w-4 h-4 border-2 border-t-transparent rounded-full animate-spin" style="border-color: var(--color-accent); border-top-color: transparent;"></div>
                                <span class="text-sm" style="color: var(--color-text-secondary);">Processing video...</span>
                            </div>
                            
                            <!-- Success Status -->
                            <div v-else-if="uploadStatus === 'success'" class="mt-3 flex items-center gap-2 text-green-500">
                                <CheckCircle class="w-4 h-4" />
                                <span class="text-sm">Upload complete!</span>
                            </div>
                            
                            <!-- Error Status -->
                            <div v-else-if="uploadStatus === 'error'" class="mt-3 flex items-center gap-2 text-red-500">
                                <AlertCircle class="w-4 h-4" />
                                <span class="text-sm">{{ uploadError || 'Upload failed' }}</span>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="removeVideo" 
                            :disabled="uploadStatus === 'uploading'"
                            class="p-2 rounded-full hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed" 
                            style="background-color: var(--color-bg-secondary);"
                        >
                            <X class="w-5 h-5" style="color: var(--color-text-muted);" />
                        </button>
                    </div>
                    <p v-if="form.errors.video_file" class="text-red-500 text-sm mt-2">{{ form.errors.video_file }}</p>
                </div>
                
                <!-- Upload Error Message -->
                <div v-if="uploadError && !form.video_file" class="p-4 rounded-lg border border-red-500/30 bg-red-500/10">
                    <div class="flex items-center gap-2 text-red-500">
                        <AlertCircle class="w-5 h-5" />
                        <span>{{ uploadError }}</span>
                    </div>
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

                <!-- Scheduling (Admin/Pro only) -->
                <div v-if="canSchedule && !isShortMode" class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Calendar class="w-5 h-5" style="color: var(--color-accent);" />
                            <div>
                                <p class="font-medium" style="color: var(--color-text-primary);">Schedule Upload</p>
                                <p class="text-sm" style="color: var(--color-text-muted);">Set a future date and time to publish</p>
                            </div>
                        </div>
                        <input
                            v-model="enableScheduling"
                            type="checkbox"
                            class="w-5 h-5 rounded"
                            @change="!enableScheduling && (form.scheduled_at = '')"
                        />
                    </div>
                    <div v-if="enableScheduling" class="mt-4">
                        <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Publish Date & Time</label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            :min="minScheduleDate"
                            class="input"
                            required
                        />
                        <p v-if="form.errors.scheduled_at" class="text-red-500 text-sm mt-1">{{ form.errors.scheduled_at }}</p>
                        <p class="text-xs mt-1" style="color: var(--color-text-muted);">The video will be processed immediately but published at the scheduled time.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing || !form.video_file"
                        class="btn btn-primary"
                    >
                        <span v-if="form.processing">Uploading...</span>
                        <span v-else-if="enableScheduling && form.scheduled_at">Schedule {{ isShortMode ? 'Short' : 'Video' }}</span>
                        <span v-else>{{ isShortMode ? 'Upload Short' : 'Upload Video' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
