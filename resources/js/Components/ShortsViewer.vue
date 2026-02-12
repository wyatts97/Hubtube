<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Heart, MessageCircle, Share2, MoreVertical, Volume2, VolumeX, Play, Pause, ChevronUp, ChevronDown, X, Flag } from 'lucide-vue-next';
import { useFetch } from '@/Composables/useFetch';
import { sanitizeHtml } from '@/Composables/useSanitize';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    shorts: { type: Array, required: true },
    adSettings: { type: Object, default: () => ({ enabled: false, frequency: 3, skipDelay: 5, code: '' }) },
    nextPageUrl: { type: String, default: null },
});

const emit = defineEmits(['load-more']);

const { post } = useFetch();

// State
const container = ref(null);
const currentIndex = ref(0);
const isMuted = ref(false);
const isPlaying = ref(true);
const videoRefs = ref({});
const loadingMore = ref(false);
const allShorts = ref([...props.shorts]);
const nextPage = ref(props.nextPageUrl);

// Ad state
const adSkipCountdown = ref(0);
const adSkipTimer = ref(null);
const canSkipAd = ref(false);

// Build the feed: interleave shorts with ad slots
const feed = computed(() => {
    const items = [];
    const freq = props.adSettings.frequency || 3;
    const adsEnabled = props.adSettings.enabled && props.adSettings.code;
    let shortCount = 0;

    for (const short of allShorts.value) {
        items.push({ type: 'short', data: short });
        shortCount++;

        if (adsEnabled && shortCount % freq === 0) {
            items.push({ type: 'ad', id: `ad-${shortCount}` });
        }
    }

    return items;
});

// Current feed item
const currentItem = computed(() => feed.value[currentIndex.value]);

// Like state per short
const likeState = ref({});

const initLikeState = (short) => {
    if (!likeState.value[short.id]) {
        likeState.value[short.id] = {
            liked: false,
            count: short.likes_count || 0,
        };
    }
};

// Register video ref
const setVideoRef = (index, el) => {
    if (el) {
        videoRefs.value[index] = el;
    }
};

// Play/pause management
const playVideo = (index) => {
    const item = feed.value[index];
    if (item?.type !== 'short') return;

    const video = videoRefs.value[index];
    if (!video) return;

    video.currentTime = 0;
    video.muted = isMuted.value;
    video.play().catch(() => {
        // Autoplay blocked — mute and retry
        isMuted.value = true;
        video.muted = true;
        video.play().catch(() => {});
    });
    isPlaying.value = true;
};

const pauseVideo = (index) => {
    const video = videoRefs.value[index];
    if (video) {
        video.pause();
    }
};

const pauseAllExcept = (activeIndex) => {
    Object.keys(videoRefs.value).forEach((key) => {
        const idx = parseInt(key);
        if (idx !== activeIndex) {
            pauseVideo(idx);
        }
    });
};

// Toggle play/pause on tap
const togglePlay = () => {
    const item = currentItem.value;
    if (item?.type !== 'short') return;

    const video = videoRefs.value[currentIndex.value];
    if (!video) return;

    if (video.paused) {
        video.play().catch(() => {});
        isPlaying.value = true;
    } else {
        video.pause();
        isPlaying.value = false;
    }
};

// Toggle mute
const toggleMute = () => {
    isMuted.value = !isMuted.value;
    Object.values(videoRefs.value).forEach((v) => {
        if (v) v.muted = isMuted.value;
    });
};

// Update browser URL to reflect the current short
const updateUrl = (item) => {
    if (item?.type === 'short' && item.data?.id) {
        const newUrl = `/shorts/${item.data.id}`;
        if (window.location.pathname !== newUrl) {
            window.history.replaceState({}, '', newUrl);
        }
    }
};

// Navigate to specific index
const goToIndex = (index) => {
    if (index < 0 || index >= feed.value.length) return;

    // Clear ad timer
    clearAdTimer();

    pauseAllExcept(index);
    currentIndex.value = index;

    nextTick(() => {
        const el = container.value?.children[index];
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        const item = feed.value[index];
        if (item?.type === 'short') {
            playVideo(index);
            initLikeState(item.data);
            updateUrl(item);
        } else if (item?.type === 'ad') {
            startAdTimer();
        }

        // Load more when near end
        if (index >= feed.value.length - 4 && nextPage.value && !loadingMore.value) {
            loadMore();
        }
    });
};

const goNext = () => goToIndex(currentIndex.value + 1);
const goPrev = () => goToIndex(currentIndex.value - 1);

// Ad skip timer
const startAdTimer = () => {
    const delay = props.adSettings.skipDelay || 0;
    if (delay <= 0) {
        canSkipAd.value = true;
        adSkipCountdown.value = 0;
        return;
    }

    canSkipAd.value = false;
    adSkipCountdown.value = delay;

    adSkipTimer.value = setInterval(() => {
        adSkipCountdown.value--;
        if (adSkipCountdown.value <= 0) {
            canSkipAd.value = true;
            clearInterval(adSkipTimer.value);
            adSkipTimer.value = null;
        }
    }, 1000);
};

const clearAdTimer = () => {
    if (adSkipTimer.value) {
        clearInterval(adSkipTimer.value);
        adSkipTimer.value = null;
    }
    canSkipAd.value = false;
    adSkipCountdown.value = 0;
};

const skipAd = () => {
    if (canSkipAd.value) {
        goNext();
    }
};

// Scroll-snap based index detection (debounced)
let scrollDebounce = null;
const handleScroll = () => {
    if (scrollDebounce) clearTimeout(scrollDebounce);
    scrollDebounce = setTimeout(() => {
        if (!container.value) return;
        const scrollTop = container.value.scrollTop;
        const height = container.value.clientHeight;
        const newIndex = Math.round(scrollTop / height);

        if (newIndex !== currentIndex.value && newIndex >= 0 && newIndex < feed.value.length) {
            clearAdTimer();
            pauseAllExcept(newIndex);
            currentIndex.value = newIndex;

            const item = feed.value[newIndex];
            if (item?.type === 'short') {
                playVideo(newIndex);
                initLikeState(item.data);
                updateUrl(item);
            } else if (item?.type === 'ad') {
                startAdTimer();
            }

            // Load more when near end
            if (newIndex >= feed.value.length - 4 && nextPage.value && !loadingMore.value) {
                loadMore();
            }
        }
    }, 150);
};

// Load more shorts
const loadMore = async () => {
    if (!nextPage.value || loadingMore.value) return;
    loadingMore.value = true;

    try {
        const res = await fetch(nextPage.value);
        const json = await res.json();
        if (json.data?.length) {
            allShorts.value.push(...json.data);
        }
        nextPage.value = json.next_page_url || null;
    } catch (e) {
        console.error('Failed to load more shorts:', e);
    }

    loadingMore.value = false;
};

// Like handler
const handleLike = async (short) => {
    initLikeState(short);
    const state = likeState.value[short.id];
    const { ok, data } = await post(`/videos/${short.id}/like`);
    if (ok && data) {
        state.liked = data.liked;
        state.count = data.likesCount;
    }
};

// Share handler
const handleShare = async (short) => {
    const url = `${window.location.origin}/shorts/${short.id}`;
    if (navigator.share) {
        try {
            await navigator.share({ title: short.title, url });
        } catch {}
    } else {
        try {
            await navigator.clipboard.writeText(url);
        } catch {}
    }
};

// Keyboard navigation
const handleKeydown = (e) => {
    if (e.key === 'ArrowDown' || e.key === 'j') {
        e.preventDefault();
        goNext();
    } else if (e.key === 'ArrowUp' || e.key === 'k') {
        e.preventDefault();
        goPrev();
    } else if (e.key === ' ') {
        e.preventDefault();
        togglePlay();
    } else if (e.key === 'm') {
        toggleMute();
    }
};

// Touch swipe support
let touchStartY = 0;
const handleTouchStart = (e) => {
    touchStartY = e.touches[0].clientY;
};
const handleTouchEnd = (e) => {
    const diff = touchStartY - e.changedTouches[0].clientY;
    if (Math.abs(diff) > 50) {
        if (diff > 0) goNext();
        else goPrev();
    }
};

// Wheel navigation (for desktop)
let wheelTimeout = null;
const handleWheel = (e) => {
    e.preventDefault();
    if (wheelTimeout) return;
    wheelTimeout = setTimeout(() => { wheelTimeout = null; }, 600);

    if (e.deltaY > 30) goNext();
    else if (e.deltaY < -30) goPrev();
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);

    // Play first short and set URL
    if (feed.value.length > 0 && feed.value[0].type === 'short') {
        nextTick(() => {
            initLikeState(feed.value[0].data);
            playVideo(0);
            updateUrl(feed.value[0]);
        });
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    clearAdTimer();
    // Pause all videos
    Object.values(videoRefs.value).forEach((v) => {
        if (v) v.pause();
    });
});

// Watch for new shorts from parent
watch(() => props.shorts, (newShorts) => {
    if (newShorts.length > allShorts.value.length) {
        allShorts.value = [...newShorts];
    }
});
</script>

<template>
    <div
        ref="container"
        class="shorts-container"
        @scroll.passive="handleScroll"
        @wheel.prevent="handleWheel"
        @touchstart.passive="handleTouchStart"
        @touchend.passive="handleTouchEnd"
    >
        <div
            v-for="(item, index) in feed"
            :key="item.type === 'short' ? `s-${item.data.id}` : item.id"
            class="shorts-slide"
        >
            <!-- SHORT SLIDE -->
            <template v-if="item.type === 'short'">
                <div class="shorts-video-wrapper" @click="togglePlay">
                    <video
                        :ref="(el) => setVideoRef(index, el)"
                        :src="item.data.video_url"
                        :poster="item.data.thumbnail_url"
                        class="shorts-video"
                        loop
                        playsinline
                        preload="metadata"
                        :muted="isMuted"
                    ></video>

                    <!-- Play/Pause overlay -->
                    <transition name="fade">
                        <div v-if="!isPlaying && currentIndex === index" class="shorts-play-overlay">
                            <Play class="w-16 h-16 text-white opacity-80" />
                        </div>
                    </transition>

                    <!-- Bottom gradient -->
                    <div class="shorts-gradient"></div>

                    <!-- Video info (bottom-left) -->
                    <div class="shorts-info">
                        <Link :href="`/channel/${item.data.user?.username}`" class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0" style="background-color: var(--color-bg-card);">
                                <img :src="item.data.user?.avatar_url || item.data.user?.avatar || '/images/default_avatar.webp'" class="w-full h-full object-cover" />
                            </div>
                            <span class="text-white text-sm font-medium">@{{ item.data.user?.username }}</span>
                        </Link>
                        <p class="text-white text-sm font-medium line-clamp-2">{{ item.data.title }}</p>
                        <p v-if="item.data.description" class="text-white/70 text-xs mt-1 line-clamp-1">{{ item.data.description }}</p>
                    </div>

                    <!-- Action buttons (right side) -->
                    <div class="shorts-actions">
                        <button
                            @click.stop="handleLike(item.data)"
                            class="shorts-action-btn"
                        >
                            <Heart
                                class="w-7 h-7"
                                :class="likeState[item.data.id]?.liked ? 'text-red-500' : 'text-white'"
                                :fill="likeState[item.data.id]?.liked ? 'currentColor' : 'none'"
                            />
                            <span class="text-white text-xs mt-1">{{ likeState[item.data.id]?.count || item.data.likes_count || 0 }}</span>
                        </button>

                        <Link :href="`/${item.data.slug}`" class="shorts-action-btn" @click.stop>
                            <MessageCircle class="w-7 h-7 text-white" />
                            <span class="text-white text-xs mt-1">{{ item.data.comments_count || 0 }}</span>
                        </Link>

                        <button @click.stop="handleShare(item.data)" class="shorts-action-btn">
                            <Share2 class="w-7 h-7 text-white" />
                            <span class="text-white text-xs mt-1">{{ t('common.share') || 'Share' }}</span>
                        </button>

                        <button @click.stop="toggleMute" class="shorts-action-btn">
                            <Volume2 v-if="!isMuted" class="w-6 h-6 text-white" />
                            <VolumeX v-else class="w-6 h-6 text-white" />
                        </button>
                    </div>
                </div>
            </template>

            <!-- AD SLIDE -->
            <template v-else-if="item.type === 'ad'">
                <div class="shorts-ad-wrapper">
                    <div class="shorts-ad-label">
                        <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-muted);">{{ t('common.sponsored') || 'Sponsored' }}</span>
                    </div>

                    <div class="shorts-ad-content" v-html="sanitizeHtml(adSettings.code)"></div>

                    <div class="shorts-ad-skip">
                        <button
                            v-if="canSkipAd || adSettings.skipDelay === 0"
                            @click="skipAd"
                            class="shorts-skip-btn"
                        >
                            <span>{{ t('common.skip') || 'Skip' }}</span>
                            <ChevronDown class="w-4 h-4" />
                        </button>
                        <div v-else-if="adSkipCountdown > 0 && currentIndex === index" class="shorts-skip-countdown">
                            Skip in {{ adSkipCountdown }}s
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Loading indicator -->
        <div v-if="loadingMore" class="shorts-loading">
            <div class="w-8 h-8 border-2 border-t-transparent rounded-full animate-spin" style="border-color: var(--color-accent); border-top-color: transparent;"></div>
        </div>

        <!-- Navigation arrows (desktop) -->
        <div class="shorts-nav hidden md:flex">
            <button
                @click="goPrev"
                :disabled="currentIndex === 0"
                class="shorts-nav-btn"
                :class="{ 'opacity-30 cursor-not-allowed': currentIndex === 0 }"
            >
                <ChevronUp class="w-6 h-6" />
            </button>
            <button
                @click="goNext"
                :disabled="currentIndex >= feed.length - 1"
                class="shorts-nav-btn"
                :class="{ 'opacity-30 cursor-not-allowed': currentIndex >= feed.length - 1 }"
            >
                <ChevronDown class="w-6 h-6" />
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="feed.length === 0" class="shorts-empty">
            <p class="text-lg font-medium" style="color: var(--color-text-primary);">{{ t('shorts.no_shorts') || 'No shorts yet' }}</p>
            <p class="text-sm mt-1" style="color: var(--color-text-muted);">{{ t('shorts.upload_first') || 'Be the first to upload a short!' }}</p>
        </div>
    </div>
</template>

<style scoped>
.shorts-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow-y: scroll;
    scroll-snap-type: y mandatory;
    scroll-behavior: smooth;
    background-color: #000;
    z-index: 40;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.shorts-container::-webkit-scrollbar {
    display: none;
}

.shorts-slide {
    scroll-snap-align: start;
    height: 100vh;
    height: 100dvh;
    width: 100%;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.shorts-video-wrapper {
    position: relative;
    height: 100%;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.shorts-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}

.shorts-play-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.2);
    pointer-events: none;
}

.shorts-gradient {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 50%;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
    pointer-events: none;
}

.shorts-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 80px;
    padding: 1.5rem;
    z-index: 2;
}

.shorts-actions {
    position: absolute;
    right: 0.5rem;
    bottom: 6rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;
    z-index: 2;
}

@media (min-width: 768px) {
    .shorts-actions {
        right: calc(50% - 240px - 4rem);
    }

    .shorts-info {
        left: calc(50% - 240px);
        right: calc(50% - 240px + 80px);
    }
}

.shorts-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem;
    border-radius: 9999px;
    transition: transform 0.15s ease;
    text-decoration: none;
}

.shorts-action-btn:hover {
    transform: scale(1.1);
}

.shorts-action-btn:active {
    transform: scale(0.95);
}

/* Ad slide */
.shorts-ad-wrapper {
    height: 100%;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    position: relative;
    background: var(--color-bg-primary, #0a0a0a);
}

.shorts-ad-label {
    position: absolute;
    top: env(safe-area-inset-top, 1rem);
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.1);
    margin-top: 1rem;
}

.shorts-ad-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-height: 70vh;
    overflow: hidden;
}

.shorts-ad-skip {
    position: absolute;
    bottom: 2rem;
    right: 1.5rem;
    z-index: 5;
}

.shorts-skip-btn {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #fff;
    background-color: var(--color-accent, #ef4444);
    transition: opacity 0.2s;
}

.shorts-skip-btn:hover {
    opacity: 0.9;
}

.shorts-skip-countdown {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(255, 255, 255, 0.1);
}

/* Navigation */
.shorts-nav {
    position: fixed;
    right: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    flex-direction: column;
    gap: 0.5rem;
    z-index: 50;
}

.shorts-nav-btn {
    width: 3rem;
    height: 3rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(8px);
    transition: background 0.2s;
}

.shorts-nav-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.2);
}

.shorts-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.shorts-empty {
    height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
