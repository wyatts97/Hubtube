<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Film, Filter, Search } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    videos: Object,
    filters: Object,
});

const searchQuery = ref(props.filters?.q || '');
const selectedSite = ref(props.filters?.site || '');

const sites = [
    { value: '', label: 'All Sites' },
    { value: 'xvideos', label: 'XVideos' },
    { value: 'pornhub', label: 'PornHub' },
    { value: 'xhamster', label: 'xHamster' },
    { value: 'xnxx', label: 'XNXX' },
    { value: 'redtube', label: 'RedTube' },
    { value: 'youporn', label: 'YouPorn' },
];

const applyFilters = () => {
    router.get('/embedded', {
        q: searchQuery.value || undefined,
        site: selectedSite.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedSite.value = '';
    router.get('/embedded');
};
</script>

<template>
    <AppLayout>
        <Head title="Embedded Videos" />

        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <Film class="w-8 h-8" style="color: var(--color-accent);" />
                    <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">
                        {{ t('embedded.title') || 'Embedded Videos' }}
                    </h1>
                </div>
            </div>

            <!-- Filters -->
            <div class="card p-4 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5" style="color: var(--color-text-muted);" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search videos..."
                                class="w-full pl-10 pr-4 py-2 rounded-lg border"
                                style="background-color: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    
                    <select
                        v-model="selectedSite"
                        class="px-4 py-2 rounded-lg border"
                        style="background-color: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);"
                        @change="applyFilters"
                    >
                        <option v-for="site in sites" :key="site.value" :value="site.value">
                            {{ site.label }}
                        </option>
                    </select>
                    
                    <button
                        @click="applyFilters"
                        class="btn btn-primary px-6 py-2"
                    >
                        <Filter class="w-4 h-4 mr-2" />
                        {{ t('embedded.filter') || 'Filter' }}
                    </button>
                    
                    <button
                        v-if="filters?.q || filters?.site"
                        @click="clearFilters"
                        class="btn btn-secondary px-4 py-2"
                    >
                        {{ t('common.clear') || 'Clear' }}
                    </button>
                </div>
            </div>

            <!-- Video Grid -->
            <div v-if="videos.data?.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <Link
                    v-for="video in videos.data"
                    :key="video.id"
                    :href="`/embedded/${video.id}`"
                    class="group"
                >
                    <div class="card overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="relative aspect-video bg-gray-800">
                            <img
                                v-if="video.thumbnail_url"
                                :src="video.thumbnail_url"
                                :alt="video.title"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <Film class="w-12 h-12 text-gray-600" />
                            </div>
                            
                            <!-- Duration -->
                            <div v-if="video.duration_formatted" class="absolute bottom-2 right-2 bg-black/80 text-white text-xs px-1.5 py-0.5 rounded">
                                {{ video.duration_formatted }}
                            </div>
                            
                            <!-- Source badge -->
                            <div class="absolute top-2 left-2 bg-black/60 text-white text-xs px-1.5 py-0.5 rounded uppercase">
                                {{ video.source_site }}
                            </div>
                        </div>
                        
                        <div class="p-3">
                            <h3 class="text-sm font-medium line-clamp-2" style="color: var(--color-text-primary);">
                                {{ video.title }}
                            </h3>
                            <div class="mt-1 text-xs" style="color: var(--color-text-muted);">
                                {{ video.formatted_views || video.views_count?.toLocaleString() }} views
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-16">
                <Film class="w-16 h-16 mx-auto mb-4 opacity-50" style="color: var(--color-text-muted);" />
                <p style="color: var(--color-text-muted);">{{ t('common.no_results') || 'No embedded videos found.' }}</p>
            </div>

            <!-- Pagination -->
            <div v-if="videos.links?.length > 3" class="flex justify-center gap-2 mt-8">
                <template v-for="link in videos.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="px-4 py-2 rounded-lg text-sm"
                        :class="link.active ? 'btn-primary' : 'btn-secondary'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="px-4 py-2 rounded-lg text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
