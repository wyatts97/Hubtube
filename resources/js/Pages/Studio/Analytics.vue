<script setup>
/**
 * Per-video analytics for the creator who owns it.
 *
 * Everything here comes from rows the site already writes — video_views and
 * watch_history — so the page is careful to say what each number is actually
 * counting: views are de-duplicated per viewer per window, the daily series
 * only reaches back as far as views are retained, and watch time exists for
 * signed-in viewers alone.
 */
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { ArrowLeft, Eye, Users, ThumbsUp, MessageSquare, Clock, CheckCircle2 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { formatViews } from '@/Composables/useFormatters';

defineOptions({ layout: AppLayout });

const { t, locale, localizedUrl } = useI18n();

const props = defineProps({
    video: Object,
    analytics: Object,
    retentionDays: { type: Number, default: 90 },
});

const totals = computed(() => props.analytics?.totals || {});
const engagement = computed(() => props.analytics?.engagement || {});
const series = computed(() => props.analytics?.views_by_day || []);

const peak = computed(() => Math.max(1, ...series.value.map((point) => point.views)));

const seriesTotal = computed(() => series.value.reduce((sum, point) => sum + point.views, 0));

const setRange = (days) => {
    router.get(`/studio/videos/${props.video.id}/analytics`, { days }, {
        preserveState: true,
        preserveScroll: true,
    });
};

/** A date as a short label for the chart's axis. */
const dayLabel = (date) => new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, {
    month: 'short',
    day: 'numeric',
});

/**
 * Country names rather than ISO codes where the browser can supply them.
 * Intl.DisplayNames is unavailable in a few older browsers, hence the fallback.
 */
const countryName = (code) => {
    try {
        return new Intl.DisplayNames([locale.value], { type: 'region' }).of(code) || code;
    } catch {
        return code;
    }
};

const sourceLabel = (source) => {
    if (source === 'direct') return t('studio.source_direct');
    if (source === 'internal') return t('studio.source_internal');
    return source;
};

/** Seconds as "3h 21m" / "4m 05s", for watch-time figures. */
const duration = (seconds) => {
    const total = Math.max(0, Math.round(seconds || 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);

    if (hours > 0) return `${hours}h ${String(minutes).padStart(2, '0')}m`;

    return `${minutes}m ${String(total % 60).padStart(2, '0')}s`;
};

const cards = computed(() => [
    { label: t('studio.views_in_range'), value: formatViews(totals.value.views_in_range), icon: Eye, color: '#8b5cf6' },
    { label: t('studio.unique_viewers'), value: formatViews(totals.value.viewers_in_range), icon: Users, color: '#3b82f6' },
    { label: t('studio.views_lifetime'), value: formatViews(totals.value.views_lifetime), icon: Eye, color: '#22c55e' },
    { label: t('dashboard.total_likes'), value: formatViews(totals.value.likes), icon: ThumbsUp, color: '#ef4444' },
    { label: t('video.comments'), value: formatViews(totals.value.comments), icon: MessageSquare, color: '#f59e0b' },
]);

const rangeDays = computed(() => props.analytics?.range_days || 28);
</script>

<template>
    <SeoHead :title="`${t('studio.analytics')} — ${video.title}`" />

    <div class="max-w-5xl mx-auto">
        <Link href="/studio/videos" class="flex items-center gap-2 mb-6 text-sm hover:opacity-80 text-text-secondary">
            <ArrowLeft class="w-4 h-4" />
            {{ t('studio.back_to_videos') }}
        </Link>

        <!-- Video header -->
        <div class="card p-4 mb-6 flex items-start gap-4">
            <div class="w-32 h-18 rounded-lg overflow-hidden shrink-0 bg-bg-secondary">
                <img
                    v-if="video.thumbnail_url"
                    :src="video.thumbnail_url"
                    :alt="video.title"
                    class="w-full h-full object-cover"
                />
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold truncate text-text-primary">{{ video.title }}</h1>
                <p class="text-sm mt-1 text-text-muted">
                    {{ video.duration }}
                    <span v-if="video.published_at"> · {{ new Date(video.published_at).toLocaleDateString(locale) }}</span>
                </p>
                <Link :href="localizedUrl(`/${video.slug}`)" class="text-sm mt-2 inline-block hover:underline text-accent-text">
                    {{ t('playlist.open_video') }}
                </Link>
            </div>
        </div>

        <!-- Range picker -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button
                v-for="days in analytics.ranges"
                :key="days"
                class="px-3 py-1.5 rounded-full text-sm border transition-colors"
                :class="rangeDays === days ? 'border-accent text-accent-text' : 'border-border text-text-secondary hover:text-text-primary'"
                @click="setRange(days)"
            >
                {{ t('studio.last_days', { count: days, n: days }) }}
            </button>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-4 mb-6">
            <div v-for="card in cards" :key="card.label" class="card p-3 sm:p-4">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: card.color + '15' }">
                        <component :is="card.icon" class="w-4 h-4" :style="{ color: card.color }" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs truncate text-text-muted">{{ card.label }}</p>
                        <p class="text-sm sm:text-lg font-bold truncate text-text-primary">{{ card.value }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Views over time -->
        <div class="card p-4 mb-6">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="font-semibold text-text-primary">{{ t('studio.views_over_time') }}</h2>
                <span class="text-sm text-text-muted">{{ formatViews(seriesTotal) }} {{ t('common.views') }}</span>
            </div>

            <div
                v-if="seriesTotal > 0"
                class="flex items-end gap-0.5 h-40"
                role="img"
                :aria-label="t('studio.chart_label', { total: seriesTotal, days: rangeDays })"
            >
                <div
                    v-for="point in series"
                    :key="point.date"
                    class="flex-1 rounded-t bg-accent min-h-px"
                    :style="{ height: `${Math.max(2, (point.views / peak) * 100)}%`, opacity: point.views ? 1 : 0.25 }"
                    :title="`${dayLabel(point.date)}: ${point.views}`"
                ></div>
            </div>
            <p v-else class="py-10 text-center text-sm text-text-muted">{{ t('studio.no_views_yet') }}</p>

            <div v-if="series.length" class="flex justify-between mt-2 text-xs text-text-muted">
                <span>{{ dayLabel(series[0].date) }}</span>
                <span>{{ dayLabel(series[series.length - 1].date) }}</span>
            </div>

            <!-- Views are counted once per viewer per window, so this is not
                 the same number as a raw hit count. -->
            <p class="mt-3 text-xs text-text-muted">
                {{ t('studio.views_note', { days: retentionDays }) }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Watch time -->
            <div class="card p-4">
                <h2 class="font-semibold mb-4 text-text-primary">{{ t('studio.watch_time') }}</h2>

                <dl v-if="engagement.tracked_viewers" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm flex items-center gap-2 text-text-secondary">
                            <Clock class="w-4 h-4" />{{ t('studio.total_watch_time') }}
                        </dt>
                        <dd class="font-medium text-text-primary">{{ duration(engagement.total_watch_seconds) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-text-secondary">{{ t('studio.average_watch_time') }}</dt>
                        <dd class="font-medium text-text-primary">
                            {{ duration(engagement.average_watch_seconds) }}
                            <span v-if="engagement.average_watched_percent !== null" class="text-sm text-text-muted">
                                ({{ engagement.average_watched_percent }}%)
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm flex items-center gap-2 text-text-secondary">
                            <CheckCircle2 class="w-4 h-4" />{{ t('studio.completion_rate') }}
                        </dt>
                        <dd class="font-medium text-text-primary">
                            {{ engagement.completion_rate === null ? '—' : `${engagement.completion_rate}%` }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-text-secondary">{{ t('studio.tracked_viewers') }}</dt>
                        <dd class="font-medium text-text-primary">{{ formatViews(engagement.tracked_viewers) }}</dd>
                    </div>
                </dl>
                <p v-else class="py-6 text-center text-sm text-text-muted">{{ t('studio.no_watch_time') }}</p>

                <p class="mt-4 text-xs text-text-muted">{{ t('studio.watch_time_note') }}</p>
            </div>

            <div class="space-y-6">
                <!-- Countries -->
                <div class="card p-4">
                    <h2 class="font-semibold mb-3 text-text-primary">{{ t('studio.top_countries') }}</h2>
                    <ul v-if="analytics.countries.length" class="space-y-2">
                        <li v-for="row in analytics.countries" :key="row.country" class="flex items-center justify-between text-sm">
                            <span class="text-text-secondary">{{ countryName(row.country) }}</span>
                            <span class="text-text-primary">{{ formatViews(row.views) }}</span>
                        </li>
                    </ul>
                    <p v-else class="py-3 text-sm text-text-muted">{{ t('studio.no_country_data') }}</p>
                </div>

                <!-- Traffic sources -->
                <div class="card p-4">
                    <h2 class="font-semibold mb-3 text-text-primary">{{ t('studio.traffic_sources') }}</h2>
                    <ul v-if="analytics.sources.length" class="space-y-2">
                        <li v-for="row in analytics.sources" :key="row.source" class="flex items-center justify-between text-sm">
                            <span class="truncate text-text-secondary">{{ sourceLabel(row.source) }}</span>
                            <span class="ps-2 text-text-primary">{{ formatViews(row.views) }}</span>
                        </li>
                    </ul>
                    <p v-else class="py-3 text-sm text-text-muted">{{ t('studio.no_source_data') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
