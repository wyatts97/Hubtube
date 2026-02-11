<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Folder } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    categories: Array,
});

const page = usePage();
const typography = computed(() => page.props.theme?.categoryTypography || {});

const titleStyle = computed(() => ({
    fontFamily: typography.value.font || 'inherit',
    fontSize: (typography.value.size || 18) + 'px',
    color: typography.value.color || '#ffffff',
    opacity: (typography.value.opacity || 90) / 100,
}));
</script>

<template>
    <Head :title="t('categories.title') || 'Categories'" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ t('categories.title') || 'Categories' }}</h1>
            <p class="text-sm mt-1" style="color: var(--color-text-muted);">{{ t('categories.browse') || 'Browse videos by category' }}</p>
        </div>

        <div v-if="categories.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="`/category/${category.slug}`"
                class="group relative rounded-xl overflow-hidden cursor-pointer"
                style="aspect-ratio: 16/9;"
            >
                <!-- Thumbnail from latest video -->
                <img
                    v-if="category.latest_thumbnail"
                    :src="category.latest_thumbnail"
                    :alt="category.name"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
                <div
                    v-else
                    class="w-full h-full flex items-center justify-center"
                    style="background-color: var(--color-bg-card);"
                >
                    <Folder class="w-12 h-12" style="color: var(--color-text-muted);" />
                </div>

                <!-- Dark overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent transition-opacity group-hover:from-black/90"></div>

                <!-- Category title overlay -->
                <div class="absolute inset-0 flex flex-col items-center justify-center p-3">
                    <span
                        class="font-bold text-center drop-shadow-lg"
                        :style="titleStyle"
                    >
                        {{ category.name }}
                    </span>
                    <span class="text-xs mt-1 text-white/70">{{ category.videos_count }} {{ t('common.videos') || 'videos' }}</span>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-12">
            <Folder class="w-12 h-12 mx-auto mb-3" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ t('categories.no_categories') || 'No categories yet' }}</p>
        </div>
    </AppLayout>
</template>
