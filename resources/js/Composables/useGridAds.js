import { computed } from 'vue';
import { useVideoGrid } from '@/Composables/useVideoGrid';

/**
 * Ad interleaving for content grids.
 *
 * This logic was copy-pasted, byte for byte, across Home, Videos/Index,
 * Trending and Categories/Show, with a fifth drifted copy in Search. That
 * duplication was not harmless: it is why Trending shipped without a banner and
 * Search without grid ads — nobody forgot a feature, they just forgot to copy
 * some lines. Centralising it means a new listing page gets the whole set by
 * calling one function.
 *
 * Takes props rather than reading usePage(), matching useVirtualGrid(items,
 * options): the ad data arrives as per-page props, and passing them in keeps
 * this testable.
 *
 * @param {object} props Page props carrying `adSettings`, `sponsoredCards`, `outstreamAds`.
 * @param {object} options
 * @param {number} options.outstreamOffset Index shift so outstream units do not
 *   collide with sponsored cards.
 * @param {number} options.frequency Overrides the admin's grid frequency. For
 *   interleaving between *blocks* rather than between cells — the subscription
 *   feed groups videos into per-creator sections, so counting sections with the
 *   card-level frequency would place an ad roughly never.
 */
export function useGridAds(props, options = {}) {
    const { mobileGrid } = useVideoGrid();

    /**
     * The server sends a real boolean, but settings have historically round
     * -tripped as '1' or 'true' through the key/value store, so accept those
     * too. Shorts deliberately used a stricter `=== true` and silently lost its
     * ads whenever the setting was stored as a string; this is the permissive
     * version, which is the one that behaves.
     */
    const adsEnabled = computed(() => {
        const enabled = props.adSettings?.videoGridEnabled;
        return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
    });

    const gridAds = computed(() => props.adSettings?.videoGridAds || []);
    const adFrequency = computed(
        () => options.frequency ?? (parseInt(props.adSettings?.videoGridFrequency) || 8)
    );

    /**
     * True when an ad cell belongs after the card at `index`.
     *
     * The final card never gets a trailing ad — an ad as the last thing in a
     * grid reads as a broken row rather than as inventory.
     */
    const shouldShowAd = (index, totalLength) => {
        if (!adsEnabled.value || !gridAds.value.length) return false;
        return (index + 1) % adFrequency.value === 0 && index < totalLength - 1;
    };

    const sponsoredFrequency = computed(() => props.sponsoredCards?.[0]?.frequency || 8);

    // Staggered by half the grid-ad interval so a sponsored card and a grid ad
    // never land next to each other.
    const sponsoredOffset = computed(() => Math.floor(adFrequency.value / 2));

    const getSponsoredCard = (index) => {
        if (!props.sponsoredCards?.length) return null;
        const position = index + 1 + sponsoredOffset.value;
        if (position % sponsoredFrequency.value !== 0) return null;
        const cardIndex = Math.floor(position / sponsoredFrequency.value) - 1;
        return props.sponsoredCards[cardIndex % props.sponsoredCards.length] || null;
    };

    const outstreamFrequency = computed(() => parseInt(props.adSettings?.outstreamFrequency) || 6);

    // The original hard-coded a `+ 4` while its comment said "offset by 3".
    // Keeping 4 preserves the shipped spacing; naming it makes the intent —
    // land between the grid ads and the sponsored cards — reviewable, and lets
    // a caller change it without editing the composable.
    const outstreamOffset = options.outstreamOffset ?? 4;

    const getOutstreamAd = (index) => {
        if (!props.outstreamAds?.length) return null;
        const position = index + outstreamOffset;
        if (position % outstreamFrequency.value !== 0) return null;
        const adIndex = Math.floor(position / outstreamFrequency.value) - 1;
        if (adIndex < 0) return null;
        return props.outstreamAds[adIndex % props.outstreamAds.length] || null;
    };

    /**
     * Class for the wrapper around an in-grid ad.
     *
     * A two-column mobile grid gets a full-width ad cell, because a creative
     * squeezed into half a phone screen is unsellable. Callers used to
     * destructure `mobileGrid` themselves just to compute this.
     */
    const adCellClass = computed(() =>
        mobileGrid.value === 2 ? 'col-span-2 sm:col-span-1' : 'col-span-1'
    );

    return {
        adsEnabled,
        gridAds,
        adFrequency,
        shouldShowAd,
        getSponsoredCard,
        getOutstreamAd,
        adCellClass,
    };
}
