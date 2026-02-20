<script setup>
/**
 * VideoAdPlayer — pre/mid/post-roll ads.
 *
 * VAST/VPAID ads use Google IMA SDK (loaded on demand).
 * IMA handles: wrapper chains, skipoffset, impression/click/quartile pixels.
 * Admin skip settings do NOT override VAST — the ad network controls that.
 *
 * Local MP4/HTML ads use the custom player with admin-configured skip delay.
 * Weighted shuffle is handled server-side.
 */
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
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
]);

const { get } = useFetch();

// ── State ──
const adData = ref(null);
const adConfig = ref(null);
const currentAd = ref(null);
const adPhase = ref('idle');
const adType = ref(null);

// Local ad state (MP4 / HTML only)
const adElapsed = ref(0);
const skipAfter = ref(0);
const canSkip = ref(false);
const adVideoRef = ref(null);
const adHtmlRef = ref(null);
const autoplayBlocked = ref(false);

// IMA state (VAST / VPAID only)
const imaContainerRef = ref(null);
const imaVideoRef = ref(null);
let imaDisplayContainer = null;
let imaAdsLoader = null;
let imaAdsManager = null;

// Mid-roll tracking
const midRollsPlayed = ref(0);
const lastMidRollTime = ref(0);

// Timers
let elapsedTimer = null;

// ── Ad loading ──
let adsLoadedPromise = null;

const loadAds = () => {
    const params = new URLSearchParams();
    if (props.categoryId) params.set('category_id', props.categoryId);
    adsLoadedPromise = get(`/api/video-ads?${params.toString()}`).then(({ ok, data }) => {
        if (ok && data) {
            adData.value = data.ads;
            adConfig.value = data.config;
        }
    });
    return adsLoadedPromise;
};

const waitForAds = async () => {
    if (adsLoadedPromise) await adsLoadedPromise;
};

const hasAds = (placement) => adData.value?.[placement]?.length > 0;
const pickAd  = (placement) => adData.value?.[placement]?.[0] ?? null;

// ── Skip delay (local ads only) ──
const getSkipDelay = (placement) => {
    if (!adConfig.value) return 5;
    switch (placement) {
        case 'pre_roll':  return adConfig.value.pre_roll_skip_after;
        case 'mid_roll':  return adConfig.value.mid_roll_skip_after;
        case 'post_roll': return adConfig.value.post_roll_skip_after;
        default:          return 5;
    }
};

// ── IMA SDK (VAST / VPAID) ──
const loadImaSdk = () => new Promise((resolve, reject) => {
    if (window.google?.ima) { resolve(); return; }
    const s = document.createElement('script');
    s.src = 'https://imasdk.googleapis.com/js/sdkloader/ima3.js';
    s.onload = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
});

const destroyIma = () => {
    try { imaAdsManager?.destroy(); } catch (_) {}
    try { imaAdsLoader?.contentComplete(); } catch (_) {}
    imaAdsManager = null;
    imaAdsLoader = null;
    imaDisplayContainer = null;
};

const playVastAd = async (ad, placement) => {
    try { await loadImaSdk(); }
    catch (e) {
        console.warn('[VideoAdPlayer] IMA SDK failed to load:', e);
        endAd(); return;
    }

    await nextTick();
    if (!imaContainerRef.value || !imaVideoRef.value) { endAd(); return; }

    try {
        const ima = window.google.ima;
        destroyIma();

        ima.settings.setDisableCustomPlaybackForIOS10Plus(true);

        imaDisplayContainer = new ima.AdDisplayContainer(imaContainerRef.value, imaVideoRef.value);
        imaDisplayContainer.initialize();

        imaAdsLoader = new ima.AdsLoader(imaDisplayContainer);

        imaAdsLoader.addEventListener(
            ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED,
            (event) => {
                imaAdsManager = event.getAdsManager(imaVideoRef.value);

                imaAdsManager.addEventListener(ima.AdEvent.Type.STARTED, () => {
                    emit('ad-started', placement);
                    emit('request-pause');
                });
                imaAdsManager.addEventListener(ima.AdEvent.Type.COMPLETE, () => { destroyIma(); endAd(); });
                imaAdsManager.addEventListener(ima.AdEvent.Type.SKIPPED,  () => { destroyIma(); emit('ad-skipped', placement); endAd(); });
                imaAdsManager.addEventListener(ima.AdEvent.Type.ALL_ADS_COMPLETED, () => { destroyIma(); endAd(); });
                imaAdsManager.addEventListener(ima.AdErrorEvent.Type.AD_ERROR, (err) => {
                    console.warn('[VideoAdPlayer] IMA ad error:', err.getError().toString());
                    destroyIma(); endAd();
                });

                try {
                    const w = imaContainerRef.value?.offsetWidth  || 640;
                    const h = imaContainerRef.value?.offsetHeight || 360;
                    imaAdsManager.init(w, h, ima.ViewMode.NORMAL);
                    imaAdsManager.start();
                } catch (err) {
                    console.warn('[VideoAdPlayer] IMA start error:', err);
                    destroyIma(); endAd();
                }
            }
        );

        imaAdsLoader.addEventListener(ima.AdErrorEvent.Type.AD_ERROR, (err) => {
            console.warn('[VideoAdPlayer] IMA loader error:', err.getError().toString());
            destroyIma(); endAd();
        });

        const req = new ima.AdsRequest();
        req.adTagUrl = ad.content.trim();
        req.linearAdSlotWidth    = imaContainerRef.value?.offsetWidth  || 640;
        req.linearAdSlotHeight   = imaContainerRef.value?.offsetHeight || 360;
        req.nonLinearAdSlotWidth  = imaContainerRef.value?.offsetWidth  || 640;
        req.nonLinearAdSlotHeight = 150;
        imaAdsLoader.requestAds(req);
    } catch (e) {
        console.warn('[VideoAdPlayer] IMA setup error:', e);
        destroyIma(); endAd();
    }
};

// ── Local ad playback (MP4 / HTML) ──
const playLocalAd = (ad, placement) => {
    adElapsed.value = 0;
    skipAfter.value = getSkipDelay(placement);
    canSkip.value   = skipAfter.value === 0;

    emit('ad-started', placement);
    emit('request-pause');

    clearTimers();
    elapsedTimer = setInterval(() => {
        adElapsed.value++;
        if (!canSkip.value && skipAfter.value > 0 && adElapsed.value >= skipAfter.value) {
            canSkip.value = true;
        }
    }, 1000);

    if (ad.type === 'html') {
        nextTick(() => {
            if (!adHtmlRef.value) return;
            adHtmlRef.value.innerHTML = ad.content;
            adHtmlRef.value.querySelectorAll('script').forEach(old => {
                const s = document.createElement('script');
                Array.from(old.attributes).forEach(a => s.setAttribute(a.name, a.value));
                s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
        });
    }
};

// ── Main play dispatcher ──
const playAd = (placement) => {
    const ad = pickAd(placement);
    if (!ad) return false;

    currentAd.value = ad;
    adType.value    = placement;
    adPhase.value   = 'playing';

    if (ad.type === 'vast' || ad.type === 'vpaid') {
        playVastAd(ad, placement);
    } else {
        playLocalAd(ad, placement);
    }
    return true;
};

// ── Skip (local ads only) ──
const skipAd = () => {
    if (!canSkip.value) return;
    emit('ad-skipped', adType.value);
    endAd();
};

// ── End ad ──
const endAd = () => {
    clearTimers();
    const placement = adType.value;
    currentAd.value = null;
    adPhase.value   = 'idle';
    adType.value    = null;
    adElapsed.value = 0;
    canSkip.value   = false;
    autoplayBlocked.value = false;
    if (adHtmlRef.value) adHtmlRef.value.innerHTML = '';
    emit('ad-ended', placement);
    emit('request-play');
};

// ── Local video helpers ──
const tryPlayAd = () => {
    if (!adVideoRef.value) return;
    const v = adVideoRef.value;
    v.muted = true;
    const p = v.play();
    if (p !== undefined) {
        p.then(() => {
            autoplayBlocked.value = false;
            setTimeout(() => { v.muted = false; }, 200);
        }).catch(() => {
            autoplayBlocked.value = true;
        });
    }
};

const manualPlay = () => {
    if (!adVideoRef.value) return;
    const v = adVideoRef.value;
    v.muted = false;
    v.play().then(() => { autoplayBlocked.value = false; }).catch(() => {});
};

const onAdClick = (e) => {
    if (currentAd.value?.click_url)
        window.open(currentAd.value.click_url, '_blank', 'noopener,noreferrer');
};

const onAdVideoEnded = () => endAd();
const onAdVideoError = (e) => {
    console.warn('Ad video error:', e?.target?.error?.message || '');
    endAd();
};

const clearTimers = () => {
    if (elapsedTimer) { clearInterval(elapsedTimer); elapsedTimer = null; }
};

// ── Computed ──
const isPlaying      = computed(() => adPhase.value !== 'idle');
const isVastAd       = computed(() => currentAd.value?.type === 'vast' || currentAd.value?.type === 'vpaid');
const isLocalVideoAd = computed(() => currentAd.value?.type === 'mp4');
const isHtmlAd       = computed(() => currentAd.value?.type === 'html');

const skipCountdown = computed(() => {
    if (canSkip.value || skipAfter.value === 0) return 0;
    return Math.max(0, skipAfter.value - adElapsed.value);
});

// ── Public API ──
const triggerPreRoll = async () => {
    await waitForAds();
    return hasAds('pre_roll') ? playAd('pre_roll') : false;
};
const triggerPostRoll = async () => {
    await waitForAds();
    return hasAds('post_roll') ? playAd('post_roll') : false;
};

const checkMidRoll = (currentTime) => {
    if (!hasAds('mid_roll') || !adConfig.value || adPhase.value !== 'idle') return false;
    const interval = adConfig.value.mid_roll_interval;
    const maxCount = adConfig.value.mid_roll_max_count;
    if (midRollsPlayed.value >= maxCount) return false;
    if (currentTime - lastMidRollTime.value < interval) return false;
    if (currentTime < interval) return false;
    if (props.videoDuration > 0 && currentTime > props.videoDuration - 30) return false;
    lastMidRollTime.value = currentTime;
    midRollsPlayed.value++;
    return playAd('mid_roll');
};

defineExpose({ loadAds, triggerPreRoll, triggerPostRoll, checkMidRoll, isPlaying, hasAds: (p) => hasAds(p) });

onMounted(() => loadAds());
onUnmounted(() => { clearTimers(); destroyIma(); });
</script>

<template>
    <div v-if="isPlaying && currentAd" class="absolute inset-0 z-30 bg-black flex flex-col">
        <!-- Ad Label -->
        <div class="absolute top-2 left-2 z-40">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-500 text-black uppercase tracking-wide">Ad</span>
        </div>

        <!-- Skip Button — local ads only; IMA SDK renders its own skip UI -->
        <div v-if="!isVastAd" class="absolute bottom-4 right-2 z-40">
            <button v-if="canSkip" @click.stop="skipAd"
                class="px-2.5 py-1 bg-black/60 hover:bg-black/80 text-white text-xs font-medium rounded border border-white/40 transition-all">
                Skip →
            </button>
            <div v-else-if="skipAfter > 0" class="px-2.5 py-1 bg-black/60 text-white/70 text-xs rounded border border-white/20">
                Skip in {{ skipCountdown }}s
            </div>
        </div>

        <!-- Ad Content -->
        <div class="flex-1 relative overflow-hidden">

            <!-- VAST/VPAID — Google IMA SDK handles everything -->
            <template v-if="isVastAd">
                <video ref="imaVideoRef" class="w-full h-full object-contain" playsinline style="display:block"></video>
                <div ref="imaContainerRef" class="absolute inset-0" style="pointer-events:auto"></div>
            </template>

            <!-- Local MP4 -->
            <template v-if="isLocalVideoAd">
                <video ref="adVideoRef" :src="currentAd.content"
                    class="w-full h-full object-contain"
                    autoplay muted playsinline crossorigin="anonymous"
                    @ended="onAdVideoEnded" @error="onAdVideoError"
                    @loadeddata="tryPlayAd" @canplay="tryPlayAd">
                </video>

                <!-- Tap-to-play overlay — only shown when autoplay is blocked (mobile) -->
                <div v-if="autoplayBlocked"
                    class="absolute inset-0 flex items-center justify-center bg-black/40 cursor-pointer z-10"
                    @click.stop="manualPlay">
                    <div class="w-14 h-14 rounded-full bg-white/20 backdrop-blur-sm border border-white/40 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white ml-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>
            </template>

            <!-- HTML Ad -->
            <div v-if="isHtmlAd" ref="adHtmlRef" class="w-full h-full flex items-center justify-center"></div>

            <!-- Click CTA (local only, not covering center) -->
            <div v-if="currentAd?.click_url && !isVastAd && !autoplayBlocked" class="absolute bottom-4 left-2 z-40">
                <button @click.stop="onAdClick"
                    class="px-2.5 py-1 bg-black/60 hover:bg-black/80 text-white text-xs font-medium rounded border border-white/40 transition-all inline-flex items-center gap-1">
                    Learn More ↗
                </button>
            </div>
        </div>

        <!-- Progress Bar (local ads only) -->
        <div v-if="!isVastAd" class="h-1 bg-white/10">
            <div v-if="skipAfter > 0 && !canSkip"
                class="h-full bg-yellow-500 transition-all duration-1000 ease-linear"
                :style="{ width: Math.min(100, (adElapsed / skipAfter) * 100) + '%' }">
            </div>
            <div v-else class="h-full bg-yellow-500" style="width:100%"></div>
        </div>
    </div>
</template>
