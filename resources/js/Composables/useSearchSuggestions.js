import { computed, ref } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';

/**
 * Search autocomplete state for the header search boxes.
 *
 * Extracted from AppLayout.vue, which drives two inputs — the desktop header
 * search and the mobile search overlay — off one shared set of results.
 *
 * Returns `flat`, the videos and channels concatenated in display order, which
 * is what the Combobox iterates so keyboard navigation and the rendered list
 * can never disagree about ordering.
 */
export function useSearchSuggestions({ minLength = 2, debounceMs = 250 } = {}) {
    const { get } = useFetch();
    const { localizedUrl } = useI18n();

    const suggestions = ref({ videos: [], channels: [] });
    const isLoading = ref(false);
    const isOpen = ref(false);

    const flat = computed(() => [
        ...suggestions.value.videos.map((item) => ({ type: 'video', item })),
        ...suggestions.value.channels.map((item) => ({ type: 'channel', item })),
    ]);

    const hasResults = computed(() => flat.value.length > 0);

    const clear = () => {
        suggestions.value = { videos: [], channels: [] };
        isOpen.value = false;
        isLoading.value = false;
    };

    const fetchSuggestions = async (query) => {
        if (!query || query.length < minLength) {
            clear();
            return;
        }
        isLoading.value = true;
        const { ok, data } = await get(`${localizedUrl('/api/search-suggest')}?q=${encodeURIComponent(query)}`);
        isLoading.value = false;
        if (ok && data) {
            suggestions.value = { videos: data.videos || [], channels: data.channels || [] };
            isOpen.value = true;
        }
    };

    const search = useDebounceFn(fetchSuggestions, debounceMs);

    /** Where a given suggestion navigates to. */
    const urlFor = ({ type, item }) =>
        type === 'video'
            ? localizedUrl(`/${item.slug}`)
            : localizedUrl(`/channel/${item.username}`);

    return { suggestions, flat, hasResults, isLoading, isOpen, search, clear, urlFor };
}
