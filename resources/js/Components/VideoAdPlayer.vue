<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useFetch } from '@/Composables/useFetch';

const props = defineProps({
    categoryId: { type: Number, default: null },
    videoDuration: { type: Number, default: 0 },
});

const emit = defineEmits([
    'ad-started',
    'ad-ended',
    'ad-skipped',
    'request-pause',
    'request-play',
    'request-current-time',
]);

const { get } = useFetch();

// State
const adData = ref(null);
const adConfig = ref(null);
const currentAd = ref(null);
const adPhase = ref('idle'); // idle, playing, skippable, done
const adType = ref(null); // pre_roll, mid_roll, post_roll
const adElapsed = ref(0);
const skipAfter = ref(0);
const canSkip = ref(false);
const adVideoRef = ref(null);
const adHtmlRef = ref(null);
const vastMediaUrl = ref(null);

// Mid-roll tracking
const midRollsPlayed = ref(0);
const lastMidRollTime = ref(0);
const midRollCheckInterval = ref(null);
const pendingMidRoll = ref(false);

// Timers
let skipTimer = null;
let elapsedTimer = null;

// Load ads from API
const loadAds = async () => {
    const params = new URLSearchParams();
    if (props.categoryId) params.set('category_id', props.categoryId);

    const { ok, data } = await get(`/api/video-ads?${params.toString()}`);
    if (ok && data) {
        adData.value = data.ads;
        adConfig.value = data.config;
    }
};

// Check if we have ads for a placement
const hasAds = (placement) => {
    return adData.value?.[placement]?.length > 0;
};

// Pick an ad for a placement
const pickAd = (placement) => {
    const ads = adData.value?.[placement];
    if (!ads?.length) return null;
    // Server already handles shuffle/weighting, just take first
    return ads[0];
};

// Get skip delay for a placement
const getSkipDelay = (placement) => {
    if (!adConfig.value) return 5;
    switch (placement) {
        case 'pre_roll': return adConfig.value.pre_roll_skip_after;
        case 'mid_roll': return adConfig.value.mid_roll_skip_after;
        case 'post_roll': return adConfig.value.post_roll_skip_after;
        default: return 5;
    }
};

// Start playing an ad
const playAd = (placement) => {
    const ad = pickAd(placement);
    if (!ad) return false;

    currentAd.value = ad;
    adType.value = placement;
    adPhase.value = 'playing';
    adElapsed.value = 0;
    skipAfter.value = getSkipDelay(placement);
    canSkip.value = skipAfter.value === 0;
    vastMediaUrl.value = null;

    emit('ad-started', placement);
    emit('request-pause');

    // Start elapsed timer
    clearTimers();
    elapsedTimer = setInterval(() => {
        adElapsed.value++;
        if (!canSkip.value && skipAfter.value > 0 && adElapsed.value >= skipAfter.value) {
            canSkip.value = true;
        }
    }, 1000);

    // Handle VAST/VPAID — fetch the XML and extract media URL
    if (ad.type === 'vast' || ad.type === 'vpaid') {
        fetchVastMedia(ad.content);
    }

    // For HTML ads, inject after next tick
    if (ad.type === 'html') {
        nextTick(() => {
            if (adHtmlRef.value) {
                adHtmlRef.value.innerHTML = ad.content;
                // Execute any scripts in the injected HTML
                const scripts = adHtmlRef.value.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => {
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }
        });
    }

    return true;
};

// Fetch VAST XML and extract the first MediaFile
const fetchVastMedia = async (vastUrl) => {
    try {
        const response = await fetch(vastUrl);
        const text = await response.text();
        const parser = new DOMParser();
        const xml = parser.parseFromString(text, 'text/xml');

        // Try to find a MediaFile element
        const mediaFiles = xml.querySelectorAll('MediaFile');
        if (mediaFiles.length > 0) {
            // Prefer MP4
            let chosen = null;
            mediaFiles.forEach(mf => {
                const type = mf.getAttribute('type') || '';
                if (type.includes('mp4') || type.includes('video')) {
                    if (!chosen) chosen = mf;
                }
            });
            if (!chosen) chosen = mediaFiles[0];
            vastMediaUrl.value = (chosen.textContent || '').trim();
        }

        // Fire impression pixels
        const impressions = xml.querySelectorAll('Impression');
        impressions.forEach(imp => {
            const url = (imp.textContent || '').trim();
            if (url) {
                new Image().src = url;
            }
        });
    } catch (e) {
        console.warn('Failed to parse VAST:', e);
        endAd();
    }
};

// Skip the current ad
const skipAd = () => {
    if (!canSkip.value) return;
    emit('ad-skipped', adType.value);
    endAd();
};

// End the current ad (natural end or skip)
const endAd = () => {
    clearTimers();
    const placement = adType.value;

    currentAd.value = null;
    adPhase.value = 'idle';
    adType.value = null;
    adElapsed.value = 0;
    canSkip.value = false;
    vastMediaUrl.value = null;

    // Clean up HTML ad container
    if (adHtmlRef.value) {
        adHtmlRef.value.innerHTML = '';
    }

    emit('ad-ended', placement);
    emit('request-play');
};

// Handle ad video ended naturally
const onAdVideoEnded = () => {
    endAd();
};

// Handle ad video error
const onAdVideoError = () => {
    console.warn('Ad video failed to load');
    endAd();
};

const clearTimers = () => {
    if (skipTimer) { clearTimeout(skipTimer); skipTimer = null; }
    if (elapsedTimer) { clearInterval(elapsedTimer); elapsedTimer = null; }
};

// ── Public API (called by parent) ──

// Called by parent to trigger pre-roll
const triggerPreRoll = () => {
    if (hasAds('pre_roll')) {
        return playAd('pre_roll');
    }
    return false;
};

// Called by parent to trigger post-roll
const triggerPostRoll = () => {
    if (hasAds('post_roll')) {
        return playAd('post_roll');
    }
    return false;
};

// Called by parent with current playback time to check mid-rolls
const checkMidRoll = (currentTime) => {
    if (!hasAds('mid_roll') || !adConfig.value) return false;
    if (adPhase.value !== 'idle') return false;

    const interval = adConfig.value.mid_roll_interval;
    const maxCount = adConfig.value.mid_roll_max_count;

    if (midRollsPlayed.value >= maxCount) return false;
    if (currentTime - lastMidRollTime.value < interval) return false;
    if (currentTime < interval) return false;

    // Don't play mid-roll in the last 30 seconds
    if (props.videoDuration > 0 && currentTime > props.videoDuration - 30) return false;

    lastMidRollTime.value = currentTime;
    midRollsPlayed.value++;
    return playAd('mid_roll');
};

// Is an ad currently playing?
const isPlaying = computed(() => adPhase.value !== 'idle');

// Remaining seconds until skip
const skipCountdown = computed(() => {
    if (canSkip.value || skipAfter.value === 0) return 0;
    return Math.max(0, skipAfter.value - adElapsed.value);
});

// Ad display type for template
const adDisplayType = computed(() => {
    if (!currentAd.value) return null;
    if (currentAd.value.type === 'mp4') return 'video';
    if (currentAd.value.type === 'vast' || currentAd.value.type === 'vpaid') return 'vast';
    if (currentAd.value.type === 'html') return 'html';
    return null;
});

// Expose methods to parent
defineExpose({
    loadAds,
    triggerPreRoll,
    triggerPostRoll,
    checkMidRoll,
    isPlaying,
    hasAds: (p) => hasAds(p),
});

onMounted(() => {
    loadAds();
});

onUnmounted(() => {
    clearTimers();
});
</script>

<template>
    <!-- Ad Overlay — covers the video player area -->
    <div
        v-if="isPlaying && currentAd"
        class="absolute inset-0 z-30 bg-black flex flex-col"
    >
        <!-- Ad Label -->
        <div class="absolute top-3 left-3 z-40 flex items-center gap-2">
            <span class="px-2 py-0.5 rounded text-xs font-bold bg-yellow-500 text-black uppercase tracking-wide">
                Ad
            </span>
            <span class="text-xs text-white/70">
                {{ adType === 'pre_roll' ? 'Pre-roll' : adType === 'mid_roll' ? 'Mid-roll' : 'Post-roll' }}
            </span>
        </div>

        <!-- Skip Button -->
        <div class="absolute bottom-16 right-4 z-40">
            <button
                v-if="canSkip"
                @click="skipAd"
                class="px-4 py-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white text-sm font-medium rounded-lg border border-white/30 transition-all"
            >
                Skip Ad →
            </button>
            <div
                v-else-if="skipAfter > 0"
                class="px-4 py-2 bg-black/60 text-white/80 text-sm rounded-lg"
            >
                Skip in {{ skipCountdown }}s
            </div>
        </div>

        <!-- Ad Content Area -->
        <div class="flex-1 flex items-center justify-center">
            <!-- MP4 Video Ad -->
            <video
                v-if="adDisplayType === 'video'"
                ref="adVideoRef"
                :src="currentAd.content"
                class="w-full h-full object-contain"
                autoplay
                playsinline
                @ended="onAdVideoEnded"
                @error="onAdVideoError"
            ></video>

            <!-- VAST/VPAID — plays the extracted media URL -->
            <template v-if="adDisplayType === 'vast'">
                <video
                    v-if="vastMediaUrl"
                    ref="adVideoRef"
                    :src="vastMediaUrl"
                    class="w-full h-full object-contain"
                    autoplay
                    playsinline
                    @ended="onAdVideoEnded"
                    @error="onAdVideoError"
                ></video>
                <div v-else class="flex items-center justify-center">
                    <div class="w-8 h-8 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
                </div>
            </template>

            <!-- HTML Ad Script -->
            <div
                v-if="adDisplayType === 'html'"
                ref="adHtmlRef"
                class="w-full h-full flex items-center justify-center"
            ></div>
        </div>

        <!-- Ad Progress Bar -->
        <div class="h-1 bg-white/10">
            <div
                v-if="skipAfter > 0 && !canSkip"
                class="h-full bg-yellow-500 transition-all duration-1000 ease-linear"
                :style="{ width: Math.min(100, (adElapsed / skipAfter) * 100) + '%' }"
            ></div>
            <div
                v-else
                class="h-full bg-yellow-500"
                style="width: 100%"
            ></div>
        </div>
    </div>
</template>
