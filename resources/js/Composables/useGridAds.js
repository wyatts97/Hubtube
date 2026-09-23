import { computed } from 'vue';
import { useVideoGrid } from '@/Composables/useVideoGrid';

/**
 * Ad interleaving for content grids.
 *
 * Sponsored cards are the one in-grid ad: image, video or pasted HTML code.
 * They used to share the grid with a separate settings-based "grid ad" that
 * had its own frequency, and the two collided whenever the frequencies lined
 * up. One list and one frequency removes that whole class of problem.
 *
 * Takes props rather than reading usePage(), matching useVirtualGrid(items,
 * options): the ad data arrives as per-page props, and passing them in keeps
 * this testable.
 *
 * @param {object} props Page props carrying `sponsoredCards`, `sponsoredFrequency`
 *   and, on /videos, `adSettings.outstreamFrequency` + `outstreamAds`.
 * @param {object} options
 * @param {number} options.frequency Overrides the admin's frequency. For
 *   interleaving between *blocks* rather than between cells — the subscription
 *   feed groups videos into per-creator sections, so counting sections with the
 *   card-level frequency would place an ad roughly never.
 * @param {number} options.outstreamOffset Index shift for outstream units.
 */
export function useGridAds(props, options = {}) {
    const { mobileGrid } = useVideoGrid();

    const sponsoredFrequency = computed(
        () => options.frequency ?? (parseInt(props.sponsoredFrequency) || 8)
    );

    /** The sponsored card that belongs after the item at `index`, or null. */
    const getSponsoredCard = (index) => {
        const cards = props.sponsoredCards;
        if (!cards?.length) return null;
        const position = index + 1;
        if (position % sponsoredFrequency.value !== 0) return null;
        const slot = position / sponsoredFrequency.value - 1;
        return cards[slot % cards.length] || null;
    };

    const outstreamFrequency = computed(() => parseInt(props.adSettings?.outstreamFrequency) || 6);

    // The original hard-coded a `+ 4` while its comment said "offset by 3".
    // Keeping 4 preserves the shipped spacing.
    const outstreamOffset = options.outstreamOffset ?? 4;

    const getOutstreamAd = (index) => {
        if (!props.outstreamAds?.length) return null;
        // Never put two ads back to back.
        if (getSponsoredCard(index)) return null;
        const position = index + outstreamOffset;
        if (position % outstreamFrequency.value !== 0) return null;
        const adIndex = Math.floor(position / outstreamFrequency.value) - 1;
        if (adIndex < 0) return null;
        return props.outstreamAds[adIndex % props.outstreamAds.length] || null;
    };

    /**
     * Class for an ad cell that should span the row on phones.
     *
     * A two-column mobile grid gets a full-width cell for HTML creatives,
     * because network code squeezed into half a phone screen is unsellable.
     */
    const adCellClass = computed(() =>
        mobileGrid.value === 2 ? 'col-span-2 sm:col-span-1' : 'col-span-1'
    );

    const sponsoredCellClass = (card) => (card?.type === 'html' ? adCellClass.value : '');

    return {
        getSponsoredCard,
        sponsoredCellClass,
        getOutstreamAd,
        adCellClass,
    };
}
