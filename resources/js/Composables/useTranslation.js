import { computed, ref, reactive } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Content translation (video titles and descriptions) for non-default locales.
 *
 * One shared cache, keyed "type:id:locale". useGlobalAutoTranslate fills it
 * from every page's props, useAutoTranslate from videos loaded later (infinite
 * scroll), and VideoCard reads it. Interface strings live in useI18n instead.
 */
const translationCache = reactive({});

/** "id:locale" pairs with a title request in flight, so two callers don't ask twice. */
const pendingTitles = new Set();

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

/**
 * Fetch translated titles for any of these videos not already cached or
 * requested. The server only returns stored translations (it queues the
 * rest), so this is quick and never blocks the page.
 */
export async function fetchVideoTitles(ids, locale) {
    const wanted = [...new Set(ids)].filter((id) =>
        id && !translationCache[`video:${id}:${locale}`] && !pendingTitles.has(`${id}:${locale}`)
    );
    if (!wanted.length) return;

    wanted.forEach((id) => pendingTitles.add(`${id}:${locale}`));
    try {
        const response = await fetch('/api/translate/batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ type: 'video', ids: wanted, fields: ['title'], locale }),
        });
        if (!response.ok) return;

        const data = await response.json();
        for (const t of data.translations || []) {
            const entry = {};
            if (t.title) entry.title = t.title;
            if (t.translated_slug) entry.translated_slug = t.translated_slug;
            if (Object.keys(entry).length) {
                translationCache[`video:${t.id}:${locale}`] = { id: t.id, ...entry };
            }
        }
    } catch (e) {
        // Show the original titles.
    } finally {
        wanted.forEach((id) => pendingTitles.delete(`${id}:${locale}`));
    }
}

export function useTranslation() {
    const page = usePage();
    const translating = ref(false);

    const currentLocale = computed(() => page.props.locale?.current || 'en');
    const isTranslated = computed(() => {
        const loc = page.props.locale;
        return !!loc && loc.current !== loc.default;
    });

    /**
     * Translate one item's fields on demand. A cached entry only counts if it
     * holds every requested field: the title-only batch cache must not stop a
     * watch page from getting its description translated.
     */
    async function translateItem(type, id, fields) {
        if (!isTranslated.value) return null;

        const cacheKey = `${type}:${id}:${currentLocale.value}`;
        const cached = translationCache[cacheKey];
        if (cached && fields.every((field) => field in cached)) {
            return cached;
        }

        translating.value = true;
        try {
            const response = await fetch('/api/translate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ type, id, fields, locale: currentLocale.value }),
            });
            const data = await response.json();
            if (data.translations) {
                translationCache[cacheKey] = { ...cached, ...data.translations };
                return translationCache[cacheKey];
            }
        } catch (e) {
            // Show the original text.
        } finally {
            translating.value = false;
        }
        return null;
    }

    /** Translated titles for a list of videos, into the shared cache. */
    async function translateBatch(type, ids) {
        if (!isTranslated.value || !ids.length || type !== 'video') return;
        await fetchVideoTitles(ids, currentLocale.value);
    }

    /** A cached translated field, or the fallback. */
    function getTranslated(type, id, field, fallback) {
        if (!isTranslated.value) return fallback;
        return translationCache[`${type}:${id}:${currentLocale.value}`]?.[field] || fallback;
    }

    return {
        currentLocale,
        isTranslated,
        translating,
        translateItem,
        translateBatch,
        getTranslated,
    };
}
