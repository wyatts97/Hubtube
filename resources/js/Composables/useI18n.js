import { ref, computed } from 'vue';

/**
 * Lightweight i18n composable for HubTube.
 *
 * Usage:
 *   const { t } = useI18n();
 *   t('nav.home')        // → "Home"
 *   t('video.views', { count: 5 })  // → "5 views"
 *
 * Adding a new language:
 *   1. Create resources/js/i18n/<code>.json  (copy en.json as template)
 *   2. Add the code + label to SUPPORTED_LOCALES below
 */

const SUPPORTED_LOCALES = [
    { code: 'en', label: 'English' },
    { code: 'es', label: 'Español' },
    { code: 'fr', label: 'Français' },
    { code: 'de', label: 'Deutsch' },
    { code: 'pt', label: 'Português' },
    { code: 'ar', label: 'العربية', dir: 'rtl' },
    { code: 'zh', label: '中文' },
    { code: 'ja', label: '日本語' },
    { code: 'ko', label: '한국어' },
    { code: 'hi', label: 'हिन्दी' },
];

const currentLocale = ref(localStorage.getItem('hubtube_locale') || 'en');
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

// Initial load
loadLocale(currentLocale.value).then(() => { loaded.value = true; });

export function useI18n() {
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

    const setLocale = async (code) => {
        currentLocale.value = code;
        localStorage.setItem('hubtube_locale', code);
        document.documentElement.lang = code;
        const loc = SUPPORTED_LOCALES.find(l => l.code === code);
        document.documentElement.dir = loc?.dir || 'ltr';
        await loadLocale(code);
    };

    const locale = computed(() => currentLocale.value);
    const localeDir = computed(() => {
        const loc = SUPPORTED_LOCALES.find(l => l.code === currentLocale.value);
        return loc?.dir || 'ltr';
    });

    return {
        t,
        locale,
        localeDir,
        setLocale,
        loaded,
        supportedLocales: SUPPORTED_LOCALES,
    };
}
