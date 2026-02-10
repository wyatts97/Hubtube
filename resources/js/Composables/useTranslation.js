import { computed, ref, reactive } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useFetch } from '@/Composables/useFetch';

const translationCache = reactive({});

export function useTranslation() {
    const page = usePage();
    const { post } = useFetch();
    const translating = ref(false);

    const localeData = computed(() => page.props.locale || {
        current: 'en',
        default: 'en',
        languages: {},
        enabled: false,
    });

    const currentLocale = computed(() => localeData.value.current);
    const defaultLocale = computed(() => localeData.value.default);
    const languages = computed(() => localeData.value.languages);
    const isTranslationEnabled = computed(() => localeData.value.enabled);
    const isTranslated = computed(() => currentLocale.value !== defaultLocale.value);

    /**
     * Get the locale prefix for URLs (empty string for default locale).
     */
    const localePrefix = computed(() => {
        if (currentLocale.value === defaultLocale.value) return '';
        return `/${currentLocale.value}`;
    });

    /**
     * Build a localized URL.
     */
    function localizedUrl(path) {
        if (!isTranslated.value) return path;
        // Don't prefix API routes, admin routes, or already-prefixed routes
        if (path.startsWith('/api/') || path.startsWith('/admin') || path.startsWith('/livewire')) {
            return path;
        }
        return `/${currentLocale.value}${path}`;
    }

    /**
     * Switch to a different language.
     */
    async function switchLanguage(locale) {
        try {
            const response = await fetch('/api/locale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': page.props.csrf_token,
                },
                body: JSON.stringify({ locale }),
            });
            const data = await response.json();
            if (data.redirect) {
                // Use full page navigation to apply the new locale
                window.location.href = data.redirect;
            }
        } catch (e) {
            console.error('Failed to switch language:', e);
        }
    }

    /**
     * Translate a single item's fields on-demand.
     */
    async function translateItem(type, id, fields) {
        if (!isTranslated.value) return null;

        const cacheKey = `${type}:${id}:${currentLocale.value}`;
        if (translationCache[cacheKey]) {
            return translationCache[cacheKey];
        }

        translating.value = true;
        try {
            const response = await fetch('/api/translate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': page.props.csrf_token,
                },
                body: JSON.stringify({
                    type,
                    id,
                    fields,
                    locale: currentLocale.value,
                }),
            });
            const data = await response.json();
            if (data.translations) {
                translationCache[cacheKey] = data.translations;
                return data.translations;
            }
        } catch (e) {
            console.error('Translation failed:', e);
        } finally {
            translating.value = false;
        }
        return null;
    }

    /**
     * Batch translate multiple items.
     */
    async function translateBatch(type, ids, fields) {
        if (!isTranslated.value || !ids.length) return {};

        // Filter out already-cached items
        const uncachedIds = ids.filter(id => !translationCache[`${type}:${id}:${currentLocale.value}`]);

        if (uncachedIds.length > 0) {
            translating.value = true;
            try {
                const response = await fetch('/api/translate/batch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': page.props.csrf_token,
                    },
                    body: JSON.stringify({
                        type,
                        ids: uncachedIds,
                        fields,
                        locale: currentLocale.value,
                    }),
                });
                const data = await response.json();
                if (data.translations) {
                    data.translations.forEach(item => {
                        translationCache[`${type}:${item.id}:${currentLocale.value}`] = item;
                    });
                }
            } catch (e) {
                console.error('Batch translation failed:', e);
            } finally {
                translating.value = false;
            }
        }

        // Return all from cache
        const result = {};
        ids.forEach(id => {
            const cached = translationCache[`${type}:${id}:${currentLocale.value}`];
            if (cached) result[id] = cached;
        });
        return result;
    }

    /**
     * Get a translated field value for a cached item.
     */
    function getTranslated(type, id, field, fallback) {
        if (!isTranslated.value) return fallback;
        const cached = translationCache[`${type}:${id}:${currentLocale.value}`];
        return cached?.[field] || fallback;
    }

    return {
        currentLocale,
        defaultLocale,
        languages,
        isTranslationEnabled,
        isTranslated,
        localePrefix,
        translating,
        localizedUrl,
        switchLanguage,
        translateItem,
        translateBatch,
        getTranslated,
    };
}
