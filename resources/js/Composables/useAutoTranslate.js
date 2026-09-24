import { usePage } from '@inertiajs/vue3';
import { useTranslation, fetchVideoTitles } from '@/Composables/useTranslation';

/**
 * Translated video titles for a page's grid, including videos loaded after
 * the page (infinite scroll), which useGlobalAutoTranslate never sees.
 *
 * Reads and fills the shared translation cache, so a video the layout has
 * already requested is not requested again.
 *
 * Usage:
 *   const { translateVideos, tr } = useAutoTranslate();
 *   translateVideos(moreVideos);
 *   tr(video, 'title')  // falls back to the original
 */
export function useAutoTranslate() {
    const page = usePage();
    const { getTranslated } = useTranslation();

    const isEnabled = () => {
        const loc = page.props.locale;
        return loc?.enabled && loc?.current !== loc?.default;
    };

    function translateVideos(videos) {
        if (!isEnabled() || !videos?.length) return;
        fetchVideoTitles(videos.map((v) => v.id), page.props.locale.current);
    }

    function tr(video, field = 'title') {
        return getTranslated('video', video.id, field, video[field]);
    }

    return { translateVideos, tr };
}
