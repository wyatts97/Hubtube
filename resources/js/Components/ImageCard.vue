<script setup>
import { Link } from '@inertiajs/vue3';
import { Eye, ImageIcon } from 'lucide-vue-next';

const props = defineProps({
    image: Object,
});

const formatViews = (count) => {
    if (!count) return '0';
    if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
};
</script>

<template>
    <Link :href="`/image/${image.uuid}`" class="group block">
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
            <div class="absolute bottom-2 right-2 flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-black/70 text-white">
                <Eye class="w-3 h-3" />
                {{ formatViews(image.views_count) }}
            </div>
        </div>
        <div class="mt-2">
            <p class="text-sm font-medium line-clamp-2" style="color: var(--color-text-primary);">
                {{ image.title || 'Untitled' }}
            </p>
            <p v-if="image.user" class="text-xs mt-0.5" style="color: var(--color-text-muted);">
                {{ image.user.username }}
            </p>
        </div>
    </Link>
</template>
