<script setup>
/**
 * Adapts a Laravel paginator to Pagination.vue's link mode.
 *
 * The Channel pages previously each inlined their own `videos.links` loop
 * (three slightly different copies), and two paginators on the Playlists tab
 * were never rendered at all — so a channel's videos past #24 were simply
 * unreachable.
 *
 * Uses real <a href> anchors rather than JS navigation because channel URLs
 * are published in sitemap-channels.xml and need to stay crawlable, and
 * partial reloads so paging doesn't re-send the channel header and sidebar.
 */
import { computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    /** A Laravel length-aware paginator payload. */
    paginator: { type: Object, default: null },
    /** Query param this paginator uses — Playlists has two on one page. */
    pageName: { type: String, default: 'page' },
    /** Query params to carry across, e.g. the Playlists sub-tab. */
    extraQuery: { type: Object, default: () => ({}) },
    /** Inertia partial-reload keys. */
    only: { type: Array, default: () => [] },
});

const currentPage = computed(() => props.paginator?.current_page ?? 1);
const lastPage = computed(() => props.paginator?.last_page ?? 1);

const linkFor = (pageNum) => {
    // Build off the live URL so every unrelated query param — the other
    // paginator's page, filters, the sub-tab — survives paging.
    const query = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    );

    Object.entries(props.extraQuery).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            query.delete(key);
        } else {
            query.set(key, value);
        }
    });

    if (pageNum <= 1) {
        query.delete(props.pageName);
    } else {
        query.set(props.pageName, pageNum);
    }

    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const qs = query.toString();

    return qs ? `${path}?${qs}` : path;
};
</script>

<template>
    <Pagination
        v-if="paginator"
        :current-page="currentPage"
        :last-page="lastPage"
        :link-for="linkFor"
        :only="only"
    />
</template>
