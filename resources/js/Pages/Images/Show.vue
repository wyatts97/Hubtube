<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImageCard from '@/Components/ImageCard.vue';
import Lightbox from '@/Components/Lightbox.vue';
import { Eye, Calendar, Tag, User, Trash2, Download, Maximize2, ArrowLeft } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    image: Object,
    relatedImages: Array,
    canEdit: Boolean,
});

const showLightbox = ref(false);

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatViews = (count) => {
    if (!count) return '0';
    if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
};

const deleteImage = () => {
    if (confirm('Are you sure you want to delete this image?')) {
        router.delete(`/images/${props.image.id}`);
    }
};

const downloadImage = () => {
    const a = document.createElement('a');
    a.href = props.image.image_url;
    a.download = props.image.title || 'image';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

const lightboxImages = computed(() => {
    return [props.image, ...(props.relatedImages || [])];
});
</script>

<template>
    <Head :title="image.title || 'Image'" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <!-- Back Button -->
            <Link href="/images" class="inline-flex items-center gap-1.5 mb-4 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                Back to Images
            </Link>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Image Display -->
                <div class="lg:col-span-2">
                    <div
                        class="card rounded-xl overflow-hidden cursor-pointer relative group"
                        @click="showLightbox = true"
                    >
                        <img
                            :src="image.image_url"
                            :alt="image.title || 'Image'"
                            class="w-full h-auto max-h-[80vh] object-contain"
                            style="background-color: var(--color-bg-secondary);"
                        />
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/20">
                            <div class="p-3 rounded-full bg-black/60 text-white">
                                <Maximize2 class="w-6 h-6" />
                            </div>
                        </div>
                        <div v-if="image.is_animated" class="absolute top-3 left-3 px-2 py-1 rounded text-xs font-bold uppercase bg-purple-600 text-white">
                            GIF
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="space-y-4">
                    <!-- Title & Description -->
                    <div class="card p-5">
                        <h1 class="text-xl font-bold mb-2" style="color: var(--color-text-primary);">
                            {{ image.title || 'Untitled' }}
                        </h1>
                        <p v-if="image.description" class="text-sm whitespace-pre-wrap" style="color: var(--color-text-secondary);">
                            {{ image.description }}
                        </p>
                    </div>

                    <!-- Uploader -->
                    <div v-if="image.user" class="card p-4">
                        <Link :href="`/channel/${image.user.username}`" class="flex items-center gap-3 hover:opacity-80">
                            <div class="w-10 h-10 avatar">
                                <img :src="image.user.avatar || '/images/default_avatar.webp'" :alt="image.user.username" class="w-full h-full object-cover" />
                            </div>
                            <div>
                                <p class="font-medium text-sm" style="color: var(--color-text-primary);">{{ image.user.username }}</p>
                            </div>
                        </Link>
                    </div>

                    <!-- Stats -->
                    <div class="card p-4 space-y-3">
                        <div class="flex items-center gap-2 text-sm" style="color: var(--color-text-secondary);">
                            <Eye class="w-4 h-4" />
                            <span>{{ formatViews(image.views_count) }} views</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm" style="color: var(--color-text-secondary);">
                            <Calendar class="w-4 h-4" />
                            <span>{{ formatDate(image.published_at || image.created_at) }}</span>
                        </div>
                        <div v-if="image.width && image.height" class="text-sm" style="color: var(--color-text-muted);">
                            {{ image.width }} × {{ image.height }} px — {{ image.formatted_size }}
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="image.tags && image.tags.length" class="card p-4">
                        <div class="flex items-center gap-1.5 mb-2">
                            <Tag class="w-4 h-4" style="color: var(--color-text-muted);" />
                            <span class="text-sm font-medium" style="color: var(--color-text-secondary);">Tags</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in image.tags"
                                :key="tag"
                                class="px-2 py-0.5 rounded text-xs"
                                style="background-color: var(--color-bg-secondary); color: var(--color-text-primary);"
                            >
                                #{{ tag }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button
                            @click="downloadImage"
                            class="flex-1 btn flex items-center justify-center gap-2"
                            style="background-color: var(--color-bg-secondary); color: var(--color-text-primary); border: 1px solid var(--color-border);"
                        >
                            <Download class="w-4 h-4" />
                            Download
                        </button>
                        <button
                            v-if="canEdit"
                            @click="deleteImage"
                            class="btn flex items-center justify-center gap-2 text-red-500"
                            style="background-color: var(--color-bg-secondary); border: 1px solid var(--color-border);"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Related Images -->
            <div v-if="relatedImages && relatedImages.length" class="mt-10">
                <h2 class="text-lg font-bold mb-4" style="color: var(--color-text-primary);">More Images</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                    <ImageCard v-for="img in relatedImages" :key="img.id" :image="img" />
                </div>
            </div>
        </div>

        <!-- Lightbox -->
        <Lightbox
            v-model="showLightbox"
            :images="lightboxImages"
            :start-index="0"
        />
    </AppLayout>
</template>
