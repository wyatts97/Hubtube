<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ShortsViewer from '@/Components/ShortsViewer.vue';
import { ArrowLeft } from 'lucide-vue-next';

const props = defineProps({
    shorts: Object,
    adSettings: {
        type: Object,
        default: () => ({ enabled: false, frequency: 3, skipDelay: 5, code: '' }),
    },
});

const shortsList = computed(() => props.shorts?.data || []);
const nextPageUrl = computed(() => props.shorts?.next_page_url || null);

const goBack = () => {
    router.visit('/');
};
</script>

<template>
    <Head title="Shorts" />

    <!-- Back button overlay -->
    <button
        @click="goBack"
        class="fixed top-4 left-4 z-50 p-2 rounded-full backdrop-blur-sm transition-opacity hover:opacity-80"
        style="background: rgba(255,255,255,0.1); color: #fff;"
    >
        <ArrowLeft class="w-6 h-6" />
    </button>

    <ShortsViewer
        v-if="shortsList.length"
        :shorts="shortsList"
        :ad-settings="adSettings"
        :next-page-url="nextPageUrl"
    />

    <!-- Empty state (no shorts) -->
    <div v-else class="min-h-screen flex flex-col items-center justify-center" style="background-color: var(--color-bg-primary);">
        <p class="text-lg font-medium" style="color: var(--color-text-primary);">No shorts yet</p>
        <p class="text-sm mt-1 mb-4" style="color: var(--color-text-muted);">Be the first to upload a short video!</p>
        <Link href="/upload?type=short" class="btn btn-primary">Upload Short</Link>
    </div>
</template>
