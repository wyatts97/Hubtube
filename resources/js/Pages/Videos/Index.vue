<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import FilterRail from '@/Components/UI/FilterRail.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import GridAdSlot from '@/Components/GridAdSlot.vue';
import OutstreamAd from '@/Components/OutstreamAd.vue';
import BannerAd from '@/Components/UI/BannerAd.vue';
import { useAutoTranslate } from '@/Composables/useAutoTranslate';
import { useI18n } from '@/Composables/useI18n';
import { useVideoGrid } from '@/Composables/useVideoGrid';
import SeoHead from '@/Components/SeoHead.vue';

const { t } = useI18n();

const { translateVideos, tr } = useAutoTranslate(['title']);
const { gridClass, mobileGrid } = useVideoGrid();

const props = defineProps({
    videos: Object,
    categories: Array,
    filters: Object,
    bannerAd: { type: Object, default: () => ({}) },
    adSettings: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
    outstreamAds: { type: Array, default: () => [] },
});

/**
 * Filter state mirrors the query string exactly, so a filtered view is
 * shareable and the back button restores it. FilterRail is presentational —
 * it emits a new value and this decides how to navigate.
 */
const activeFilters = ref({
    sort: props.filters?.sort || '',
    duration: props.filters?.duration || '',
    quality: props.filters?.quality || '',
    date: props.filters?.date || '',
    category: props.filters?.category ? String(props.filters.category) : '',
});

// Server-side state wins after a navigation (including back/forward), so the
// rail can never show a filter the results don't reflect.
watch(() => props.filters, (next) => {
    activeFilters.value = {
        sort: next?.sort || '',
        duration: next?.duration || '',
        quality: next?.quality || '',
        date: next?.date || '',
        category: next?.category ? String(next.category) : '',
    };
}, { deep: true });

/** Sort is always set, so it doesn't count as a filter for the empty state. */
const hasActiveFilters = computed(() =>
    ['duration', 'quality', 'date', 'category'].some((key) => activeFilters.value[key] !== '')
);

const applyFilters = (next) => {
    activeFilters.value = next;

    // Empty values are dropped rather than sent as blanks, keeping shared URLs
    // clean and the server's `only()` payload minimal.
    const params = Object.fromEntries(
        Object.entries(next).filter(([, value]) => value !== '' && value != null)
    );

    router.get('/videos', params, { preserveState: true, preserveScroll: true });
};

onMounted(() => {
    const allVideos = props.videos?.data || [];
    if (allVideos.length) translateVideos(allVideos);
});

const withTranslation = (video) => {
    const title = tr(video, 'title');
    const translatedSlug = tr(video, 'translated_slug');
    if (title !== video.title || translatedSlug) {
        const override = { ...video, title };
        if (translatedSlug && translatedSlug !== video.slug) {
            override.translated_slug = translatedSlug;
        }
        return override;
    }
    return video;
};

const adsEnabled = computed(() => {
    const enabled = props.adSettings?.videoGridEnabled;
    return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
});
const gridAds = computed(() => props.adSettings?.videoGridAds || []);
const adFrequency = computed(() => parseInt(props.adSettings?.videoGridFrequency) || 8);

const shouldShowAd = (index, totalLength) => {
    if (!adsEnabled.value || !gridAds.value.length) return false;
    return (index + 1) % adFrequency.value === 0 && index < totalLength - 1;
};

// Sponsored cards: insert at frequency intervals, cycling through available cards
// Offset by half the grid ad frequency so they interleave instead of stacking
const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);
const sponsoredOffset = computed(() => Math.floor(adFrequency.value / 2));
const getSponsoredCard = (index) => {
    if (!props.sponsoredCards?.length) return null;
    if ((index + 1 + sponsoredOffset.value) % sponsoredFrequency.value !== 0) return null;
    const cardIndex = Math.floor((index + 1 + sponsoredOffset.value) / sponsoredFrequency.value) - 1;
    return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
};

// Outstream ads: interleaved at configured frequency
const outstreamFrequency = computed(() => parseInt(props.adSettings?.outstreamFrequency) || 6);
const getOutstreamAd = (index) => {
    if (!props.outstreamAds?.length) return null;
    // Offset by 3 so outstream and sponsored cards don't land on the same index
    if ((index + 4) % outstreamFrequency.value !== 0) return null;
    const adIndex = Math.floor((index + 4) / outstreamFrequency.value) - 1;
    return props.outstreamAds[adIndex % props.outstreamAds.length] || null;
};
</script>

<template>
    <SeoHead />

    <AppLayout>
        <!-- Top Ad Banner -->
        <BannerAd :config="bannerAd" />

        <div class="mb-4">
            <div class="flex items-baseline justify-between gap-3 mb-3">
                <h1 class="page-title">{{ t('common.browse_videos') }}</h1>
                <span class="text-xs tabular-nums text-text-muted">
                    {{ videos.total?.toLocaleString?.() ?? videos.data.length }}
                </span>
            </div>

            <FilterRail
                :model-value="activeFilters"
                :categories="categories || []"
                @update:model-value="applyFilters"
            />
        </div>

        <div v-if="videos.data.length" :class="gridClass">
            <template v-for="(video, index) in videos.data" :key="video.id">
                <VideoCard :video="withTranslation(video)" />
                <div
                    v-if="shouldShowAd(index, videos.data.length)"
                    class="p-1"
                    :class="mobileGrid === 2 ? 'col-span-2 sm:col-span-1' : 'col-span-1'"
                >
                    <GridAdSlot :ads="gridAds" />
                </div>
                <SponsoredVideoCard
                    v-if="getSponsoredCard(index)"
                    :card="getSponsoredCard(index)"
                />
                <OutstreamAd
                    v-if="getOutstreamAd(index)"
                    :ad="getOutstreamAd(index)"
                />
            </template>
        </div>

        <EmptyState
            v-else
            :title="hasActiveFilters ? t('filters.no_results_title') : t('common.no_videos_found')"
            :description="hasActiveFilters ? t('filters.no_results_body') : t('common.try_different')"
        />

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center gap-1.5">
            <template v-for="link in videos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="chip"
                    :class="{ 'chip-active': link.active }"
                    v-html="link.label"
                    preserve-scroll
                />
                <span v-else class="chip opacity-50" v-html="link.label" />
            </template>
        </div>
    </AppLayout>
</template>
