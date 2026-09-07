<script setup>
/**
 * Site footer: optional ad slot, wordmark, legal links.
 *
 * Exposes its root element so AppLayout can watch its position for the
 * scroll-aware mobile dock (the dock hides rather than covering the legal
 * links, which several jurisdictions require to be reachable).
 */
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@/Composables/useI18n';
import { useSiteLogo } from '@/Composables/useSiteLogo';
import AdSlot from '@/Components/AdSlot.vue';

const page = usePage();
const { t } = useI18n();

const themeSettings = computed(() => page.props.theme || {});

// Follows the active theme exactly like the header does, and mirrors the site
// logo when the admin has that toggle on.
const { footerLogo } = useSiteLogo();

const root = ref(null);
defineExpose({ root });

const legalLinks = computed(() => [
    { href: '/pages/terms-of-service', label: t('footer.terms') },
    { href: '/pages/privacy-policy', label: t('footer.privacy') },
    { href: '/pages/dmca', label: t('footer.dmca') },
    { href: '/dmca-request', label: t('footer.dmca_request') },
    { href: '/pages/community-guidelines', label: t('footer.guidelines') },
    { href: '/pages/cookie-policy', label: t('footer.cookies') },
    { href: '/contact', label: t('footer.contact') },
]);
</script>

<template>
    <footer ref="root" class="mt-10 py-7 px-4 border-t border-border">
        <div class="max-w-5xl mx-auto">
            <div
                v-if="themeSettings.footer_ad_enabled && (themeSettings.footer_ad_code || themeSettings.footer_ad_mobile_code)"
                class="flex justify-center mb-6"
            >
                <AdSlot :html="themeSettings.footer_ad_code" class="hidden sm:block" />
                <AdSlot :html="themeSettings.footer_ad_mobile_code || themeSettings.footer_ad_code" class="sm:hidden" />
            </div>

            <div class="flex justify-center mb-4">
                <a href="/" class="inline-flex items-center gap-2 transition-opacity hover:opacity-80">
                    <img
                        v-if="footerLogo"
                        :src="footerLogo"
                        alt="Site logo"
                        class="h-7 object-contain"
                        loading="lazy"
                    />
                    <span
                        v-else
                        class="font-display text-base font-bold uppercase tracking-tight"
                        :style="{
                            color: themeSettings.site_title_color || 'var(--color-text-secondary)',
                            fontFamily: themeSettings.site_title_font || undefined,
                        }"
                    >{{ themeSettings.site_title || 'HubTube' }}</span>
                </a>
            </div>

            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs" aria-label="Legal">
                <a
                    v-for="link in legalLinks"
                    :key="link.href"
                    :href="link.href"
                    class="text-text-muted hover:text-text-primary transition-colors"
                >{{ link.label }}</a>
            </nav>
        </div>
    </footer>
</template>
