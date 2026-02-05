<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Search as SearchIcon, Users, Hash, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    query: String,
    type: String,
    results: Object,
});

const searchQuery = ref(props.query || '');
const activeType = ref(props.type || 'videos');

const tabs = [
    { key: 'videos', label: 'Videos', icon: SearchIcon },
    { key: 'channels', label: 'Channels', icon: Users },
    { key: 'hashtags', label: 'Hashtags', icon: Hash },
];

const switchTab = (type) => {
    activeType.value = type;
    router.get('/search', { q: searchQuery.value, type }, { preserveState: true });
};

const submitSearch = () => {
    if (searchQuery.value.trim()) {
        router.get('/search', { q: searchQuery.value, type: activeType.value }, { preserveState: true });
    }
};

const goToPage = (pageNum) => {
    router.get('/search', { q: searchQuery.value, type: activeType.value, page: pageNum }, { preserveState: true, preserveScroll: false });
};

const resultsList = () => {
    if (!props.results) return [];
    return props.results.data || props.results || [];
};

const hasPages = () => {
    return props.results?.last_page && props.results.last_page > 1;
};
</script>

<template>
    <Head :title="query ? `Search: ${query}` : 'Search'" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">Search</h1>
        </div>

        <!-- Search Bar -->
        <form @submit.prevent="submitSearch" class="mb-6">
            <div class="relative max-w-2xl">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search videos, channels, hashtags..."
                    class="input pr-12"
                />
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full hover:opacity-80" style="color: var(--color-text-muted);">
                    <SearchIcon class="w-5 h-5" />
                </button>
            </div>
        </form>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 border-b" style="border-color: var(--color-border);">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="switchTab(tab.key)"
                class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors -mb-px"
                :style="activeType === tab.key
                    ? { borderColor: 'var(--color-accent)', color: 'var(--color-accent)' }
                    : { borderColor: 'transparent', color: 'var(--color-text-secondary)' }"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Results -->
        <div v-if="query">
            <p class="text-sm mb-4" style="color: var(--color-text-secondary);">
                Results for "<span class="font-medium" style="color: var(--color-text-primary);">{{ query }}</span>"
            </p>

            <!-- Video Results -->
            <template v-if="activeType === 'videos'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <VideoCard v-for="video in resultsList()" :key="video.id" :video="video" />
                </div>
                <div v-else class="text-center py-12">
                    <SearchIcon class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-lg" style="color: var(--color-text-secondary);">No videos found</p>
                    <p class="mt-1" style="color: var(--color-text-muted);">Try different keywords</p>
                </div>
            </template>

            <!-- Channel Results -->
            <template v-else-if="activeType === 'channels'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a
                        v-for="channel in resultsList()"
                        :key="channel.id"
                        :href="`/channel/${channel.username}`"
                        class="card p-4 flex items-center gap-4 hover:opacity-90 transition-opacity"
                    >
                        <div class="w-14 h-14 rounded-full overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-secondary);">
                            <img v-if="channel.avatar" :src="channel.avatar" :alt="channel.username" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-xl font-bold" style="color: var(--color-accent);">
                                {{ channel.username?.charAt(0)?.toUpperCase() || '?' }}
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-medium truncate" style="color: var(--color-text-primary);">
                                {{ channel.username }}
                                <span v-if="channel.is_verified" class="ml-1">✓</span>
                            </h3>
                            <p class="text-sm" style="color: var(--color-text-secondary);">
                                {{ channel.channel?.name || channel.username }}
                            </p>
                            <p class="text-sm" style="color: var(--color-text-muted);">
                                {{ channel.subscriber_count || 0 }} subscribers
                            </p>
                        </div>
                    </a>
                </div>
                <div v-else class="text-center py-12">
                    <Users class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-lg" style="color: var(--color-text-secondary);">No channels found</p>
                </div>
            </template>

            <!-- Hashtag Results -->
            <template v-else-if="activeType === 'hashtags'">
                <div v-if="resultsList().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="hashtag in resultsList()"
                        :key="hashtag.id"
                        class="card p-4"
                    >
                        <h3 class="font-medium" style="color: var(--color-accent);">#{{ hashtag.name }}</h3>
                        <p class="text-sm mt-1" style="color: var(--color-text-muted);">{{ hashtag.usage_count || 0 }} videos</p>
                    </div>
                </div>
                <div v-else class="text-center py-12">
                    <Hash class="w-12 h-12 mx-auto mb-4" style="color: var(--color-text-muted);" />
                    <p class="text-lg" style="color: var(--color-text-secondary);">No hashtags found</p>
                </div>
            </template>

            <!-- Pagination -->
            <div v-if="hasPages()" class="flex justify-center items-center gap-2 mt-8">
                <button
                    @click="goToPage(results.current_page - 1)"
                    :disabled="results.current_page === 1"
                    class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                >
                    <ChevronLeft class="w-5 h-5" />
                </button>
                <div class="flex items-center gap-1">
                    <template v-for="pageNum in results.last_page" :key="pageNum">
                        <button
                            v-if="pageNum === 1 || pageNum === results.last_page || (pageNum >= results.current_page - 2 && pageNum <= results.current_page + 2)"
                            @click="goToPage(pageNum)"
                            class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                            :style="pageNum === results.current_page
                                ? { backgroundColor: 'var(--color-accent)', color: 'white' }
                                : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                        >
                            {{ pageNum }}
                        </button>
                        <span v-else-if="pageNum === results.current_page - 3 || pageNum === results.current_page + 3" style="color: var(--color-text-muted);">...</span>
                    </template>
                </div>
                <button
                    @click="goToPage(results.current_page + 1)"
                    :disabled="results.current_page === results.last_page"
                    class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :style="{ backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                >
                    <ChevronRight class="w-5 h-5" />
                </button>
            </div>
        </div>

        <!-- No Query State -->
        <div v-else class="text-center py-16">
            <SearchIcon class="w-16 h-16 mx-auto mb-4" style="color: var(--color-text-muted);" />
            <p class="text-lg" style="color: var(--color-text-secondary);">Search for videos, channels, and more</p>
        </div>
    </AppLayout>
</template>
