<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, reactive, onUnmounted, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Upload, X, FileVideo, CheckCircle, AlertCircle, Calendar, Pause, Play, Loader2 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { useChunkedUpload } from '@/Composables/useChunkedUpload';

const { t } = useI18n();

const props = defineProps({
    categories: Array,
    existingTags: { type: Array, default: () => [] },
    uploadLimitReached: { type: Boolean, default: false },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const canSchedule = computed(() => currentUser.value?.is_admin || currentUser.value?.is_pro);
const allowedExtensions = computed(() => page.props.app?.upload?.allowed_extensions || ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv']);
const maxUploadBytes = computed(() => {
    const mb = currentUser.value?.is_pro
        ? (page.props.app?.upload?.max_size_pro || 5000)
        : (page.props.app?.upload?.max_size_free || 500);
    return mb * 1048576;
});

const maxSizeProMb = computed(() => page.props.app?.upload?.max_size_pro || 5000);
const maxSizeFreeMb = computed(() => page.props.app?.upload?.max_size_free || 500);
const maxDailyPro = computed(() => page.props.app?.upload?.max_daily_uploads_pro || 50);
const maxDailyFree = computed(() => page.props.app?.upload?.max_daily_uploads_free || 5);
const proEnabled = computed(() => page.props.app?.pro_enabled !== false);

// ── Form state (metadata only — file goes through useChunkedUpload) ─────────
const form = reactive({
    title: '',
    description: '',
    category_id: '',
    age_restricted: true,
    tags: [],
    scheduled_at: '',
});
const fieldErrors = ref({});
const submitAttempted = ref(false);
const submitting = ref(false);
const enableScheduling = ref(false);

// ── File state ──────────────────────────────────────────────────────────────
const dragActive = ref(false);
const fullPageDrag = ref(false);
const videoPreview = ref(null);
const videoFile = ref(null);
const videoDuration = ref(null);
const videoWidth = ref(null);
const videoHeight = ref(null);
const previewThumb = ref(null); // captured first-frame
const fileError = ref('');

// ── Chunked uploader ────────────────────────────────────────────────────────
const upload = useChunkedUpload({ chunkSize: 8 * 1024 * 1024, parallel: 3, maxRetries: 3 });

const minScheduleDate = computed(() => {
    const d = new Date();
    d.setMinutes(d.getMinutes() + 5);
    return d.toISOString().slice(0, 16);
});

// ── Tags ────────────────────────────────────────────────────────────────────
const tagInput = ref('');
const showTagSuggestions = ref(false);

const recentTags = computed(() =>
    (props.existingTags || []).slice(0, 20).filter(tg => !form.tags.includes(tg))
);

const filteredTags = computed(() => {
    const q = tagInput.value.trim().replace(/^#/, '').toLowerCase();
    if (!q) return [];
    return (props.existingTags || [])
        .filter(t => t.toLowerCase().includes(q) && !form.tags.includes(t))
        .slice(0, 10);
});

const addTag = (tagValue) => {
    const raw = (typeof tagValue === 'string' ? tagValue : tagInput.value);
    if (!raw) return;
    // Support paste-multiple: split on comma / newline / tab
    const pieces = raw.split(/[,\n\t]+/).map(s => s.trim().replace(/^#/, '')).filter(Boolean);
    for (const piece of pieces) {
        if (piece && !form.tags.includes(piece) && form.tags.length < 20 && piece.length >= 2 && piece.length <= 50) {
            form.tags.push(piece);
        }
    }
    tagInput.value = '';
    showTagSuggestions.value = false;
};

const removeTag = (index) => {
    form.tags.splice(index, 1);
};

const handleTagKeydown = (e) => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(tagInput.value);
    } else if (e.key === 'Backspace' && !tagInput.value && form.tags.length) {
        form.tags.pop();
    }
};

// Drag-reorder tags
const dragTagIndex = ref(null);
const onTagDragStart = (i) => { dragTagIndex.value = i; };
const onTagDragOver = (e) => { e.preventDefault(); };
const onTagDrop = (i) => {
    if (dragTagIndex.value === null || dragTagIndex.value === i) return;
    const [moved] = form.tags.splice(dragTagIndex.value, 1);
    form.tags.splice(i, 0, moved);
    dragTagIndex.value = null;
};

// ── File handling ───────────────────────────────────────────────────────────
const handleDrop = (e) => {
    dragActive.value = false;
    fullPageDrag.value = false;
    const files = e.dataTransfer.files;
    if (files.length > 1) {
        fileError.value = 'Please drop only one video file at a time.';
        return;
    }
    const file = files[0];
    if (file) handleFile(file);
};

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) handleFile(file);
};

const handleFile = (file) => {
    fileError.value = '';

    // Pre-flight: extension
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!allowedExtensions.value.includes(ext)) {
        fileError.value = `Unsupported format. Allowed: ${allowedExtensions.value.join(', ')}`;
        return;
    }
    // Pre-flight: size
    if (file.size > maxUploadBytes.value) {
        fileError.value = `File too large. Maximum is ${formatBytes(maxUploadBytes.value)}.`;
        return;
    }

    // Read metadata + capture first frame
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.muted = true;
    const objectUrl = URL.createObjectURL(file);

    video.onloadedmetadata = () => {
        videoDuration.value = video.duration;
        videoWidth.value = video.videoWidth;
        videoHeight.value = video.videoHeight;

        videoFile.value = file;
        videoPreview.value = objectUrl;

        if (!form.title) {
            form.title = file.name.replace(/\.[^/.]+$/, '').replace(/[_-]+/g, ' ').trim();
        }

        // Capture first frame as a poster
        try {
            video.currentTime = Math.min(0.5, video.duration / 2);
            video.onseeked = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth || 320;
                    canvas.height = video.videoHeight || 180;
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    previewThumb.value = canvas.toDataURL('image/jpeg', 0.7);
                } catch (e) { /* tainted canvas — ignore */ }
            };
        } catch (e) { /* ignore */ }

        // Kick off the upload immediately (two-step flow)
        upload.start(file);
    };

    video.onerror = () => {
        fileError.value = 'Could not read video metadata. Please try a different file.';
        URL.revokeObjectURL(objectUrl);
    };

    video.src = objectUrl;
};

const removeVideo = () => {
    if (upload.status.value === 'uploading' || upload.status.value === 'paused') {
        upload.abort();
    }
    if (videoPreview.value) URL.revokeObjectURL(videoPreview.value);
    videoFile.value = null;
    videoPreview.value = null;
    previewThumb.value = null;
    videoDuration.value = null;
    videoWidth.value = null;
    videoHeight.value = null;
    fileError.value = '';
    upload.percent.value = 0;
    upload.status.value = 'idle';
    upload.error.value = null;
};

// ── Validation ──────────────────────────────────────────────────────────────
const titleValid = computed(() => form.title.trim().length >= 3 && form.title.length <= 200);
const descValid = computed(() => form.description.trim().length >= 10 && form.description.length <= 5000);
const categoryValid = computed(() => !!form.category_id);
const tagsValid = computed(() => form.tags.length >= 3 && form.tags.length <= 20);
const fileChosen = computed(() => !!videoFile.value);
const formValid = computed(() => titleValid.value && descValid.value && categoryValid.value && tagsValid.value && fileChosen.value);

// ── Submit / finalize ───────────────────────────────────────────────────────
const submit = async () => {
    submitAttempted.value = true;
    fieldErrors.value = {};

    if (!formValid.value) return;

    submitting.value = true;

    // Wait for upload to complete if still in flight
    if (upload.status.value === 'uploading' || upload.status.value === 'paused') {
        if (upload.status.value === 'paused') upload.resume();
        // poll every 500ms
        await new Promise(resolve => {
            const tick = () => {
                if (upload.status.value === 'complete') resolve();
                else if (upload.status.value === 'error' || upload.status.value === 'aborted') resolve();
                else setTimeout(tick, 500);
            };
            tick();
        });
    }

    if (upload.status.value !== 'complete') {
        submitting.value = false;
        fieldErrors.value = { upload: upload.error.value || 'Upload did not complete successfully.' };
        return;
    }

    const metadata = {
        title: form.title.trim(),
        description: form.description.trim(),
        category_id: form.category_id,
        age_restricted: form.age_restricted ? '1' : '0',
        tags: form.tags,
    };
    if (canSchedule.value && enableScheduling.value && form.scheduled_at) {
        metadata.scheduled_at = form.scheduled_at;
    }

    const result = await upload.finalize(metadata);
    submitting.value = false;

    if (result.ok) {
        if (result.redirect) {
            router.visit(result.redirect);
        }
    } else {
        fieldErrors.value = result.errors || { upload: 'Failed to publish video.' };
    }
};

// ── Helpers ─────────────────────────────────────────────────────────────────
const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    return (bytes / 1024).toFixed(2) + ' KB';
};

const fileSizeFormatted = computed(() => videoFile.value ? formatBytes(videoFile.value.size) : '');

const durationFormatted = computed(() => {
    if (!videoDuration.value) return '';
    const d = Math.floor(videoDuration.value);
    const h = Math.floor(d / 3600);
    const m = Math.floor((d % 3600) / 60);
    const s = d % 60;
    return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${m}:${String(s).padStart(2, '0')}`;
});

const speedFormatted = computed(() => {
    const bps = upload.bytesPerSecond.value;
    if (!bps) return '';
    return formatBytes(bps) + '/s';
});

const etaFormatted = computed(() => {
    const s = upload.etaSeconds.value;
    if (s === null || s === undefined || !isFinite(s)) return '';
    if (s < 60) return `${s}s remaining`;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}m ${sec}s remaining`;
});

const submitLabel = computed(() => {
    if (submitting.value) return 'Publishing…';
    if (upload.status.value === 'uploading' && formValid.value) return `Publish when ready (${upload.percent.value}%)`;
    if (upload.status.value === 'uploading') return `Uploading… ${upload.percent.value}%`;
    if (upload.status.value === 'paused') return 'Paused — Resume to continue';
    if (upload.status.value === 'complete' && !formValid.value) return 'Complete required fields';
    if (enableScheduling.value && form.scheduled_at) return 'Schedule';
    return t('upload.title') || 'Publish Video';
});

const submitDisabled = computed(() => {
    if (submitting.value) return true;
    if (!fileChosen.value) return true;
    if (upload.status.value === 'error' || upload.status.value === 'aborted') return true;
    if (!formValid.value) return true;
    if (upload.status.value === 'paused') return true;
    return false;
});

// ── Full-page drag overlay ──────────────────────────────────────────────────
let dragCounter = 0;
const onWindowDragEnter = (e) => {
    if (!e.dataTransfer || !e.dataTransfer.types?.includes('Files')) return;
    dragCounter++;
    fullPageDrag.value = true;
};
const onWindowDragLeave = () => {
    dragCounter = Math.max(0, dragCounter - 1);
    if (dragCounter === 0) fullPageDrag.value = false;
};
const onWindowDrop = () => {
    dragCounter = 0;
    fullPageDrag.value = false;
};

onMounted(() => {
    window.addEventListener('dragenter', onWindowDragEnter);
    window.addEventListener('dragleave', onWindowDragLeave);
    window.addEventListener('drop', onWindowDrop);
});

onUnmounted(() => {
    window.removeEventListener('dragenter', onWindowDragEnter);
    window.removeEventListener('dragleave', onWindowDragLeave);
    window.removeEventListener('drop', onWindowDrop);
    if (videoPreview.value) URL.revokeObjectURL(videoPreview.value);
    if (upload.status.value === 'uploading' || upload.status.value === 'paused') {
        upload.abort();
    }
});

// Surface server errors after finalize attempt
watch(fieldErrors, (errs) => {
    if (errs && Object.keys(errs).length) {
        // smooth scroll to first error
        setTimeout(() => {
            const el = document.querySelector('.field-error');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 50);
    }
}, { deep: true });
</script>

<template>
    <Head :title="t('upload.title') || 'Upload Video'" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-text-primary">{{ t('upload.title') || 'Upload Video' }}</h1>
                </div>
            </div>

            <!-- Upload limit reached banner -->
            <div v-if="uploadLimitReached" class="card p-6 mb-6 border bg-bg-card" style="border-color: var(--color-accent);">
                <div class="flex items-start gap-4">
                    <AlertCircle class="w-8 h-8 shrink-0 mt-0.5 text-accent" />
                    <div>
                        <h2 class="text-lg font-semibold mb-1 text-text-primary">Daily Upload Limit Reached</h2>
                        <p class="text-sm text-text-secondary">
                            You've reached your maximum number of uploads for today. Your limit resets at midnight.
                        </p>
                        <a
                            v-if="proEnabled && !currentUser?.is_pro"
                            href="/pro"
                            class="inline-block mt-2 text-sm font-medium text-accent hover:underline"
                        >
                            Upgrade to Pro for up to {{ maxDailyPro }} uploads/day →
                        </a>
                    </div>
                </div>
            </div>

            <!-- Full-page drag overlay -->
            <Teleport to="body">
                <div
                    v-if="fullPageDrag && !videoFile && !uploadLimitReached"
                    class="fixed inset-0 z-[9999] flex items-center justify-center pointer-events-none"
                    style="background-color: rgba(0,0,0,0.7); backdrop-filter: blur(4px);"
                >
                    <div class="text-center">
                        <Upload class="w-20 h-20 mx-auto mb-4 text-accent" />
                        <p class="text-2xl font-bold text-white">Drop your video anywhere</p>
                    </div>
                </div>
            </Teleport>

            <form v-if="!uploadLimitReached" @submit.prevent="submit" class="space-y-6">
                <!-- Video Upload Area -->
                <div
                    v-if="!videoFile"
                    @dragover.prevent="dragActive = true"
                    @dragleave.prevent="dragActive = false"
                    @drop.prevent="handleDrop"
                    class="card border-2 border-dashed p-6 sm:p-12 text-center transition-colors"
                    :style="{ borderColor: dragActive ? 'var(--color-accent)' : 'var(--color-border)' }"
                >
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-bg-secondary">
                        <Upload class="w-8 h-8 text-text-muted" />
                    </div>
                    <p class="text-lg font-medium mb-2 text-text-primary">{{ t('upload.drag_drop') || 'Drag and drop video file' }}</p>
                    <p class="mb-4 text-text-muted">{{ t('upload.or_browse') || 'or click to browse' }}</p>
                    <label class="btn btn-primary cursor-pointer">
                        {{ t('upload.select_file') || 'Select File' }}
                        <input
                            type="file"
                            accept="video/*"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                    </label>
                    <p class="text-sm mt-4 text-text-muted">
                        Supported: {{ allowedExtensions.join(', ').toUpperCase() }} · max {{ formatBytes(maxUploadBytes) }}
                    </p>
                    <p class="text-xs mt-2 text-text-muted">
                        Free: {{ maxSizeFreeMb }} MB / {{ maxDailyFree }} uploads/day · Pro: {{ maxSizeProMb }} MB / {{ maxDailyPro }} uploads/day
                    </p>
                    <p v-if="fileError" class="text-red-500 text-sm mt-3 field-error">{{ fileError }}</p>
                    <a
                        v-if="proEnabled && !currentUser?.is_pro && fileError && videoFile && videoFile.size > maxUploadBytes"
                        href="/pro"
                        class="inline-block mt-2 text-sm font-medium text-accent hover:underline"
                    >
                        Upgrade to Pro to upload up to {{ maxSizeProMb }} MB →
                    </a>
                </div>

                <!-- Video Preview + Upload Progress -->
                <div v-else class="card p-4">
                    <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
                        <div class="w-full sm:w-48 aspect-video rounded-lg overflow-hidden shrink-0 relative bg-bg-secondary">
                            <img v-if="previewThumb" :src="previewThumb" class="w-full h-full object-cover" alt="Video preview" />
                            <video v-else :src="videoPreview" preload="metadata" class="w-full h-full object-cover" muted></video>
                            <div v-if="durationFormatted" class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded text-xs font-medium bg-black/80 text-white">
                                {{ durationFormatted }}
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 w-full">
                            <div class="flex items-center gap-2">
                                <FileVideo class="w-5 h-5 shrink-0 text-accent" />
                                <p class="font-medium truncate text-text-primary">{{ videoFile.name }}</p>
                            </div>
                            <p class="text-sm mt-1 text-text-muted">
                                {{ fileSizeFormatted }}
                                <span v-if="durationFormatted"> · {{ durationFormatted }}</span>
                                <span v-if="videoWidth && videoHeight"> · {{ videoWidth }}×{{ videoHeight }}</span>
                            </p>

                            <!-- Upload Progress Bar -->
                            <div v-if="upload.status.value === 'uploading' || upload.status.value === 'paused'" class="mt-3">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span class="text-text-secondary">
                                        {{ upload.status.value === 'paused' ? 'Paused' : (t('video.uploading') || 'Uploading…') }}
                                        <span v-if="speedFormatted && upload.status.value === 'uploading'" class="text-text-muted"> · {{ speedFormatted }}</span>
                                        <span v-if="etaFormatted && upload.status.value === 'uploading'" class="text-text-muted"> · {{ etaFormatted }}</span>
                                    </span>
                                    <span class="text-accent">{{ upload.percent.value }}%</span>
                                </div>
                                <div class="h-2 rounded-full overflow-hidden bg-bg-secondary">
                                    <div
                                        class="h-full rounded-full transition-all duration-300 ease-out"
                                        :style="{ width: upload.percent.value + '%', backgroundColor: 'var(--color-accent)' }"
                                    ></div>
                                </div>
                                <div class="flex items-center gap-2 mt-2">
                                    <button
                                        type="button"
                                        v-if="upload.status.value === 'uploading'"
                                        @click="upload.pause()"
                                        class="text-xs px-2 py-1 rounded bg-bg-secondary text-text-secondary hover:opacity-80 inline-flex items-center gap-1"
                                    >
                                        <Pause class="w-3 h-3" /> Pause
                                    </button>
                                    <button
                                        type="button"
                                        v-else-if="upload.status.value === 'paused'"
                                        @click="upload.resume()"
                                        class="text-xs px-2 py-1 rounded bg-accent text-white hover:opacity-80 inline-flex items-center gap-1"
                                    >
                                        <Play class="w-3 h-3" /> Resume
                                    </button>
                                </div>
                            </div>

                            <!-- Complete -->
                            <div v-else-if="upload.status.value === 'complete'" class="mt-3 flex items-center gap-2 text-green-500">
                                <CheckCircle class="w-4 h-4" />
                                <span class="text-sm">Upload complete · ready to publish</span>
                            </div>

                            <!-- Error -->
                            <div v-else-if="upload.status.value === 'error'" class="mt-3 flex items-center gap-2 text-red-500 field-error">
                                <AlertCircle class="w-4 h-4" />
                                <span class="text-sm">{{ upload.error.value || 'Upload failed' }}</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="removeVideo"
                            class="p-2 rounded-full hover:opacity-80 bg-bg-secondary"
                            :title="t('common.remove') || 'Remove'"
                        >
                            <X class="w-5 h-5 text-text-muted" />
                        </button>
                    </div>
                    <p v-if="fieldErrors.video_file" class="text-red-500 text-sm mt-2 field-error">{{ fieldErrors.video_file }}</p>
                    <p v-if="fieldErrors.upload" class="text-red-500 text-sm mt-2 field-error">{{ fieldErrors.upload }}</p>
                </div>

                <!-- Video Details -->
                <div class="card p-6 space-y-4">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium mb-1 text-text-secondary">
                            {{ t('upload.video_title') || 'Title' }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="input"
                            :class="{ 'border-red-500': submitAttempted && !titleValid }"
                            maxlength="200"
                            required
                            aria-required="true"
                            :aria-invalid="submitAttempted && !titleValid"
                        />
                        <div class="flex items-center justify-between mt-1">
                            <p v-if="submitAttempted && !titleValid" class="text-red-500 text-xs field-error">
                                Title must be at least 3 characters.
                            </p>
                            <p v-else-if="fieldErrors.title" class="text-red-500 text-xs field-error">{{ fieldErrors.title }}</p>
                            <span v-else></span>
                            <span class="text-xs text-text-muted" :class="{ 'text-amber-500': form.title.length > 180, 'text-red-500': form.title.length >= 200 }">
                                {{ form.title.length }}/200
                            </span>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium mb-1 text-text-secondary">
                            {{ t('upload.video_description') || 'Description' }} <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="input resize-none"
                            :class="{ 'border-red-500': submitAttempted && !descValid }"
                            maxlength="5000"
                            required
                            aria-required="true"
                            :aria-invalid="submitAttempted && !descValid"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1">
                            <p v-if="submitAttempted && !descValid" class="text-red-500 text-xs field-error">
                                Description must be at least 10 characters.
                            </p>
                            <p v-else-if="fieldErrors.description" class="text-red-500 text-xs field-error">{{ fieldErrors.description }}</p>
                            <span v-else></span>
                            <span class="text-xs text-text-muted" :class="{ 'text-amber-500': form.description.length > 4500, 'text-red-500': form.description.length >= 5000 }">
                                {{ form.description.length }}/5000
                            </span>
                        </div>
                    </div>

                    <!-- Category (privacy removed) -->
                    <div>
                        <label for="category" class="block text-sm font-medium mb-1 text-text-secondary">
                            {{ t('video.category') || 'Category' }} <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="category"
                            v-model="form.category_id"
                            class="input"
                            :class="{ 'border-red-500': submitAttempted && !categoryValid }"
                            required
                            aria-required="true"
                        >
                            <option value="">Select a category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <p v-if="submitAttempted && !categoryValid" class="text-red-500 text-xs mt-1 field-error">Please select a category.</p>
                        <p v-else-if="fieldErrors.category_id" class="text-red-500 text-xs mt-1 field-error">{{ fieldErrors.category_id }}</p>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-sm font-medium mb-1 text-text-secondary">
                            {{ t('video.tags') || 'Tags' }} <span class="text-red-500">*</span>
                            <span class="ml-1 text-xs font-normal text-text-muted">(at least 3, up to 20)</span>
                        </label>
                        <div
                            class="flex flex-wrap gap-2 mb-2 min-h-[2rem] p-2 rounded-md bg-bg-secondary"
                            :class="{ 'ring-1 ring-red-500': submitAttempted && !tagsValid }"
                        >
                            <span
                                v-for="(tag, index) in form.tags"
                                :key="index"
                                draggable="true"
                                @dragstart="onTagDragStart(index)"
                                @dragover="onTagDragOver"
                                @drop="onTagDrop(index)"
                                class="flex items-center gap-1 px-2 py-1 rounded text-sm bg-bg-card text-text-primary cursor-move select-none"
                            >
                                #{{ tag }}
                                <button type="button" @click="removeTag(index)" class="hover:text-red-400" :aria-label="`Remove tag ${tag}`">
                                    <X class="w-3 h-3" />
                                </button>
                            </span>
                            <span v-if="!form.tags.length" class="text-xs text-text-muted self-center">No tags yet — add some below</span>
                        </div>
                        <div class="relative">
                            <input
                                v-model="tagInput"
                                type="text"
                                class="input"
                                :placeholder="t('upload.add_tag') || 'Type a tag and press Enter (or paste comma-separated)'"
                                @keydown="handleTagKeydown"
                                @focus="showTagSuggestions = true"
                                @blur="setTimeout(() => showTagSuggestions = false, 200)"
                                autocomplete="off"
                                aria-describedby="tags-help"
                            />
                            <div v-if="showTagSuggestions && filteredTags.length" class="absolute z-50 w-full mt-1 rounded-lg shadow-xl overflow-hidden max-h-48 overflow-y-auto bg-bg-card border border-border">
                                <button
                                    v-for="suggestion in filteredTags"
                                    :key="suggestion"
                                    type="button"
                                    class="w-full text-left px-3 py-2 text-sm hover:opacity-80 transition-opacity text-text-primary"
                                    @mousedown.prevent="addTag(suggestion)"
                                >
                                    #{{ suggestion }}
                                </button>
                            </div>
                        </div>
                        <!-- Popular tags — horizontal scrollable pills -->
                        <div v-if="recentTags.length" class="mt-2">
                            <p class="text-xs text-text-muted mb-1">Popular tags:</p>
                            <div class="flex items-center gap-1.5 overflow-x-auto pb-1" style="scrollbar-width: thin;">
                                <button
                                    v-for="rt in recentTags"
                                    :key="rt"
                                    type="button"
                                    @click="addTag(rt)"
                                    class="text-xs px-2 py-0.5 rounded-full bg-bg-secondary text-text-secondary hover:bg-bg-card transition-colors shrink-0 whitespace-nowrap"
                                >
                                    + {{ rt }}
                                </button>
                            </div>
                        </div>
                        <p v-if="submitAttempted && !tagsValid" class="text-red-500 text-xs mt-2 field-error">
                            Please add at least 3 tags ({{ form.tags.length }}/3).
                        </p>
                        <p v-else-if="fieldErrors.tags" class="text-red-500 text-xs mt-2 field-error">{{ fieldErrors.tags }}</p>
                    </div>
                </div>

                <!-- Validation checklist -->
                <div v-if="submitAttempted && !formValid" class="card p-4 border" style="border-color: var(--color-accent);">
                    <p class="text-sm font-medium mb-2 text-text-primary">Before you can publish, please fix:</p>
                    <ul class="text-sm space-y-1">
                        <li v-if="!fileChosen" class="flex items-center gap-2 text-red-500"><AlertCircle class="w-4 h-4" /> Select a video file</li>
                        <li v-if="!titleValid" class="flex items-center gap-2 text-red-500"><AlertCircle class="w-4 h-4" /> Title (3+ characters)</li>
                        <li v-if="!descValid" class="flex items-center gap-2 text-red-500"><AlertCircle class="w-4 h-4" /> Description (10+ characters)</li>
                        <li v-if="!categoryValid" class="flex items-center gap-2 text-red-500"><AlertCircle class="w-4 h-4" /> Category</li>
                        <li v-if="!tagsValid" class="flex items-center gap-2 text-red-500"><AlertCircle class="w-4 h-4" /> At least 3 tags ({{ form.tags.length }}/3)</li>
                    </ul>
                </div>

                <!-- Scheduling (Admin/Pro only) -->
                <div v-if="canSchedule" class="card p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Calendar class="w-5 h-5 text-accent" />
                            <div>
                                <p class="font-medium text-text-primary">{{ t('upload.schedule') || 'Schedule Upload' }}</p>
                                <p class="text-sm text-text-muted">{{ t('upload.schedule_desc') || 'Set a future date and time to publish' }}</p>
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
                        <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('upload.publish_date') || 'Publish Date & Time' }}</label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            :min="minScheduleDate"
                            class="input"
                            required
                        />
                        <p v-if="fieldErrors.scheduled_at" class="text-red-500 text-sm mt-1 field-error">{{ fieldErrors.scheduled_at }}</p>
                        <p class="text-xs mt-1 text-text-muted">The video will be processed immediately but published at the scheduled time.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 items-center">
                    <Loader2 v-if="submitting" class="w-4 h-4 animate-spin text-accent" />
                    <button
                        type="submit"
                        :disabled="submitDisabled"
                        class="btn btn-primary"
                    >
                        {{ submitLabel }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
