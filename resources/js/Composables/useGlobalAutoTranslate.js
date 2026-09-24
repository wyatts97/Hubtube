import { ref, nextTick, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

/**
 * Global auto-translate for video titles across ALL pages.
 *
 * Runs from AppLayout on every Inertia page visit. Scans page props for
 * video arrays/objects, collects all video IDs, fires one batch translate
 * request, and populates the shared useTranslation cache so VideoCard's
 * getTranslated() works everywhere — no per-page setup needed.
 *
 * The request only ever returns translations that are already stored; the
 * server queues the rest. So this resolves in milliseconds and untranslated
 * titles simply render in the source language until a job catches up — there
 * is nothing to block the page for.
 */

import { fetchVideoTitles } from '@/Composables/useTranslation';

const isTranslating = ref(false);

/**
 * Recursively find all video-like objects in page props.
 * A "video" is any object with { id, title, slug } properties.
 */
function extractVideos(obj, depth = 0, seen = new Set()) {
    if (depth > 6 || !obj || typeof obj !== 'object') return [];
    // Avoid circular references
    const key = typeof obj === 'object' ? obj : null;
    if (key && seen.has(key)) return [];
    if (key) seen.add(key);

    const videos = [];

    if (Array.isArray(obj)) {
        for (const item of obj) {
            videos.push(...extractVideos(item, depth + 1, seen));
        }
        return videos;
    }

    // Check if this object looks like a video
    if (obj.id && obj.title && obj.slug && typeof obj.id === 'number') {
        videos.push(obj);
    }

    // Recurse into known container patterns: .data (pagination), nested arrays
    for (const k of Object.keys(obj)) {
        // Skip heavy/irrelevant props
        if (['seo', 'theme', 'locale', 'auth', 'flash', 'csrf_token', 'menuItems', 'app', 'errors'].includes(k)) continue;
        const val = obj[k];
        if (Array.isArray(val)) {
            videos.push(...extractVideos(val, depth + 1, seen));
        } else if (val && typeof val === 'object' && val.data) {
            // Paginated result
            videos.push(...extractVideos(val.data, depth + 1, seen));
        }
    }

    return videos;
}

async function translatePageVideos(page) {
    const loc = page.props.locale;
    if (!loc?.enabled || loc.current === loc.default) return;

    isTranslating.value = true;
    try {
        await fetchVideoTitles(extractVideos(page.props).map((v) => v.id), loc.current);
    } finally {
        isTranslating.value = false;
    }
}

export function useGlobalAutoTranslate() {
    const page = usePage();

    // Translate on initial mount
    nextTick(() => translatePageVideos(page));

    // Translate on every Inertia page navigation
    const removeListener = router.on('navigate', () => {
        nextTick(() => translatePageVideos(page));
    });

    onUnmounted(() => {
        if (typeof removeListener === 'function') removeListener();
    });

    return { isTranslating };
}
