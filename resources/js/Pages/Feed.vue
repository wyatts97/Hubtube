<script setup>
/**
 * The subscription activity feed.
 *
 * Was a flat grid of subscribed channels' videos inside a fixed-height
 * virtualised scroller — which meant the feed scrolled independently of the
 * page and the browser's own scroll position was useless. It now reads as a
 * normal page: activity entries grouped per creator, loaded on a keyset
 * cursor so items don't shuffle as new videos publish mid-scroll.
 */
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ListVideo, Loader2, Rss } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import VideoCard from '@/Components/VideoCard.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ProBadge from '@/Components/ProBadge.vue';
import { useFetch } from '@/Composables/useFetch';
import { timeAgo } from '@/Composables/useFormatters';
import { useI18n } from '@/Composables/useI18n';
import { useVideoGrid } from '@/Composables/useVideoGrid';

const props = defineProps({
    activity: { type: Array, default: () => [] },
    nextCursor: { type: String, default: null },
    hasSubscriptions: { type: Boolean, default: false },
});

const { t, locale, localizedUrl } = useI18n();
const { gridClass } = useVideoGrid();
const { get } = useFetch();

const entries = ref([...props.activity]);
const cursor = ref(props.nextCursor);
const loading = ref(false);

const sentinel = ref(null);
let observer = null;

const loadMore = async () => {
    if (loading.value || !cursor.value) return;

    loading.value = true;
    const { ok, data } = await get(`/api/feed?cursor=${encodeURIComponent(cursor.value)}`);

    if (ok && data) {
        entries.value.push(...(data.activity ?? []));
        cursor.value = data.nextCursor ?? null;
    } else {
        // Drop the cursor so a failed request doesn't retry forever on scroll.
        cursor.value = null;
    }

    loading.value = false;
};

onMounted(() => {
    if (!sentinel.value || typeof IntersectionObserver === 'undefined') return;

    observer = new IntersectionObserver(
        ([entry]) => entry.isIntersecting && loadMore(),
        { rootMargin: '400px' },
    );
    observer.observe(sentinel.value);
});

onBeforeUnmount(() => observer?.disconnect());

const isEmpty = computed(() => entries.value.length === 0);
</script>

<template>
    <SeoHead :title="t('feed.title')" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-text-primary">{{ t('feed.title') }}</h1>
            <p class="mt-1 text-text-secondary">{{ t('feed.description') }}</p>
        </div>

        <div v-if="!isEmpty" class="space-y-8">
            <section v-for="(entry, index) in entries" :key="`${entry.type}-${index}`">
                <!-- Actor line -->
                <div class="flex items-center gap-3 mb-3">
                    <Link :href="localizedUrl(`/channel/${entry.actor.username}`)" class="w-9 h-9 avatar shrink-0">
                        <img
                            :src="entry.actor.avatar_url || '/images/default_avatar.webp'"
                            :alt="entry.actor.username"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        />
                    </Link>
                    <div class="min-w-0">
                        <p class="text-sm text-text-secondary">
                            <Link
                                :href="localizedUrl(`/channel/${entry.actor.username}`)"
                                class="font-medium text-text-primary hover:underline"
                            >{{ entry.actor.username }}</Link>
                            <span v-if="entry.actor.is_verified" class="text-accent ms-1">&#10003;</span>
                            <span class="ms-1">
                                {{
                                    entry.type === 'video'
                                        ? t('feed.posted_videos', { count: entry.videos.length })
                                        : t('feed.created_playlist')
                                }}
                            </span>
                        </p>
                        <p class="text-xs text-text-muted">{{ timeAgo(entry.occurred_at, locale) }}</p>
                    </div>
                </div>

                <div v-if="entry.type === 'video'" :class="gridClass">
                    <VideoCard v-for="video in entry.videos" :key="video.id" :video="video" />
                </div>

                <Link
                    v-else
                    :href="localizedUrl(`/playlist/${entry.subject.slug}`)"
                    class="card flex items-center gap-4 p-4 hover:ring-2 transition-all"
                    style="--tw-ring-color: var(--color-accent);"
                >
                    <div class="flex h-16 w-28 shrink-0 items-center justify-center rounded-lg bg-bg-secondary">
                        <ListVideo class="w-7 h-7 text-text-muted" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="truncate font-medium text-text-primary">{{ entry.subject.title }}</h3>
                        <p class="text-sm text-text-muted">
                            {{ entry.subject.videos_count }} {{ t('common.videos') }}
                        </p>
                    </div>
                </Link>
            </section>

            <div ref="sentinel" class="h-px" aria-hidden="true"></div>

            <div v-if="loading" class="flex justify-center py-6">
                <Loader2 class="w-6 h-6 animate-spin text-text-muted" />
            </div>
        </div>

        <EmptyState
            v-else
            :icon="Rss"
            :title="hasSubscriptions ? t('feed.empty') : t('feed.no_subscriptions')"
            :description="hasSubscriptions ? t('feed.empty_desc') : t('feed.no_subscriptions_desc')"
        >
            <template v-if="!hasSubscriptions" #action>
                <Link :href="localizedUrl('/trending')" class="btn btn-primary">
                    {{ t('feed.discover') }}
                </Link>
            </template>
        </EmptyState>
    </AppLayout>
</template>
