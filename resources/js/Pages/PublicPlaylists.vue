<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, TrendingUp, ArrowUpDown, Clock, Heart } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();


const props = defineProps({
    playlists: Object,
    currentSort: { type: String, default: 'newest' },
});


const sortOptions = [
    { value: 'newest', label: 'Newest', icon: Clock },
    { value: 'oldest', label: 'Oldest', icon: ArrowUpDown },
    { value: 'popular', label: 'Most Popular', icon: TrendingUp },
];

const visiblePlaylists = computed(() => {
    return (props.playlists?.data || []).filter((playlist) => Number(playlist.videos_count || 0) > 0);
});

const changeSort = (sort) => {
    router.get('/public-playlists', { sort }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <SeoHead />

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="page-title">{{ t('playlist.public_playlists') }}</h1>
            <p class="mt-1 text-text-secondary">{{ t('playlist.browse_all') }}</p>
        </div>

        <!-- Sort Buttons -->
        <div class="flex gap-2">
            <button
                v-for="opt in sortOptions"
                :key="opt.value"
                @click="changeSort(opt.value)"
                class="px-4 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-1.5"
                :style="{
                    backgroundColor: currentSort === opt.value ? 'var(--color-accent)' : 'var(--color-bg-card)',
                    color: currentSort === opt.value ? '#fff' : 'var(--color-text-secondary)',
                }"
            >
                <component :is="opt.icon" class="w-4 h-4" />
                {{ opt.label }}
            </button>
        </div>
    </div>

    <!-- Playlists Grid -->
    <div v-if="visiblePlaylists.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <Link
            v-for="playlist in visiblePlaylists"
            :key="playlist.id"
            :href="`/playlist/${playlist.slug}`"
            class="card overflow-hidden hover:ring-2 transition-all"
            style="--tw-ring-color: var(--color-accent);"
        >
            <div class="aspect-video flex items-center justify-center bg-bg-secondary">
                <img
                    v-if="playlist.thumbnail"
                    :src="playlist.thumbnail"
                    :alt="playlist.title"
                    class="w-full h-full object-cover"
                    loading="lazy"
                />
                <ListVideo v-else class="w-12 h-12 text-text-muted" />
            </div>
            <div class="p-3">
                <h3 class="font-medium truncate text-text-primary">{{ playlist.title }}</h3>
                <p class="text-sm mt-1 text-text-secondary">
                    {{ playlist.videos_count || 0 }} {{ t('common.videos') }}
                    <span v-if="playlist.user"> &middot; {{ playlist.user.username }}</span>
                </p>
                <p v-if="playlist.favorited_by_count > 0" class="text-xs mt-1 flex items-center gap-1 text-text-muted">
                    <Heart class="w-3 h-3" />
                    {{ t('playlist.favorites_count', { count: playlist.favorited_by_count }) }}
                </p>
            </div>
        </Link>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
        <ListVideo class="w-16 h-16 mx-auto mb-4 text-text-muted" />
        <p class="text-lg text-text-secondary">{{ t('playlist.no_public_playlists') }}</p>
        <p class="mt-2 text-text-muted">{{ t('playlist.no_public_playlists_desc') }}</p>
    </div>

    <!-- Pagination -->
    <div v-if="playlists?.links && playlists.links.length > 3" class="mt-8 flex justify-center gap-2">
        <template v-for="link in playlists.links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                class="px-4 py-2 rounded-lg text-sm"
                :style="{
                    backgroundColor: link.active ? 'var(--color-accent)' : 'var(--color-bg-card)',
                    color: link.active ? '#fff' : 'var(--color-text-secondary)',
                }"
                v-html="link.label"
                preserve-scroll
            />
        </template>
    </div>
</template>
