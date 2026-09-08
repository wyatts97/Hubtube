<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { Eye, ImageIcon, Clock, Flame, Plus } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import GridAdSlot from '@/Components/GridAdSlot.vue';
import SponsoredVideoCard from '@/Components/SponsoredVideoCard.vue';
import { useGridAds } from '@/Composables/useGridAds';

const { t } = useI18n();

const props = defineProps({
    galleries: Object,
    filters: Object,
    seo: Object,
    adSettings: { type: Object, default: () => ({}) },
    sponsoredCards: { type: Array, default: () => [] },
});

// Gallery covers are 16:9, so the video-shaped ad units sit correctly here.
const { gridAds, shouldShowAd, getSponsoredCard, adCellClass } = useGridAds(props);

const sort = ref(props.filters?.sort || '');

const setSort = (val) => {
    sort.value = val;
    router.get('/galleries', {
        sort: val || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatViews = (count) => {
    if (!count) return '0';
    if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
};
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="mb-5">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h1 class="page-title">Galleries</h1>

                <div class="flex items-center gap-2">
                    <!-- Sort Buttons -->
                    <div class="flex items-center gap-1">
                        <button
                            @click="setSort('')"
                            class="chip"
                            :class="{ 'chip-active': !sort }"
                        >
                            <Clock class="w-3.5 h-3.5 inline -mt-0.5 me-1" />Latest
                        </button>
                        <button
                            @click="setSort('popular')"
                            class="chip"
                            :class="{ 'chip-active': sort === 'popular' }"
                        >
                            <Flame class="w-3.5 h-3.5 inline -mt-0.5 me-1" />Popular
                        </button>
                    </div>

                    <Link href="/galleries/create" class="btn btn-primary flex items-center gap-1.5 text-sm">
                        <Plus class="w-4 h-4" />
                        {{ t('gallery.create') }}
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="galleries.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <template v-for="(gallery, index) in galleries.data" :key="gallery.id">
            <Link
                :href="`/gallery/${gallery.slug}`"
                class="group block"
            >
                <div class="card rounded-xl overflow-hidden">
                    <!-- Cover Image -->
                    <div class="aspect-video relative bg-bg-secondary">
                        <img
                            v-if="gallery.cover_url"
                            :src="gallery.cover_url"
                            :alt="gallery.title"
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                            loading="lazy"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <ImageIcon class="w-10 h-10 text-text-muted" />
                        </div>
                        <div class="absolute bottom-2 end-2 flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-black/70 text-white">
                            <ImageIcon class="w-3 h-3" />
                            {{ gallery.images_count || 0 }}
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="p-3">
                        <h3 class="font-medium text-sm line-clamp-1 text-text-primary">{{ gallery.title }}</h3>
                        <div class="flex items-center gap-3 mt-1">
                            <span v-if="gallery.user" class="text-xs text-text-muted">{{ gallery.user.username }}</span>
                            <span class="text-xs flex items-center gap-1 text-text-muted">
                                <Eye class="w-3 h-3" />
                                {{ formatViews(gallery.views_count) }}
                            </span>
                        </div>
                    </div>
                </div>
            </Link>
            <div v-if="shouldShowAd(index, galleries.data.length)" class="rounded-xl p-2" :class="adCellClass">
                <GridAdSlot :ads="gridAds" />
            </div>
            <SponsoredVideoCard v-if="getSponsoredCard(index)" :card="getSponsoredCard(index)" />
            </template>
        </div>

        <div v-else class="text-center py-16">
            <ImageIcon class="w-12 h-12 mx-auto mb-3 text-text-muted" />
            <p class="text-lg text-text-secondary">{{ t('gallery.none_yet') }}</p>
            <p class="mt-2 text-sm text-text-muted">{{ t('gallery.be_first') }}</p>
            <Link href="/galleries/create" class="btn btn-primary mt-4 inline-flex items-center gap-1.5">
                <Plus class="w-4 h-4" />
                Create Gallery
            </Link>
        </div>

        <!-- Pagination -->
        <div v-if="galleries.links && galleries.links.length > 3" class="mt-8 flex justify-center gap-1.5">
            <template v-for="link in galleries.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="['px-3 py-1.5 rounded-lg text-sm transition-colors']"
                    :style="link.active
                        ? 'background-color: var(--color-accent); color: #fff;'
                        : 'background-color: var(--color-bg-secondary); color: var(--color-text-secondary); border: 1px solid var(--color-border);'"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    class="px-3 py-1.5 rounded-lg text-sm text-text-muted"
                    v-html="link.label"
                />
            </template>
        </div>
    </AppLayout>
</template>
