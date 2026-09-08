<script setup>
/**
 * Public site chrome.
 *
 * This file used to be ~1000 lines holding the header, category bar, sidebar,
 * footer, mobile dock, search overlay and login dialog inline. Each of those now
 * lives in Layouts/Partials, and what remains here is composition plus the
 * genuinely cross-cutting concerns: sidebar state, flash-message toasts, and the
 * global overlays.
 */
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useToast } from '@/Composables/useToast';
import { useGlobalAutoTranslate } from '@/Composables/useGlobalAutoTranslate';
import SiteHeader from '@/Layouts/Partials/SiteHeader.vue';
import CategoryBar from '@/Layouts/Partials/CategoryBar.vue';
import SiteSidebar from '@/Layouts/Partials/SiteSidebar.vue';
import SiteFooter from '@/Layouts/Partials/SiteFooter.vue';
import MobileDock from '@/Layouts/Partials/MobileDock.vue';
import MobileSearchOverlay from '@/Layouts/Partials/MobileSearchOverlay.vue';
import LoginDialog from '@/Layouts/Partials/LoginDialog.vue';
import ToastContainer from '@/Components/ToastContainer.vue';
import ImpersonationBar from '@/Components/ImpersonationBar.vue';
import AgeVerificationModal from '@/Components/AgeVerificationModal.vue';
import AdInterstitial from '@/Components/AdInterstitial.vue';
import StickyBannerAd from '@/Components/StickyBannerAd.vue';

const page = usePage();
const toast = useToast();

useTheme();
useGlobalAutoTranslate();

const sidebarCollapsed = ref(false);
const showMobileSearch = ref(false);
const showLogin = ref(false);
const footerRef = ref(null);
const headerRef = ref(null);

/**
 * Indent that clears the fixed sidebar. Applied as a class rather than an inline
 * style because it must only apply from `lg` up, where the sidebar exists — an
 * inline style cannot carry a media query, and rendering the slot twice to work
 * around that would mount every page component twice.
 */
const contentInset = computed(() =>
    sidebarCollapsed.value ? 'inset-sidebar-collapsed' : 'inset-sidebar'
);

const flash = computed(() => page.props.flash);

watch(flash, (next) => {
    if (!next) return;
    if (next.success) toast.success(next.success);
    if (next.error) toast.error(next.error);
    if (next.warning) toast.warning(next.warning);
    if (next.info) toast.info(next.info);
}, { immediate: true, deep: true });

// Stale autocomplete results must not survive a navigation.
watch(() => page.url, () => headerRef.value?.clearSuggestions?.());
</script>

<template>
    <div class="min-h-screen bg-bg-primary">
        <SiteHeader
            ref="headerRef"
            @open-mobile-search="showMobileSearch = true"
            @open-login="showLogin = true"
            @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
        >
            <template #below>
                <CategoryBar />
            </template>
        </SiteHeader>

        <SiteSidebar :collapsed="sidebarCollapsed" />

        <!-- Top padding clears the fixed header: the bar alone on mobile, the bar
             plus the category strip from md up, where that strip is visible. -->
        <main
            class="pt-header md:pt-[calc(var(--spacing-header)+var(--spacing-catbar))] transition-[padding] duration-200"
            :class="contentInset"
        >
            <div class="page-shell">
                <slot />
            </div>
        </main>

        <SiteFooter ref="footerRef" :class="contentInset" />

        <MobileDock :footer-el="footerRef" @open-search="showMobileSearch = true" />
        <MobileSearchOverlay v-model="showMobileSearch" />
        <LoginDialog v-model="showLogin" />

        <ImpersonationBar />
        <ToastContainer />
        <AgeVerificationModal />
        <AdInterstitial />
        <StickyBannerAd :config="page.props.app?.sticky_banner" />
    </div>
</template>
