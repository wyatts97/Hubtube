<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { AlertTriangle, RefreshCw, Home, Search, TrendingUp } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

defineOptions({ layout: AppLayout });

const { t, localizedUrl } = useI18n();

const props = defineProps({
    status: Number,
    message: String,
});

/**
 * Copy for the status, translated.
 *
 * These were hardcoded English, which the site's other pages are not. The
 * error page is also rendered from the exception handler, outside the
 * middleware that shares the translation catalogue — so a missing key here
 * used to surface as a raw dot-path. The handler now shares the catalogue,
 * and t() falls back to English anyway if it could not.
 */
const KNOWN_STATUSES = [403, 404, 419, 451, 500, 503];

const suffix = computed(() => (KNOWN_STATUSES.includes(props.status) ? props.status : 'generic'));

const title = computed(() => t(`errors.title_${suffix.value}`));

// A message the application set deliberately (an abort() reason) wins over the
// generic copy; the handler drops anything that looks internal before it gets
// this far.
const description = computed(() => props.message || t(`errors.body_${suffix.value}`));

// A missing page offers a way on instead of redirecting after a countdown,
// which took the visitor away before they could read why.
const is404 = computed(() => props.status === 404);

const refresh = () => {
    window.location.reload();
};
</script>

<template>
    <SeoHead :title="title" />

    <div class="flex items-center justify-center py-20 px-4">
        <div class="w-full max-w-md text-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style="background-color: color-mix(in srgb, var(--color-accent) 20%, transparent);">
                <AlertTriangle class="w-10 h-10 text-accent-text" />
            </div>

            <h1 class="text-5xl font-bold mb-2 text-text-primary">{{ status }}</h1>
            <h2 class="text-xl font-semibold mb-4 text-text-primary">{{ title }}</h2>
            <p class="mb-6 text-text-secondary">{{ description }}</p>

            <form v-if="is404" :action="localizedUrl('/search')" method="get" role="search" class="relative mb-6">
                <input name="q" type="search" class="input pe-12" :placeholder="t('common.search_placeholder')" :aria-label="t('common.search')" />
                <button type="submit" class="absolute end-2 top-1/2 -translate-y-1/2 p-2 text-text-muted" :aria-label="t('common.search')">
                    <Search class="w-5 h-5" />
                </button>
            </form>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="/" class="btn btn-primary inline-flex items-center gap-2">
                    <Home class="w-4 h-4" />
                    {{ t('errors.go_home') }}
                </a>
                <a v-if="is404" :href="localizedUrl('/trending')" class="btn btn-secondary inline-flex items-center gap-2">
                    <TrendingUp class="w-4 h-4" />
                    {{ t('nav.trending') }}
                </a>
                <button v-else @click="refresh" class="btn btn-secondary inline-flex items-center gap-2">
                    <RefreshCw class="w-4 h-4" />
                    {{ t('errors.refresh') }}
                </button>
            </div>
        </div>
    </div>
</template>
