<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useEventListener, useIntersectionObserver } from '@vueuse/core';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { useToast } from '@/Composables/useToast';
import { formatViews } from '@/Composables/useFormatters';
import ShortsAdSlide from '@/Components/ShortsAdSlide.vue';
import ShareModal from '@/Components/ShareModal.vue';
import ReportModal from '@/Components/ReportModal.vue';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import { DropdownMenuItem } from 'reka-ui';
import SeoHead from '@/Components/SeoHead.vue';
import {
    Heart, MessageCircle, Share2, MoreVertical, Volume2, VolumeX,
    ChevronUp, ChevronDown, X, Filter, ChevronLeft, Flag
} from 'lucide-vue-next';

const props = defineProps({
    initialShorts: Object,
    startIndex: { type: Number, default: 0 },
    filters: Object,
    categories: Array,
    currentShort: Object,
    adSettings: { type: Object, default: () => ({}) },
    seo: { type: Object, default: () => ({}) },
});

const { t, localizedUrl } = useI18n();
const toast = useToast();
const page = usePage();
const { get, post } = useFetch();

const user = computed(() => page.props.auth?.user);

const items = ref([]);
const currentIndex = ref(Math.max(0, props.startIndex));
const loadingMore = ref(false);
const hasMore = ref(props.initialShorts?.next_page_url ? true : false);
const nextCursor = ref(2);
const muted = ref(true);
const showComments = ref(false);
const showFilters = ref(false);
const commentsCloseRef = ref(null);
const filtersCloseRef = ref(null);

// Move focus into a panel when it opens, so keyboard and screen-reader users
// land in it rather than behind it.
watch(showComments, (open) => open && nextTick(() => commentsCloseRef.value?.focus()));
watch(showFilters, (open) => open && nextTick(() => filtersCloseRef.value?.focus()));
const showShareModal = ref(false);
const showReportModal = ref(false);
const comments = ref([]);
const loadingComments = ref(false);
const feedContainer = ref(null);
const slides = ref([]);

const activeFilters = ref({ ...props.filters });

const adEnabled = computed(() => props.adSettings?.shortsAdEnabled === true);
const adFrequency = computed(() => parseInt(props.adSettings?.shortsAdFrequency) || 8);

function buildFeedItems(shorts, insertAfter = 0) {
    const result = [];
    let sinceLastAd = insertAfter % adFrequency.value;

    shorts.forEach((short) => {
        result.push({ type: 'short', data: short });
        sinceLastAd++;
        if (adEnabled.value && sinceLastAd >= adFrequency.value) {
            result.push({ type: 'ad', id: `ad-${short.id}` });
            sinceLastAd = 0;
        }
    });

    return result;
}

const activeShort = computed(() => {
    const item = items.value[currentIndex.value];
    return item?.type === 'short' ? item.data : null;
});

const currentVideoId = computed(() => activeShort.value?.id);

const shareUrl = computed(() => {
    if (!activeShort.value) return '';
    return window.location.origin + localizedUrl(`/shorts/${activeShort.value.uuid}`);
});

const toggleMute = () => {
    muted.value = !muted.value;
    const video = slides.value[currentIndex.value]?.querySelector('video');
    if (video) video.muted = muted.value;
};

const togglePlay = () => {
    const el = slides.value[currentIndex.value]?.querySelector('video');
    if (!el) return;
    if (el.paused) el.play().catch(() => {});
    else el.pause();
};

const likeShort = async () => {
    if (!activeShort.value) return;
    if (!user.value) {
        router.visit(localizedUrl('/login'));
        return;
    }
    const { ok, data } = await post(`/videos/${activeShort.value.id}/like`);
    if (ok && data && activeShort.value) {
        activeShort.value.user_liked = data.liked;
        activeShort.value.likes_count = data.likesCount;
    }
};

const shareShort = () => {
    if (!activeShort.value) return;
    showShareModal.value = true;
};

const openReport = () => {
    if (!activeShort.value) return;
    if (!user.value) {
        router.visit(localizedUrl('/login'));
        return;
    }
    showReportModal.value = true;
};

const openComments = async () => {
    if (!activeShort.value) return;
    showComments.value = true;
    await loadComments(activeShort.value.id);
};

const loadComments = async (videoId) => {
    loadingComments.value = true;
    const { ok, data } = await get(`/videos/${videoId}/comments`);
    if (ok && data) comments.value = data.comments || [];
    loadingComments.value = false;
};

const submitComment = async (content) => {
    if (!activeShort.value || !content.trim()) return;
    const { ok, data } = await post(`/videos/${activeShort.value.id}/comments`, { content });
    if (ok && data) {
        comments.value.unshift(data.comment);
        activeShort.value.comments_count = (activeShort.value.comments_count || 0) + 1;
    }
};

const loadMore = async () => {
    if (loadingMore.value || !hasMore.value) return;
    loadingMore.value = true;

    const exclude = items.value
        .filter(i => i.type === 'short')
        .map(i => i.data.id)
        .join(',');

    const params = new URLSearchParams();
    params.set('cursor', nextCursor.value);
    params.set('exclude', exclude);
    for (const [k, v] of Object.entries(activeFilters.value)) {
        if (v) params.set(k, v);
    }

    const { ok, data } = await get(`${localizedUrl('/api/shorts/feed')}?${params.toString()}`);
    if (ok && data?.data?.length) {
        const insertAfter = items.value.filter(i => i.type === 'short').length % adFrequency.value;
        items.value.push(...buildFeedItems(data.data, insertAfter));
        nextCursor.value = data.next_cursor || nextCursor.value + 1;
        hasMore.value = !!data.next_cursor;
    } else {
        hasMore.value = false;
    }
    loadingMore.value = false;
};

const applyFilters = () => {
    const query = {};
    if (activeFilters.value.category_id) query.category = activeFilters.value.category_id;
    if (activeFilters.value.tag) query.tag = activeFilters.value.tag;
    if (activeFilters.value.date) query.date = activeFilters.value.date;
    if (activeFilters.value.sort && activeFilters.value.sort !== 'latest') query.sort = activeFilters.value.sort;
    router.get(localizedUrl('/shorts'), query, { preserveState: false });
};

const clearFilters = () => {
    activeFilters.value = { category_id: null, tag: null, date: null, sort: 'latest' };
    router.get(localizedUrl('/shorts'), {}, { preserveState: false });
};

const scrollToIndex = (index) => {
    if (!feedContainer.value) return;
    const slide = feedContainer.value.children[index];
    if (slide) slide.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const goNext = () => {
    if (currentIndex.value < items.value.length - 1) {
        scrollToIndex(currentIndex.value + 1);
    } else if (hasMore.value) {
        loadMore().then(() => nextTick(() => scrollToIndex(currentIndex.value + 1)));
    }
};

const goPrev = () => {
    if (currentIndex.value > 0) scrollToIndex(currentIndex.value - 1);
};

const handleKeydown = (e) => {
    if (e.key === 'Escape' && (showComments.value || showFilters.value)) {
        showComments.value = false;
        showFilters.value = false;
        return;
    }
    if (showComments.value || showFilters.value) return;
    if (e.key === 'ArrowDown' || e.key === ' ') {
        e.preventDefault();
        if (e.key === ' ') togglePlay();
        else goNext();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        goPrev();
    } else if (e.key.toLowerCase() === 'm') {
        toggleMute();
    }
};

const onScroll = () => {
    if (!feedContainer.value) return;
    const container = feedContainer.value;
    const newIndex = Math.round(container.scrollTop / container.clientHeight);
    if (newIndex !== currentIndex.value && newIndex >= 0 && newIndex < items.value.length) {
        currentIndex.value = newIndex;
    }
};

const pauseAllVideos = (exceptIndex) => {
    slides.value.forEach((slide, idx) => {
        if (idx === exceptIndex || !slide) return;
        const video = slide.querySelector('video');
        if (video) {
            video.pause();
            video.currentTime = 0;
        }
    });
};

/**
 * Autoplay tracking for the slide feed.
 *
 * This was deliberately left as a hand-rolled IntersectionObserver during the
 * VueUse pass because it observes a *dynamic* array: `slides` grows as more
 * shorts load via infinite scroll, and the old code only ever observed the
 * slides that existed at the single moment `setupIntersectionTracking()` ran,
 * so slides appended later were never tracked.
 *
 * `useIntersectionObserver` accepts an array target and rebuilds the observer
 * whenever that array changes, which fixes that limitation rather than
 * preserving it — an intentional behavior change, not a mechanical swap.
 */
useIntersectionObserver(
    slides,
    (entries) => {
        entries.forEach((entry) => {
            const idx = Number(entry.target.dataset.index);
            if (isNaN(idx)) return;
            const item = items.value[idx];
            const video = entry.target.querySelector('video');
            if (!video) return;

            if (entry.isIntersecting && idx === currentIndex.value) {
                // Pause all other videos first to prevent overlap
                pauseAllVideos(idx);
                video.muted = muted.value;
                video.play().catch(() => {});
                if (item?.type === 'short') {
                    video.setAttribute('data-viewed', 'true');
                }
            } else {
                video.pause();
                video.currentTime = 0;
            }
        });
    },
    { root: feedContainer, threshold: 0.6 }
);

useEventListener(window, 'keydown', handleKeydown);
useEventListener(feedContainer, 'scroll', onScroll);

onMounted(() => {
    items.value = buildFeedItems(props.initialShorts?.data || []);

    nextTick(() => {
        if (feedContainer.value && currentIndex.value > 0) {
            feedContainer.value.scrollTop = currentIndex.value * feedContainer.value.clientHeight;
        }
    });
});

watch(currentIndex, async (newIndex, oldIndex) => {
    
    // Pause previous video
    if (oldIndex !== undefined && slides.value[oldIndex]) {
        const prevVideo = slides.value[oldIndex].querySelector('video');
        if (prevVideo) {
            prevVideo.pause();
            prevVideo.currentTime = 0;
        }
    }
    
    // Play new video
    if (slides.value[newIndex]) {
        const newVideo = slides.value[newIndex].querySelector('video');
        if (newVideo) {
            newVideo.muted = muted.value;
            newVideo.play().catch(() => {});
        }
    }
    
    if (newIndex >= items.value.length - 3 && hasMore.value) await loadMore();
    if (activeShort.value && showComments.value) await loadComments(activeShort.value.id);
}, { flush: 'post' }); // after the DOM update that gives the new slide its src

watch(muted, (value) => {
    const video = slides.value[currentIndex.value]?.querySelector('video');
    if (video) video.muted = value;
});

const goBack = () => router.visit(localizedUrl('/'));
</script>

<template>
    <SeoHead />

    <div class="fixed inset-0 z-50 bg-black flex">
        <!-- Desktop back button -->
        <button
            @click="goBack"
            class="hidden lg:flex absolute top-4 start-4 z-50 items-center gap-2 px-3 py-2 rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors"
        >
            <ChevronLeft class="w-5 h-5" />
            <span class="text-sm font-medium">{{ t('common.back') }}</span>
        </button>

        <!-- Main swipe feed -->
        <div
            ref="feedContainer"
            class="flex-1 h-full overflow-y-scroll snap-y snap-mandatory scrollbar-hide"
            style="scroll-snap-type: y mandatory;"
        >
            <div
                v-for="(item, index) in items"
                :key="item.type === 'short' ? item.data.uuid : item.id"
                :data-index="index"
                :ref="el => { if (el) slides[index] = el }"
                class="relative w-full h-full shrink-0 snap-start overflow-hidden"
            >
                <template v-if="item.type === 'short'">
                    <!-- Only the current short and its neighbours get a source, so a
                         long feed doesn't keep a preloading video per slide. -->
                    <video
                        :src="Math.abs(index - currentIndex) <= 1 ? item.data.video_url : undefined"
                        class="w-full h-full object-contain bg-black"
                        loop
                        playsinline
                        :muted="muted"
                        preload="metadata"
                    />

                    <!-- Right action bar -->
                    <div class="absolute end-3 bottom-24 lg:bottom-12 z-20 flex flex-col items-center gap-5">
                        <Link :href="localizedUrl(`/channel/${item.data.user?.username}`)" class="flex flex-col items-center">
                            <div class="w-10 h-10 rounded-full overflow-hidden border border-white/30 bg-bg-secondary">
                                <img :src="item.data.user?.avatar || '/assets/default_avatar.webp'" :alt="item.data.user?.avatar_alt || item.data.user?.username || 'User'" class="w-full h-full object-cover" />
                            </div>
                        </Link>

                        <button @click="likeShort" class="flex flex-col items-center gap-1 text-white" :aria-label="t('video.like')" :aria-pressed="!!item.data.user_liked">
                            <Heart class="w-7 h-7" :fill="item.data.user_liked ? 'currentColor' : 'none'" />
                            <span class="text-xs font-medium">{{ formatViews(item.data.likes_count || 0) }}</span>
                        </button>

                        <button @click="openComments" class="flex flex-col items-center gap-1 text-white" :aria-label="t('shorts.comments')">
                            <MessageCircle class="w-7 h-7" />
                            <span class="text-xs font-medium">{{ item.data.comments_count || 0 }}</span>
                        </button>

                        <BaseDropdown
                            side="top"
                            align="end"
                            :side-offset="8"
                            content-class="bg-black/80 backdrop-blur-sm rounded-xl p-2 min-w-[140px] border-white/10"
                        >
                            <template #trigger>
                                <button class="text-white relative" :aria-label="t('common.more')">
                                    <MoreVertical class="w-7 h-7" />
                                </button>
                            </template>

                            <DropdownMenuItem
                                class="flex items-center gap-3 w-full px-3 py-2 text-white rounded-lg cursor-pointer outline-none data-[highlighted]:bg-white/10"
                                @select="shareShort"
                            >
                                <Share2 class="w-5 h-5" />
                                <span class="text-sm">{{ t('common.share') }}</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-3 w-full px-3 py-2 text-white rounded-lg cursor-pointer outline-none data-[highlighted]:bg-white/10"
                                @select="openReport"
                            >
                                <Flag class="w-5 h-5" />
                                <span class="text-sm">{{ t('common.report') }}</span>
                            </DropdownMenuItem>
                        </BaseDropdown>
                    </div>

                    <!-- Bottom info -->
                    <div class="absolute start-0 end-16 lg:end-24 bottom-12 lg:bottom-8 p-4 z-20">
                        <Link :href="localizedUrl(`/channel/${item.data.user?.username}`)" class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-semibold text-white">{{ item.data.user?.username }}</span>
                        </Link>
                        <h3 class="text-white font-semibold text-base line-clamp-2 mb-1">{{ item.data.title }}</h3>
                        <p v-if="item.data.description" class="text-white/80 text-sm line-clamp-2">{{ item.data.description }}</p>
                    </div>

                    <!-- Top controls -->
                    <div class="absolute top-4 start-0 lg:start-auto lg:end-4 end-0 px-4 lg:px-0 z-[60] flex items-center justify-between lg:justify-end gap-3">
                        <button @click="goBack" class="lg:hidden text-white/80 hover:text-white" :aria-label="t('common.back')">
                            <ChevronLeft class="w-6 h-6" />
                        </button>
                        <div class="flex items-center gap-3">
                            <button @click="toggleMute" class="text-white/80 hover:text-white p-2 rounded-full bg-black/40 hover:bg-black/60" :aria-label="muted ? t('shorts.unmute') : t('shorts.mute')">
                                <VolumeX v-if="muted" class="w-6 h-6" />
                                <Volume2 v-else class="w-6 h-6" />
                            </button>
                            <button @click="showFilters = true" class="text-white/80 hover:text-white p-2 rounded-full bg-black/40 hover:bg-black/60" :aria-label="t('shorts.filter_title')">
                                <Filter class="w-6 h-6" />
                            </button>
                        </div>
                    </div>

                    <!-- Tap to play/mute area (center-left) -->
                    <div
                        class="absolute inset-0 z-10"
                        @click="togglePlay"
                    ></div>
                </template>

                <ShortsAdSlide v-else @load-more="loadMore" />
            </div>

            <!-- Loading sentinel -->
            <div v-if="loadingMore" class="h-full w-full flex items-center justify-center shrink-0">
                <div class="w-10 h-10 border-3 border-white/30 border-t-white rounded-full animate-spin"></div>
            </div>
        </div>

        <!-- Desktop prev/next chevrons -->
        <div class="hidden lg:flex absolute end-6 top-1/2 -translate-y-1/2 z-40 flex-col gap-4">
            <button
                @click="goPrev"
                :disabled="currentIndex === 0"
                :aria-label="t('common.previous')"
                class="p-2 rounded-full bg-black/50 text-white hover:bg-black/70 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
            >
                <ChevronUp class="w-7 h-7" />
            </button>
            <button
                @click="goNext"
                :disabled="currentIndex >= items.length - 1 && !hasMore"
                :aria-label="t('common.next')"
                class="p-2 rounded-full bg-black/50 text-white hover:bg-black/70 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
            >
                <ChevronDown class="w-7 h-7" />
            </button>
        </div>

        <!-- Comments drawer -->
        <Transition name="slide-up">
            <div
                v-if="showComments"
                role="dialog"
                aria-modal="true"
                aria-labelledby="shorts-comments-title"
                class="fixed inset-x-0 bottom-0 z-50 bg-bg-card rounded-t-2xl border-t border-border max-h-[70vh] flex flex-col"
            >
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h3 id="shorts-comments-title" class="text-lg font-semibold text-text-primary">{{ t('shorts.comments_count', { count: comments.length }) }}</h3>
                    <button ref="commentsCloseRef" @click="showComments = false" class="text-text-secondary hover:text-text-primary" :aria-label="t('common.close')">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div v-if="loadingComments" class="text-center py-8">
                        <div class="w-8 h-8 border-2 border-t-transparent rounded-full animate-spin mx-auto" style="border-color: var(--color-accent); border-top-color: transparent;"></div>
                    </div>
                    <div v-for="comment in comments" :key="comment.id" class="flex gap-3">
                        <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 bg-bg-secondary">
                            <img :src="comment.user?.avatar || '/assets/default_avatar.webp'" :alt="comment.user?.avatar_alt || comment.user?.username || 'User'" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-text-primary">{{ comment.user?.username }}</p>
                            <p class="text-sm text-text-secondary mt-0.5">{{ comment.content }}</p>
                        </div>
                    </div>
                    <div v-if="!loadingComments && !comments.length" class="text-center text-text-muted py-8">{{ t('shorts.no_comments') }}</div>
                </div>

                <div v-if="user" class="p-4 border-t border-border flex gap-2">
                    <input
                        type="text"
                        :placeholder="t('shorts.add_comment')"
                        :aria-label="t('shorts.add_comment')"
                        class="input flex-1"
                        @keydown.enter="submitComment($event.target.value); $event.target.value = ''"
                    />
                </div>
            </div>
        </Transition>

        <!-- Filters panel -->
        <Transition name="slide-up">
            <div
                v-if="showFilters"
                role="dialog"
                aria-modal="true"
                aria-labelledby="shorts-filters-title"
                class="fixed inset-x-0 bottom-0 z-50 bg-bg-card rounded-t-2xl border-t border-border p-4 max-h-[80vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 id="shorts-filters-title" class="text-lg font-semibold text-text-primary">{{ t('shorts.filter_title') }}</h3>
                    <button ref="filtersCloseRef" @click="showFilters = false" class="text-text-secondary hover:text-text-primary" :aria-label="t('common.close')">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="shorts-filter-category" class="block text-sm font-medium text-text-secondary mb-1">{{ t('shorts.category') }}</label>
                        <select id="shorts-filter-category" v-model="activeFilters.category_id" class="input w-full">
                            <option :value="null">{{ t('shorts.all_categories') }}</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="shorts-filter-tag" class="block text-sm font-medium text-text-secondary mb-1">{{ t('shorts.tag') }}</label>
                        <input id="shorts-filter-tag" v-model="activeFilters.tag" type="text" class="input w-full" />
                    </div>

                    <div>
                        <label for="shorts-filter-date" class="block text-sm font-medium text-text-secondary mb-1">{{ t('shorts.date') }}</label>
                        <select id="shorts-filter-date" v-model="activeFilters.date" class="input w-full">
                            <option :value="null">{{ t('shorts.any_time') }}</option>
                            <option value="today">{{ t('shorts.today') }}</option>
                            <option value="week">{{ t('shorts.this_week') }}</option>
                            <option value="month">{{ t('shorts.this_month') }}</option>
                            <option value="year">{{ t('shorts.this_year') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="shorts-filter-sort" class="block text-sm font-medium text-text-secondary mb-1">{{ t('shorts.sort') }}</label>
                        <select id="shorts-filter-sort" v-model="activeFilters.sort" class="input w-full">
                            <option value="latest">{{ t('shorts.latest') }}</option>
                            <option value="popular">{{ t('shorts.most_popular') }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button @click="clearFilters" class="btn btn-ghost flex-1">{{ t('shorts.clear') }}</button>
                        <button @click="applyFilters" class="btn btn-primary flex-1">{{ t('shorts.apply') }}</button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Share modal -->
        <ShareModal v-model="showShareModal" :url="shareUrl" :title="activeShort?.title" />

        <!-- Report modal -->
        <ReportModal
            v-if="activeShort"
            v-model="showReportModal"
            :reportable-id="activeShort.id"
            reportable-type="video"
        />
    </div>
</template>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.25s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
}
</style>
