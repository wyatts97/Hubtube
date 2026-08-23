import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Lightweight i18n composable for HubTube.
 *
 * Translations are loaded server-side via Inertia shared props
 * (page.props.locale.translations), with the default-locale catalogue supplied
 * alongside as page.props.locale.fallback.
 *
 * Usage:
 *   const { t, localizedUrl } = useI18n();
 *   t('nav.home')                    // → "Inicio" (if locale is es)
 *   t('video.views', { count: 5 })   // → "5 vistas"
 *   localizedUrl('/trending')         // → "/es/trending"
 */

// Plural categories in the order forms are written in a message, filtered per
// locale. English uses [one, other] → "{count} view | {count} views".
const CATEGORY_ORDER = ['zero', 'one', 'two', 'few', 'many', 'other'];

const pluralRulesCache = new Map();
const warnedKeys = new Set();

/**
 * Walk a dot-notation key. Returns undefined for a miss or a non-string leaf,
 * so callers can distinguish "absent" from "present but empty".
 */
function lookup(source, key) {
    let value = source;

    for (const part of key.split('.')) {
        if (value && typeof value === 'object' && part in value) {
            value = value[part];
        } else {
            return undefined;
        }
    }

    return typeof value === 'string' ? value : undefined;
}

function pluralRules(locale) {
    if (!pluralRulesCache.has(locale)) {
        try {
            pluralRulesCache.set(locale, new Intl.PluralRules(locale));
        } catch {
            pluralRulesCache.set(locale, null);
        }
    }

    return pluralRulesCache.get(locale);
}

/**
 * Pick the plural form for `count` from a pipe-separated message.
 *
 * Messages with more forms than the locale needs are clamped to the last one,
 * so a two-form English string still behaves sensibly under a locale with
 * four categories (translators can supply the extra forms later).
 */
function selectPlural(message, count, locale) {
    if (!message.includes('|')) return message;

    const forms = message.split('|').map((form) => form.trim());
    if (!Number.isFinite(count)) return forms[0];

    const rules = pluralRules(locale);
    if (!rules) return forms[count === 1 ? 0 : forms.length - 1];

    const categories = CATEGORY_ORDER.filter((category) =>
        rules.resolvedOptions().pluralCategories.includes(category),
    );
    const index = categories.indexOf(rules.select(count));

    return forms[Math.min(index === -1 ? forms.length - 1 : index, forms.length - 1)];
}

function interpolate(message, params) {
    return message.replace(/\{(\w+)\}/g, (_, name) => (name in params ? params[name] : `{${name}}`));
}

function warnMissingKey(key) {
    if (!import.meta.env.DEV || warnedKeys.has(key)) return;
    warnedKeys.add(key);
    console.warn(`[i18n] Missing translation key "${key}" — add it to resources/js/i18n/en.json`);
}

export function useI18n() {
    const page = usePage();

    // All locale data comes from server-side Inertia shared props
    const locale = computed(() => page.props.locale?.current || 'en');
    const defaultLocale = computed(() => page.props.locale?.default || 'en');
    const enabledLanguages = computed(() => page.props.locale?.languages || {});
    const isTranslationEnabled = computed(() => page.props.locale?.enabled || false);
    const localePrefix = computed(() => page.props.locale?.prefix || '');
    const translations = computed(() => page.props.locale?.translations || {});
    const fallbackTranslations = computed(() => page.props.locale?.fallback || {});
    const isTranslated = computed(() => locale.value !== defaultLocale.value);
    // Direction comes from the server (TranslationService::RTL_LOCALES) so the
    // RTL locale list lives in exactly one place.
    const localeDir = computed(() => page.props.locale?.dir || 'ltr');

    // Build supportedLocales array from server data
    const supportedLocales = computed(() => {
        return Object.entries(enabledLanguages.value).map(([code, data]) => ({
            code,
            label: data.native || data.name,
            flag: data.flag || '',
            dir: data.dir || 'ltr',
        }));
    });

    /**
     * Translate a dot-notation key, with plural selection and interpolation.
     *
     * Resolution order is current locale → default locale → the key itself.
     * The fallback step is what keeps a locale with a partial (or missing)
     * JSON file rendering English instead of raw dot-paths; reaching the third
     * step means the key is absent from en.json too, which is a bug, so it is
     * surfaced loudly in dev rather than silently papered over.
     *
     * Plural form is chosen from `params.n` when present, else `params.count`.
     * Pass `n` whenever `count` is a display string rather than a number —
     * formatViews() yields "1.5K", which would otherwise select the singular.
     */
    const t = (key, params = {}) => {
        const message = lookup(translations.value, key) ?? lookup(fallbackTranslations.value, key);

        if (message === undefined) {
            warnMissingKey(key);
            return key;
        }

        const selector = Number(params.n ?? params.count);

        return interpolate(selectPlural(message, selector, locale.value), params);
    };

    /**
     * Switch language.
     * Sets the locale in the session via POST, then navigates to the locale-prefixed
     * version of the current page. Uses full page reload to ensure all server-side
     * data (translations, locale prefix) is refreshed.
     */
    const setLocale = async (code) => {
        try {
            const currentPath = window.location.pathname;
            const response = await fetch('/api/locale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': page.props.csrf_token,
                },
                body: JSON.stringify({ locale: code, current_path: currentPath }),
            });
            const data = await response.json();
            if (data.redirect) {
                // Full reload to pick up new server-side translations
                window.location.href = data.redirect;
            }
        } catch (e) {
            // Fallback: stay on current page with locale prefix
            const path = window.location.pathname;
            const stripped = path.replace(/^\/[a-z]{2,3}(\/|$)/, '/');
            const target = code === defaultLocale.value ? (stripped || '/') : `/${code}${stripped === '/' ? '' : stripped}`;
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
