<script setup>
import { Link, router } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import { computed } from 'vue';
import ShortsViewer from '@/Components/ShortsViewer.vue';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    shorts: Object,
    adSettings: {
        type: Object,
        default: () => ({ enabled: false, frequency: 3, skipDelay: 5, code: '' }),
    },
    seo: { type: Object, default: () => ({}) },
});

const shortsList = computed(() => props.shorts?.data || []);
const nextPageUrl = computed(() => props.shorts?.next_page_url || null);

const goBack = () => {
    router.visit('/');
};
</script>

<template>
    <SeoHead :seo="seo" />

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
        <p class="text-lg font-medium" style="color: var(--color-text-primary);">{{ t('channel.no_shorts') || 'No shorts yet' }}</p>
        <p class="text-sm mt-1 mb-4" style="color: var(--color-text-muted);">{{ t('channel.no_shorts_desc') || 'Be the first to upload a short video!' }}</p>
        <Link href="/upload?type=short" class="btn btn-primary">{{ t('nav.upload_short') || 'Upload Short' }}</Link>
    </div>
</template>
