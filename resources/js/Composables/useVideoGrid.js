import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Single source for every video grid's column classes.
 *
 * Two admin settings feed it: `mobile_video_grid` (1 or 2 columns on phones) and
 * `video_grid_density`. Density defaults to 'dense' — 5 columns at xl and 6 at
 * 2xl, with tighter gaps — which is the browsing pattern this kind of catalogue
 * actually wants; 'comfortable' restores the previous 4-column maximum for
 * operators who prefer larger thumbnails.
 *
 * Class strings are written out in full rather than composed, because Tailwind
 * scans source text and will not generate a class it never sees literally.
 *
 * Usage:
 *   const { gridClass } = useVideoGrid();
 *   <div :class="gridClass">
 */
export function useVideoGrid() {
    const page = usePage();

    const mobileGrid = computed(() =>
        page.props.theme?.mobileVideoGrid === '1' ? 1 : 2
    );

    const density = computed(() =>
        page.props.theme?.gridDensity === 'comfortable' ? 'comfortable' : 'dense'
    );

    const gridClass = computed(() => {
        const mobile = mobileGrid.value === 1 ? 'grid-cols-1' : 'grid-cols-2';

        if (density.value === 'comfortable') {
            return `grid ${mobile} sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4`;
        }

        return `grid ${mobile} sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2 sm:gap-2.5 lg:gap-3`;
    });

    return { gridClass, mobileGrid, density };
}
