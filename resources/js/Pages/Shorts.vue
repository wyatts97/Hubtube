<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';

defineProps({
    shorts: Object,
});
</script>

<template>
    <Head title="Shorts" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Shorts</h1>
            <p class="text-dark-400 mt-1">Quick, vertical videos under 60 seconds</p>
        </div>

        <div v-if="shorts.data?.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <VideoCard v-for="short in shorts.data" :key="short.id" :video="short" />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400 text-lg">No shorts yet</p>
            <p class="text-dark-500 mt-2">Be the first to upload a short video!</p>
        </div>

        <!-- Pagination -->
        <div v-if="shorts.links?.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in shorts.links" :key="link.label">
                <a
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm',
                        link.active 
                            ? 'bg-primary-600 text-white' 
                            : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                    ]"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
