<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ListVideo, TrendingUp, ArrowUpDown, Clock, Heart } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const tSafe = (key, fallback) => {
    const val = t(key);
    return val === key ? fallback : val;
};

const props = defineProps({
    playlists: Object,
    currentSort: { type: String, default: 'newest' },
});

const isInitialLoad = ref(true);
onMounted(() => { setTimeout(() => { isInitialLoad.value = false; }, 100); });

const sortOptions = [
    { value: 'newest', label: 'Newest', icon: Clock },
    { value: 'oldest', label: 'Oldest', icon: ArrowUpDown },
    { value: 'popular', label: 'Most Popular', icon: TrendingUp },
];

const changeSort = (sort) => {
    router.get('/public-playlists', { sort }, { preserveState: true, preserveScroll: false });
};
</script>

<template>
    <Head :title="tSafe('playlist.public_playlists', 'Public Playlists')" />

    <AppLayout>
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">{{ tSafe('playlist.public_playlists', 'Public Playlists') }}</h1>
                <p class="mt-1" style="color: var(--color-text-secondary);">{{ tSafe('playlist.browse_all', 'Browse playlists from all creators') }}</p>
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

        <!-- Skeleton Loading -->
        <div v-if="isInitialLoad" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="i in 8" :key="'skeleton-' + i" class="card overflow-hidden">
                <div class="aspect-video skeleton" style="background-color: var(--color-bg-secondary);"></div>
                <div class="p-3 space-y-2">
                    <div class="skeleton skeleton-text w-3/4"></div>
                    <div class="skeleton skeleton-text-sm w-1/2"></div>
                </div>
            </div>
        </div>

        <!-- Playlists Grid -->
        <div v-else-if="playlists?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="playlist in playlists.data"
                :key="playlist.id"
                :href="`/playlist/${playlist.slug}`"
                class="card overflow-hidden hover:ring-2 transition-all"
                style="--tw-ring-color: var(--color-accent);"
            >
                <div class="aspect-video flex items-center justify-center" style="background-color: var(--color-bg-secondary);">
                    <img
                        v-if="playlist.thumbnail"
                        :src="playlist.thumbnail"
                        :alt="playlist.title"
                        class="w-full h-full object-cover"
                        loading="lazy"
                    />
                    <ListVideo v-else class="w-12 h-12" style="color: var(--color-text-muted);" />
                </div>
                <div class="p-3">
                    <h3 class="font-medium truncate" style="color: var(--color-text-primary);">{{ playlist.title }}</h3>
                    <p class="text-sm mt-1" style="color: var(--color-text-secondary);">
                        {{ playlist.videos_count || 0 }} {{ tSafe('common.videos', 'videos') }}
                        <span v-if="playlist.user"> &middot; {{ playlist.user.username }}</span>
                    </p>
                    <p v-if="playlist.favorited_by_count > 0" class="text-xs mt-1 flex items-center gap-1" style="color: var(--color-text-muted);">
                        <Heart class="w-3 h-3" />
                        {{ playlist.favorited_by_count }} {{ playlist.favorited_by_count === 1 ? 'favorite' : 'favorites' }}
                    </p>
                </div>
            </Link>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
            <ListVideo class="w-16 h-16 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">{{ tSafe('playlist.no_public_playlists', 'No playlists yet') }}</p>
            <p class="mt-2" style="color: var(--color-text-muted);">{{ tSafe('playlist.no_public_playlists_desc', 'Be the first to create a playlist!') }}</p>
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
    </AppLayout>
</template>
