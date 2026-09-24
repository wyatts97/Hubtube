<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { History, Trash2 } from 'lucide-vue-next';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { useToast } from '@/Composables/useToast';
import SeoHead from '@/Components/SeoHead.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import { useGridAds } from '@/Composables/useGridAds';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const toast = useToast();

const props = defineProps({
    videos: Object,
    sponsoredCards: { type: Array, default: () => [] },
    sponsoredFrequency: { type: Number, default: 8 },
});

const { getSponsoredCard, sponsoredCellClass } = useGridAds(props);


const { del } = useFetch();

const confirmingClear = ref(false);

const clearHistory = async () => {
    const { ok, data } = await del('/history', null);
    if (ok) {
        router.reload();
    } else {
        toast.error(data?.message || t('common.error'));
    }
};
</script>

<template>
    <SeoHead :title="t('history.title')" />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="page-title">{{ t('history.title') }}</h1>
            <p class="mt-1 text-text-secondary">{{ t('history.description') }}</p>
        </div>
        <button v-if="videos?.data?.length" @click="confirmingClear = true" class="btn btn-ghost text-red-400 gap-2">
            <Trash2 class="w-4 h-4" />
            {{ t('history.clear') }}
        </button>
    </div>

    <div v-if="videos?.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <template v-for="(video, index) in videos.data" :key="video.id">
            <VideoCard :video="video" />
            <SponsoredVideoCard v-if="getSponsoredCard(index)" :card="getSponsoredCard(index)" :class="sponsoredCellClass(getSponsoredCard(index))" />
        </template>
    </div>

    <div v-else class="text-center py-12">
        <History class="w-16 h-16 mx-auto mb-4 text-text-muted" />
        <p class="text-lg text-text-secondary">{{ t('history.empty') }}</p>
        <p class="mt-2 text-text-muted">{{ t('history.empty_desc') }}</p>
        <Link href="/" class="btn btn-primary mt-4">
            {{ t('common.browse_videos') }}
        </Link>
    </div>

    <!-- Pagination -->
    <div v-if="videos?.links?.length > 3" class="mt-8 flex justify-center gap-2">
        <template v-for="link in videos.links" :key="link.label">
            <a
                v-if="link.url"
                :href="link.url"
                class="px-4 py-2 rounded-lg text-sm"
                :style="link.active 
                    ? { backgroundColor: 'var(--color-accent)', color: 'white' } 
                    : { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }"
                v-html="link.label"
            />
        </template>
    </div>

    <ConfirmDialog
        v-model="confirmingClear"
        :title="t('history.clear')"
        :message="t('history.clear_confirm')"
        :confirm-label="t('history.clear')"
        @confirm="clearHistory"
    />
</template>
