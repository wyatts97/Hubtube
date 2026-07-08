<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import { useFetch } from '@/Composables/useFetch';
import { useImaAd } from '@/Composables/useImaAd';
import { Play } from 'lucide-vue-next';

const emit = defineEmits(['load-more']);

const { get, post } = useFetch();
const containerRef = ref(null);
const videoRef = ref(null);
const ad = ref(null);
const adType = ref(null);
const loading = ref(true);
const autoplayBlocked = ref(false);
const localVideoRef = ref(null);
const htmlRef = ref(null);

const { play: playIma, destroy: destroyIma } = useImaAd(containerRef, videoRef, {
    onStart: () => { loading.value = false; fireImpression(); },
    onComplete: () => { /* slide remains visible until user swipes past */ },
    onError: () => { loading.value = false; },
    fireImpression: () => fireImpression(),
});

const isVast = computed(() => ad.value?.type === 'vast' || ad.value?.type === 'vpaid');
const isMp4 = computed(() => ad.value?.type === 'mp4');
const isHtml = computed(() => ad.value?.type === 'html');

const fetchAd = async () => {
    loading.value = true;
    const { ok, data } = await get('/api/video-ads');
    if (ok && data?.ads?.shorts?.length) {
        ad.value = data.ads.shorts[0];
        adType.value = ad.value.type;

        if (isVast.value) {
            nextTick(() => playIma(ad.value));
        } else if (isMp4.value) {
            loading.value = false;
            nextTick(() => tryPlayLocal());
        } else if (isHtml.value) {
            loading.value = false;
            nextTick(() => injectHtml());
        } else {
            loading.value = false;
        }
    } else {
        loading.value = false;
    }
};

const fireImpression = () => {
    if (!ad.value?.id) return;
    post('/api/ad-impression', { ad_id: ad.value.id }).catch(() => {});
};

const fireClick = () => {
    if (!ad.value?.id) return;
    post('/api/ad-click', { ad_id: ad.value.id }).catch(() => {});
};

const onAdClick = () => {
    if (ad.value?.click_url) {
        fireClick();
        window.open(ad.value.click_url, '_blank', 'noopener,noreferrer');
    }
};

const tryPlayLocal = () => {
    if (!localVideoRef.value) return;
    const v = localVideoRef.value;
    v.muted = true;
    v.play().then(() => {
        autoplayBlocked.value = false;
        fireImpression();
    }).catch(() => {
        autoplayBlocked.value = true;
    });
};

const manualPlay = () => {
    if (!localVideoRef.value) return;
    localVideoRef.value.muted = false;
    localVideoRef.value.play().catch(() => {});
    autoplayBlocked.value = false;
};

const injectHtml = () => {
    if (!htmlRef.value || !ad.value?.content) return;
    htmlRef.value.innerHTML = ad.value.content;
    htmlRef.value.querySelectorAll('script').forEach(old => {
        const s = document.createElement('script');
        Array.from(old.attributes).forEach(a => s.setAttribute(a.name, a.value));
        s.textContent = old.textContent;
        old.parentNode.replaceChild(s, old);
    });
    fireImpression();
};

let observer = null;

onMounted(() => {
    fetchAd();

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                const v = localVideoRef.value || videoRef.value;
                if (!v) return;
                if (entry.isIntersecting) {
                    v.play?.().catch(() => {});
                } else {
                    v.pause?.();
                }
            });
        },
        { threshold: 0.5 }
    );

    if (containerRef.value) observer.observe(containerRef.value);
});

onUnmounted(() => {
    if (observer) observer.disconnect();
    destroyIma();
});
</script>

<template>
    <div ref="containerRef" class="relative w-full h-full bg-black overflow-hidden">
        <!-- Ad label -->
        <div class="absolute top-3 left-3 z-30">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-500 text-black uppercase tracking-wide">Ad</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center z-20">
            <div class="w-10 h-10 border-3 border-white/30 border-t-white rounded-full animate-spin"></div>
        </div>

        <!-- VAST/VPAID via IMA -->
        <template v-if="isVast">
            <video ref="videoRef" class="w-full h-full object-contain" playsinline style="display: block;"></video>
            <div class="absolute inset-0" style="pointer-events: auto;"></div>
        </template>

        <!-- Local MP4 -->
        <template v-if="isMp4">
            <video
                ref="localVideoRef"
                :src="ad.content"
                class="w-full h-full object-contain"
                loop
                playsinline
                muted
                preload="auto"
            />
            <div
                v-if="ad.click_url"
                class="absolute inset-0 z-10 cursor-pointer"
                @click.stop="onAdClick"
            ></div>
            <div
                v-if="autoplayBlocked"
                class="absolute inset-0 flex items-center justify-center bg-black/40 cursor-pointer z-20"
                @click.stop="manualPlay"
            >
                <div class="w-14 h-14 rounded-full bg-white/20 backdrop-blur-sm border border-white/40 flex items-center justify-center">
                    <Play class="w-6 h-6 text-white ml-1" fill="currentColor" />
                </div>
            </div>
        </template>

        <!-- HTML / banner -->
        <div v-if="isHtml" ref="htmlRef" class="w-full h-full flex items-center justify-center"></div>
        <div
            v-if="isHtml && ad.click_url"
            class="absolute bottom-4 left-4 z-20"
        >
            <button
                @click.stop="onAdClick"
                class="px-3 py-1.5 bg-black/60 hover:bg-black/80 text-white text-xs font-medium rounded border border-white/40"
            >
                Learn More ↗
            </button>
        </div>
    </div>
</template>
