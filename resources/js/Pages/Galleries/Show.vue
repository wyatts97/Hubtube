<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImageCard from '@/Components/ImageCard.vue';
import Lightbox from '@/Components/Lightbox.vue';
import MasonryGrid from '@/Components/MasonryGrid.vue';
import { Eye, Calendar, User, Trash2, ArrowLeft, ImageIcon, Grid3x3 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    gallery: Object,
    images: Object,
    canEdit: Boolean,
});

const showLightbox = ref(false);
const lightboxIndex = ref(0);
const viewMode = ref('grid'); // 'grid' or 'masonry'

const openLightbox = (index) => {
    lightboxIndex.value = index;
    showLightbox.value = true;
};

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

const deleteGallery = () => {
    if (confirm('Are you sure you want to delete this gallery? Images will not be deleted.')) {
        router.delete(`/gallery/${props.gallery.id}`);
    }
};
</script>

<template>
    <Head :title="gallery.title" />

    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <!-- Back -->
            <Link href="/galleries" class="inline-flex items-center gap-1.5 mb-4 text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                <ArrowLeft class="w-4 h-4" />
                Back to Galleries
            </Link>

            <!-- Gallery Header -->
            <div class="card p-5 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ gallery.title }}</h1>
                        <p v-if="gallery.description" class="text-sm mt-1 whitespace-pre-wrap" style="color: var(--color-text-secondary);">
                            {{ gallery.description }}
                        </p>
                        <div class="flex items-center gap-4 mt-3">
                            <Link v-if="gallery.user" :href="`/channel/${gallery.user.username}`" class="flex items-center gap-2 hover:opacity-80">
                                <div class="w-6 h-6 avatar">
                                    <img :src="gallery.user.avatar || '/images/default_avatar.webp'" :alt="gallery.user.username" class="w-full h-full object-cover" />
                                </div>
                                <span class="text-sm" style="color: var(--color-text-secondary);">{{ gallery.user.username }}</span>
                            </Link>
                            <span class="text-sm flex items-center gap-1" style="color: var(--color-text-muted);">
                                <ImageIcon class="w-3.5 h-3.5" />
                                {{ gallery.images_count || 0 }} images
                            </span>
                            <span class="text-sm flex items-center gap-1" style="color: var(--color-text-muted);">
                                <Eye class="w-3.5 h-3.5" />
                                {{ formatViews(gallery.views_count) }}
                            </span>
                            <span class="text-sm flex items-center gap-1" style="color: var(--color-text-muted);">
                                <Calendar class="w-3.5 h-3.5" />
                                {{ formatDate(gallery.created_at) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- View Mode Toggle -->
                        <div class="flex items-center rounded-lg overflow-hidden" style="border: 1px solid var(--color-border);">
                            <button
                                @click="viewMode = 'grid'"
                                class="px-3 py-1.5 text-xs font-medium transition-colors"
                                :style="viewMode === 'grid' ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary);'"
                            >
                                Grid
                            </button>
                            <button
                                @click="viewMode = 'masonry'"
                                class="px-3 py-1.5 text-xs font-medium transition-colors"
                                :style="viewMode === 'masonry' ? 'background-color: var(--color-accent); color: #fff;' : 'color: var(--color-text-secondary); border-left: 1px solid var(--color-border);'"
                            >
                                Masonry
                            </button>
                        </div>
                        <button
                            v-if="canEdit"
                            @click="deleteGallery"
                            class="p-2 rounded-lg text-red-500 hover:opacity-80"
                            style="background-color: var(--color-bg-secondary); border: 1px solid var(--color-border);"
                            title="Delete Gallery"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Grid View -->
            <div v-if="viewMode === 'grid' && images.data.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div
                    v-for="(image, index) in images.data"
                    :key="image.id"
                    class="cursor-pointer group"
                    @click="openLightbox(index)"
                >
                    <div class="relative rounded-xl overflow-hidden" style="background-color: var(--color-bg-secondary);">
                        <div class="aspect-square">
                            <img
                                :src="image.thumbnail_url || image.image_url"
                                :alt="image.title || 'Image'"
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <div v-if="image.is_animated" class="absolute top-2 left-2 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-600 text-white">
                            GIF
                        </div>
                    </div>
                </div>
            </div>

            <!-- Masonry View -->
            <MasonryGrid v-else-if="viewMode === 'masonry' && images.data.length" :columns="4" :gap="16">
                <div
                    v-for="(image, index) in images.data"
                    :key="image.id"
                    class="cursor-pointer group"
                    @click="openLightbox(index)"
                >
                    <div class="rounded-xl overflow-hidden" style="background-color: var(--color-bg-secondary);">
                        <img
                            :src="image.thumbnail_url || image.image_url"
                            :alt="image.title || 'Image'"
                            class="w-full h-auto transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                    </div>
                </div>
            </MasonryGrid>

            <!-- Empty State -->
            <div v-else class="text-center py-16">
                <ImageIcon class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
                <p class="text-lg" style="color: var(--color-text-secondary);">This gallery is empty</p>
            </div>

            <!-- Pagination -->
            <div v-if="images.links && images.links.length > 3" class="mt-8 flex justify-center gap-1.5">
                <template v-for="link in images.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :class="['px-3 py-1.5 rounded-lg text-sm transition-colors']"
                        :style="link.active
                            ? 'background-color: var(--color-accent); color: #fff;'
                            : 'background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);'"
                        v-html="link.label"
                        preserve-scroll
                    />
                    <span
                        v-else
                        class="px-3 py-1.5 rounded-lg text-sm"
                        style="color: var(--color-text-muted);"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <!-- Lightbox -->
        <Lightbox
            v-model="showLightbox"
            :images="images.data"
            :start-index="lightboxIndex"
        />
    </AppLayout>
</template>
