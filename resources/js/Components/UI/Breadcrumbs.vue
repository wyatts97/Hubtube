<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Home } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Breadcrumbs — accepts an array of `{ label, href? }` objects.
 * The last item is rendered as the current page (not a link).
 *
 * Usage:
 *   <Breadcrumbs :items="[
 *       { label: 'Categories', href: '/categories' },
 *       { label: category.name },
 *   ]" />
 *
 * A `Home` link is always rendered as the first crumb.
 */
const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    homeHref: {
        type: String,
        default: '/',
    },
});

const crumbs = computed(() => props.items || []);
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-4 text-sm">
        <ol class="flex items-center gap-1.5 flex-wrap text-text-muted">
            <li>
                <Link
                    :href="homeHref"
                    class="inline-flex items-center gap-1 hover:text-text-primary transition-colors"
                    aria-label="Home"
                >
                    <Home class="w-3.5 h-3.5" />
                </Link>
            </li>
            <template v-for="(item, idx) in crumbs" :key="idx">
                <li aria-hidden="true" class="inline-flex items-center">
                    <ChevronRight class="w-3.5 h-3.5" />
                </li>
                <li>
                    <Link
                        v-if="item.href && idx !== crumbs.length - 1"
                        :href="item.href"
                        class="hover:text-text-primary transition-colors"
                    >
                        {{ item.label }}
                    </Link>
                    <span
                        v-else
                        class="text-text-secondary font-medium"
                        aria-current="page"
                    >
                        {{ item.label }}
                    </span>
                </li>
            </template>
        </ol>
    </nav>
</template>
