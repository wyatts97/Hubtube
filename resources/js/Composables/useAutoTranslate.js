import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Auto-translate video titles/descriptions when the user is viewing
 * the site in a non-default locale.
 *
 * Usage:
 *   const { translatedVideos } = useAutoTranslate(videos, ['title']);
 *   // translatedVideos.value contains the videos with translated fields
 */
export function useAutoTranslate(videosRef, fields = ['title']) {
    const page = usePage();
    const translatedVideos = ref([]);
    const isTranslating = ref(false);

    const locale = () => page.props.locale?.current || 'en';
    const defaultLocale = () => page.props.locale?.default || 'en';
    const isEnabled = () => {
        const loc = page.props.locale;
        return loc?.enabled && loc?.current !== loc?.default;
    };

    async function translateBatch(videos) {
        if (!isEnabled() || !videos?.length) {
            translatedVideos.value = videos || [];
            return;
        }

        // Start with original videos immediately (no blank screen)
        translatedVideos.value = videos;

        const ids = videos.map(v => v.id).filter(Boolean);
        if (!ids.length) return;

        isTranslating.value = true;
        try {
            const response = await fetch('/api/translate/batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': page.props.csrf_token,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    type: 'video',
                    ids,
                    fields,
                    locale: locale(),
                }),
            });

            if (response.ok) {
                const data = await response.json();
                if (data.translations?.length) {
                    // Merge translated fields back into original video objects
                    const translationMap = {};
                    for (const t of data.translations) {
                        translationMap[t.id] = t;
                    }

                    translatedVideos.value = videos.map(v => {
                        const t = translationMap[v.id];
                        if (!t) return v;
                        const merged = { ...v };
                        for (const field of fields) {
                            if (t[field]) merged[field] = t[field];
                        }
                        return merged;
                    });
                }
            }
        } catch (e) {
            // Silently fail — show original content
        } finally {
            isTranslating.value = false;
        }
    }

    // Watch for changes in the videos ref
    watch(videosRef, (newVideos) => {
        const list = Array.isArray(newVideos) ? newVideos : (newVideos?.data || []);
        translateBatch(list);
    }, { immediate: true });

    return {
        translatedVideos,
        isTranslating,
    };
}
