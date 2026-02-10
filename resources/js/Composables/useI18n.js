import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Lightweight i18n composable for HubTube.
 *
 * Usage:
 *   const { t } = useI18n();
 *   t('nav.home')        // → "Home"
 *   t('video.views', { count: 5 })  // → "5 views"
 *
 * Locale is synced with the server via Inertia props (page.props.locale).
 * Switching language navigates to /{locale}/ prefix URL for SEO.
 */

const RTL_LOCALES = ['ar', 'he'];

const currentLocale = ref('en');
const messages = ref({});
const loaded = ref(false);

// Cache loaded locale files
const cache = {};

async function loadLocale(code) {
    if (cache[code]) {
        messages.value = cache[code];
        return;
    }
    try {
        const mod = await import(`../i18n/${code}.json`);
        cache[code] = mod.default || mod;
        messages.value = cache[code];
    } catch {
        // Fallback to English if locale file not found
        if (code !== 'en') {
            await loadLocale('en');
        }
    }
}

export function useI18n() {
    const page = usePage();

    // Sync locale from server-side Inertia props
    const serverLocale = computed(() => page.props.locale?.current || 'en');
    const defaultLocale = computed(() => page.props.locale?.default || 'en');
    const enabledLanguages = computed(() => page.props.locale?.languages || {});
    const isTranslationEnabled = computed(() => page.props.locale?.enabled || false);

    // Build supportedLocales array from server data
    const supportedLocales = computed(() => {
        return Object.entries(enabledLanguages.value).map(([code, data]) => ({
            code,
            label: data.native || data.name,
            flag: data.flag || '',
            dir: RTL_LOCALES.includes(code) ? 'rtl' : 'ltr',
        }));
    });

    // Sync with server locale on mount and changes
    if (serverLocale.value !== currentLocale.value) {
        currentLocale.value = serverLocale.value;
        loadLocale(currentLocale.value).then(() => { loaded.value = true; });
    } else if (!loaded.value) {
        loadLocale(currentLocale.value).then(() => { loaded.value = true; });
    }

    watch(serverLocale, (newLocale) => {
        if (newLocale !== currentLocale.value) {
            currentLocale.value = newLocale;
            loadLocale(newLocale);
            document.documentElement.lang = newLocale;
            document.documentElement.dir = RTL_LOCALES.includes(newLocale) ? 'rtl' : 'ltr';
        }
    });

    /**
     * Translate a dot-notation key, with optional interpolation.
     * Falls back to the key itself if not found.
     */
    const t = (key, params = {}) => {
        const parts = key.split('.');
        let value = messages.value;
        for (const part of parts) {
            if (value && typeof value === 'object' && part in value) {
                value = value[part];
            } else {
                return key; // key not found — return raw key as fallback
            }
        }
        if (typeof value !== 'string') return key;

        // Simple interpolation: {count}, {name}, etc.
        return value.replace(/\{(\w+)\}/g, (_, k) => (k in params ? params[k] : `{${k}}`));
    };

    /**
     * Switch language via server-side locale change (navigates to /{locale}/ URL).
     */
    const setLocale = async (code) => {
        try {
            const response = await fetch('/api/locale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': page.props.csrf_token,
                },
                body: JSON.stringify({ locale: code }),
            });
            const data = await response.json();
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } catch (e) {
            // Fallback: client-side only switch
            currentLocale.value = code;
            document.documentElement.lang = code;
            document.documentElement.dir = RTL_LOCALES.includes(code) ? 'rtl' : 'ltr';
            await loadLocale(code);
        }
    };

    const locale = computed(() => currentLocale.value);
    const localeDir = computed(() => RTL_LOCALES.includes(currentLocale.value) ? 'rtl' : 'ltr');
    const isTranslated = computed(() => currentLocale.value !== defaultLocale.value);

    /**
     * Build a localized URL path.
     */
    const localizedUrl = (path) => {
        if (!isTranslated.value) return path;
        if (path.startsWith('/api/') || path.startsWith('/admin') || path.startsWith('/livewire')) {
            return path;
        }
        return `/${currentLocale.value}${path}`;
    };

    return {
        t,
        locale,
        localeDir,
        defaultLocale,
        isTranslated,
        isTranslationEnabled,
        setLocale,
        loaded,
        localizedUrl,
        supportedLocales,
        enabledLanguages,
    };
}
