<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },
});

const { t, localizedUrl } = useI18n();
</script>

<template>
    <section v-if="images?.length" class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-text-primary">{{ t('home.images') }}</h2>
            <Link :href="localizedUrl('/images')" class="text-sm font-medium text-accent hover:opacity-80">
                {{ t('common.view_all') }}
            </Link>
        </div>

        <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-2 -mx-3 px-3 sm:-mx-4 sm:px-4 lg:-mx-6 lg:px-6">
            <Link
                v-for="image in images"
                :key="image.id"
                :href="localizedUrl(`/image/${image.slug}`)"
                class="shrink-0 relative group"
            >
                <div class="w-[140px] h-[140px] sm:w-[160px] sm:h-[160px] rounded-xl overflow-hidden bg-black border border-border">
                    <img
                        :src="image.thumbnail_url || image.image_url"
                        :alt="image.alt || image.title || 'Image'"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div v-if="image.is_animated" class="absolute top-2 start-2 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-600 text-white">
                        GIF
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                    <div class="absolute bottom-2 start-2 end-2">
                        <p class="text-xs font-medium text-white line-clamp-2">{{ image.title || 'Untitled' }}</p>
                        <p v-if="image.user" class="text-[11px] text-white/80 mt-0.5">{{ image.user.username }}</p>
                    </div>
                </div>
            </Link>
        </div>
    </section>
</template>
