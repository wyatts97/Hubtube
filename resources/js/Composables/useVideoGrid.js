import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Returns grid classes that respect the admin-configured mobile grid
 * preference. Default is 2 columns on mobile (set in HandleInertiaRequests
 * middleware).
 *
 * Usage:
 *   const { gridClass, mobileGrid } = useVideoGrid();
 *   <div :class="gridClass">
 */
export function useVideoGrid() {
    const page = usePage();

    const mobileGrid = computed(() =>
        page.props.theme?.mobileVideoGrid === '1' ? 1 : 2
    );

    const gridClass = computed(() =>
        mobileGrid.value === 1
            ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4'
            : 'grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4'
    );

    return { gridClass, mobileGrid };
}
