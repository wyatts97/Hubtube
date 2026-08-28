<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true,
    },
    lastPage: {
        type: Number,
        required: true,
    },
    /**
     * Optional (page) => href. When supplied, pages render as real Inertia
     * <Link> anchors instead of buttons.
     *
     * The button mode navigates from JS, which leaves no <a href> for a
     * crawler to follow — fine for an in-app list, but a regression on
     * pages that are in a sitemap (channels are). Pass linkFor there.
     */
    linkFor: {
        type: Function,
        default: null,
    },
    /** Inertia partial-reload keys, so paging does not re-send the page shell. */
    only: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['page-change']);

const asLinks = computed(() => typeof props.linkFor === 'function');

const goToPage = (pageNum) => {
    if (pageNum >= 1 && pageNum <= props.lastPage && pageNum !== props.currentPage) {
        emit('page-change', pageNum);
    }
};

const isVisible = (pageNum) =>
    pageNum === 1 ||
    pageNum === props.lastPage ||
    (pageNum >= props.currentPage - 2 && pageNum <= props.currentPage + 2);

const isEllipsis = (pageNum) =>
    pageNum === props.currentPage - 3 || pageNum === props.currentPage + 3;

const stepStyle = {
    backgroundColor: 'var(--color-bg-secondary)',
    color: 'var(--color-text-primary)',
};

const pageStyle = (pageNum) =>
    pageNum === props.currentPage
        ? { backgroundColor: 'var(--color-accent)', color: 'white' }
        : stepStyle;
</script>

<template>
    <nav
        v-if="lastPage > 1"
        class="flex justify-center items-center gap-2 mt-8"
        :aria-label="'Pagination'"
    >
        <!-- Previous -->
        <component
            :is="asLinks && currentPage > 1 ? Link : 'button'"
            v-bind="asLinks && currentPage > 1
                ? { href: linkFor(currentPage - 1), preserveScroll: true, only }
                : { disabled: currentPage === 1 }"
            @click="asLinks ? undefined : goToPage(currentPage - 1)"
            class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 pointer-events-none': asLinks && currentPage === 1 }"
            :style="stepStyle"
            aria-label="Previous page"
        >
            <ChevronLeft class="w-5 h-5" />
        </component>

        <div class="flex items-center gap-1">
            <template v-for="pageNum in lastPage" :key="pageNum">
                <component
                    :is="asLinks ? Link : 'button'"
                    v-if="isVisible(pageNum)"
                    v-bind="asLinks
                        ? { href: linkFor(pageNum), preserveScroll: true, only }
                        : {}"
                    @click="asLinks ? undefined : goToPage(pageNum)"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                    :style="pageStyle(pageNum)"
                    :aria-current="pageNum === currentPage ? 'page' : undefined"
                >
                    {{ pageNum }}
                </component>
                <span v-else-if="isEllipsis(pageNum)" class="text-text-muted">...</span>
            </template>
        </div>

        <!-- Next -->
        <component
            :is="asLinks && currentPage < lastPage ? Link : 'button'"
            v-bind="asLinks && currentPage < lastPage
                ? { href: linkFor(currentPage + 1), preserveScroll: true, only }
                : { disabled: currentPage === lastPage }"
            @click="asLinks ? undefined : goToPage(currentPage + 1)"
            class="p-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 pointer-events-none': asLinks && currentPage === lastPage }"
            :style="stepStyle"
            aria-label="Next page"
        >
            <ChevronRight class="w-5 h-5" />
        </component>
    </nav>
</template>
