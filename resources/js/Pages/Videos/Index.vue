<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Search, Filter } from 'lucide-vue-next';

const props = defineProps({
    videos: Object,
    categories: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const category = ref(props.filters?.category || '');

const applyFilters = () => {
    router.get('/videos', {
        search: search.value || undefined,
        category: category.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch([search, category], () => {
    applyFilters();
}, { debounce: 300 });
</script>

<template>
    <Head title="Videos" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white mb-4">Browse Videos</h1>
            
            <div class="flex flex-wrap gap-4">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-dark-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search videos..."
                        class="input pl-10 w-full"
                        @keydown.enter="applyFilters"
                    />
                </div>
                
                <select v-model="category" class="input w-auto" @change="applyFilters">
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.name }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="videos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard
                v-for="video in videos.data"
                :key="video.id"
                :video="video"
            />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400 text-lg">No videos found</p>
            <p class="text-dark-500 mt-2">Try adjusting your search or filters</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm',
                        link.active 
                            ? 'bg-primary-600 text-white' 
                            : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    class="px-4 py-2 rounded-lg text-sm bg-dark-900 text-dark-500"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
