<script setup>
/**
 * AdInterstitial — full-screen overlay ad shown every N Inertia page navigations.
 *
 * Config pulled from shared Inertia props (app.interstitial):
 *   enabled    — master toggle (admin setting)
 *   frequency  — show every N page views (default 5)
 *   skipDelay  — seconds before close button appears (default 5)
 *   code       — desktop HTML ad code
 *   mobileCode — mobile HTML ad code (falls back to code)
 *
 * Page view count is stored in sessionStorage so it resets on tab close.
 * Never shown on the home page (/), login, register, or admin routes.
 */
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import AdSlot from '@/Components/AdSlot.vue';

const page = usePage();
const config = computed(() => page.props.app?.interstitial || {});

const STORAGE_KEY = 'ht_page_views';
const EXEMPT_PATHS = ['/', '/login', '/register', '/forgot-password', '/reset-password', '/install', '/admin'];

const visible = ref(false);
const countdown = ref(0);
let countdownTimer = null;

const isMobile = () => window.innerWidth < 768;

const adHtml = computed(() => {
    if (!config.value.enabled) return '';
    const mobile = config.value.mobileCode?.trim();
    const desktop = config.value.code?.trim();
    if (isMobile() && mobile) return mobile;
    return desktop || '';
});

const isExemptPath = (path) => {
    return EXEMPT_PATHS.some(p => path === p || path.startsWith('/admin') || path.startsWith('/install'));
};

const getPageViews = () => parseInt(sessionStorage.getItem(STORAGE_KEY) || '0', 10);
const setPageViews = (n) => sessionStorage.setItem(STORAGE_KEY, String(n));

const show = () => {
    visible.value = true;
    countdown.value = config.value.skipDelay || 5;

    if (countdown.value > 0) {
        countdownTimer = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }, 1000);
    }
};

const close = () => {
    visible.value = false;
    clearInterval(countdownTimer);
    countdownTimer = null;
};

const checkAndShow = (path) => {
    if (!config.value.enabled || !adHtml.value || isExemptPath(path)) return;

    const views = getPageViews() + 1;
    setPageViews(views);

    const freq = config.value.frequency || 5;
    if (views % freq === 0) {
        nextTick(() => show());
    }
};

let routerListener = null;

onMounted(() => {
    routerListener = router.on('navigate', (event) => {
        const path = event.detail.page.url || window.location.pathname;
        checkAndShow(path);
    });
});

onUnmounted(() => {
    if (routerListener) routerListener();
    clearInterval(countdownTimer);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="interstitial">
            <div
                v-if="visible && adHtml"
                class="fixed inset-0 z-[9999] flex items-center justify-center"
                style="background-color: rgba(0,0,0,0.85);"
                @click.self="countdown <= 0 ? close() : null"
            >
                <div class="relative w-full max-w-lg mx-4">
                    <!-- Skip / close button -->
                    <div class="absolute -top-10 right-0 flex items-center gap-2">
                        <span v-if="countdown > 0" class="text-white/70 text-sm">
                            Skip in {{ countdown }}s
                        </span>
                        <button
                            v-else
                            @click="close"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-white/20 hover:bg-white/30 transition-colors"
                        >
                            <X class="w-3.5 h-3.5" />
                            Close
                        </button>
                    </div>

                    <!-- Ad content -->
                    <div class="rounded-xl overflow-hidden bg-black flex items-center justify-center min-h-[250px]">
                        <AdSlot :html="adHtml" />
                    </div>

                    <!-- Ad label -->
                    <p class="text-center text-white/40 text-xs mt-2">Advertisement</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.interstitial-enter-active,
.interstitial-leave-active {
    transition: opacity 0.25s ease;
}
.interstitial-enter-from,
.interstitial-leave-to {
    opacity: 0;
}
</style>
