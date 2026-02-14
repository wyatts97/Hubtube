<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Hash } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t, localizedUrl } = useI18n();

const props = defineProps({
    tags: Array,
});

const page = usePage();
const typography = computed(() => page.props.theme?.categoryTypography || {});

const titleStyle = computed(() => ({
    fontFamily: typography.value.font || 'inherit',
    fontSize: (typography.value.size || 18) + 'px',
    color: typography.value.color || '#ffffff',
    opacity: (typography.value.opacity || 90) / 100,
}));

const placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23181818' width='640' height='360'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23555' font-size='48' font-family='sans-serif'%3E%23%3C/text%3E%3C/svg%3E";
</script>

<template>
    <Head :title="t('tags.title') !== 'tags.title' ? t('tags.title') : 'Tags'" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('tags.title') !== 'tags.title' ? t('tags.title') : 'Tags' }}</h1>
            <p class="text-sm mt-1" style="color: var(--color-text-muted);">{{ t('tags.browse') !== 'tags.browse' ? t('tags.browse') : 'Browse videos by tag' }}</p>
        </div>

        <div v-if="tags.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <Link
                v-for="tag in tags"
                :key="tag.name"
                :href="localizedUrl(`/tag/${encodeURIComponent(tag.name)}`)"
                class="group relative rounded-xl overflow-hidden cursor-pointer"
                style="aspect-ratio: 16/9;"
            >
                <!-- Thumbnail from latest video -->
                <img
                    v-if="tag.thumbnail"
                    :src="tag.thumbnail"
                    :alt="tag.name"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                    loading="lazy"
                    @error="(e) => e.target.src = placeholderImg"
                />
                <div
                    v-else
                    class="w-full h-full flex items-center justify-center"
                    style="background-color: var(--color-bg-card);"
                >
                    <Hash class="w-12 h-12" style="color: var(--color-text-muted);" />
                </div>

                <!-- Dark overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent transition-opacity group-hover:from-black/90"></div>

                <!-- Tag title overlay -->
                <div class="absolute inset-0 flex flex-col items-center justify-center p-3">
                    <span
                        class="font-bold text-center drop-shadow-lg"
                        :style="titleStyle"
                    >
                        #{{ tag.name }}
                    </span>
                    <span class="text-xs mt-1 text-white/70">{{ tag.count }} {{ t('common.videos') !== 'common.videos' ? t('common.videos') : 'videos' }}</span>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-12">
            <Hash class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('tags.no_tags') !== 'tags.no_tags' ? t('tags.no_tags') : 'No tags yet' }}</p>
        </div>
    </AppLayout>
</template>
