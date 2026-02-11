import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

/**
 * Lightweight i18n composable for HubTube.
 *
 * Translations are loaded server-side via Inertia shared props (page.props.locale.translations).
 * This is the proven Laravel + Inertia pattern — no client-side JSON loading needed.
 *
 * Usage:
 *   const { t, localizedUrl } = useI18n();
 *   t('nav.home')                    // → "Inicio" (if locale is es)
 *   t('video.views', { count: 5 })   // → "5 vistas"
 *   localizedUrl('/trending')         // → "/es/trending"
 */

const RTL_LOCALES = ['ar', 'he'];

export function useI18n() {
    const page = usePage();

    // All locale data comes from server-side Inertia shared props
    const locale = computed(() => page.props.locale?.current || 'en');
    const defaultLocale = computed(() => page.props.locale?.default || 'en');
    const enabledLanguages = computed(() => page.props.locale?.languages || {});
    const isTranslationEnabled = computed(() => page.props.locale?.enabled || false);
    const localePrefix = computed(() => page.props.locale?.prefix || '');
    const translations = computed(() => page.props.locale?.translations || {});
    const isTranslated = computed(() => locale.value !== defaultLocale.value);
    const localeDir = computed(() => RTL_LOCALES.includes(locale.value) ? 'rtl' : 'ltr');

    // Build supportedLocales array from server data
    const supportedLocales = computed(() => {
        return Object.entries(enabledLanguages.value).map(([code, data]) => ({
            code,
            label: data.native || data.name,
            flag: data.flag || '',
            dir: RTL_LOCALES.includes(code) ? 'rtl' : 'ltr',
        }));
    });

    // Set document lang/dir attributes reactively
    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale.value;
        document.documentElement.dir = localeDir.value;
    }

    /**
     * Translate a dot-notation key, with optional interpolation.
     * Falls back to the key itself if not found (which shows the English text in templates).
     */
    const t = (key, params = {}) => {
        const parts = key.split('.');
        let value = translations.value;
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
     * Switch language.
     * Sets the locale in the session via POST, then navigates to the locale-prefixed
     * version of the current page. Uses full page reload to ensure all server-side
     * data (translations, locale prefix) is refreshed.
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
                // Full reload to pick up new server-side translations
                window.location.href = data.redirect;
            }
        } catch (e) {
            // Fallback: navigate to locale home
            const target = code === defaultLocale.value ? '/' : `/${code}`;
            window.location.href = target;
        }
    };

    /**
     * Build a localized URL path.
     * Prepends the locale prefix (e.g. "/es") to internal paths.
     * Skips API, admin, and livewire routes.
     */
    const localizedUrl = (path) => {
        if (!isTranslated.value) return path;
        if (path.startsWith('/api/') || path.startsWith('/admin') || path.startsWith('/livewire')) {
            return path;
        }
        // For root path "/", just return the prefix (e.g. "/es" not "/es/")
        if (path === '/') return localePrefix.value || '/';
        return `${localePrefix.value}${path}`;
    };

    return {
        t,
        locale,
        localeDir,
        defaultLocale,
        isTranslated,
        isTranslationEnabled,
        setLocale,
        localizedUrl,
        localePrefix,
        supportedLocales,
        enabledLanguages,
    };
}
