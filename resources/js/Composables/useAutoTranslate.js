import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Auto-translate video titles/descriptions when the user is viewing
 * the site in a non-default locale.
 *
 * Usage:
 *   const { translated, translateVideos } = useAutoTranslate(['title']);
 *   onMounted(() => translateVideos(props.featuredVideos));
 *   // Use translated.value in template — falls back to originals instantly
 */
export function useAutoTranslate(fields = ['title']) {
    const page = usePage();
    const translated = ref({});
    const isTranslating = ref(false);

    const getLocale = () => page.props.locale?.current || 'en';
    const isEnabled = () => {
        const loc = page.props.locale;
        return loc?.enabled && loc?.current !== loc?.default;
    };

    /**
     * Translate an array of videos. Results are stored in translated ref
     * keyed by video ID, e.g. translated.value[22] = { title: 'Translated Title' }
     */
    async function translateVideos(videos) {
        if (!isEnabled() || !videos?.length) return;

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
                    locale: getLocale(),
                }),
            });

            if (response.ok) {
                const data = await response.json();
                if (data.translations?.length) {
                    const map = { ...translated.value };
                    for (const t of data.translations) {
                        const overrides = {};
                        for (const field of fields) {
                            if (t[field]) overrides[field] = t[field];
                        }
                        // Store translated_slug for SEO-friendly locale URLs
                        if (t.translated_slug) {
                            overrides.translated_slug = t.translated_slug;
                        }
                        if (Object.keys(overrides).length) {
                            map[t.id] = overrides;
                        }
                    }
                    translated.value = map;
                }
            }
        } catch (e) {
            // Silently fail — show original content
        } finally {
            isTranslating.value = false;
        }
    }

    /**
     * Get translated field for a video, falling back to original.
     * Usage in template: vTitle(video) instead of video.title
     */
    function tr(video, field = 'title') {
        return translated.value[video.id]?.[field] || video[field];
    }

    return {
        translated,
        isTranslating,
        translateVideos,
        tr,
    };
}
