/**
 * Image attribute helpers for thumbnails and avatars.
 *
 * Usage:
 *   const { thumbnailProps, avatarProps } = useOptimizedImage();
 *
 *   <img v-bind="thumbnailProps(video.thumbnail_url, video.thumbnail_alt)" />
 *   <img v-bind="avatarProps(user.avatar, 40, user.avatar_alt)" />
 *
 * There is no srcset: nothing on the server resizes images, so every
 * candidate would be the same full-size file under a different cache key.
 */

/**
 * Loading priority for the card at `index` in a grid. The first row is
 * usually on screen at load, so it shouldn't wait for lazy loading; the very
 * first thumbnail is the likely LCP element.
 */
export function cardPriority(index, eagerCount = 6) {
    if (index === 0) return 'high';
    return index < eagerCount ? 'eager' : '';
}

export function useOptimizedImage() {
    /**
     * Pass the server-generated alt: video.thumbnail_alt.
     *
     * @param {string} priority - '' (lazy), 'eager', or 'high' (eager + fetchpriority)
     */
    const thumbnailProps = (src, alt = '', priority = '') => {
        const props = {
            src: src || '',
            alt,
            loading: priority ? 'eager' : 'lazy',
            decoding: 'async',
        };
        if (priority === 'high') {
            props.fetchpriority = 'high';
        }
        return props;
    };

    /**
     * Pass the server-generated alt: user.avatar_alt, which the User model
     * appends.
     *
     * @param {string} src - Avatar URL
     * @param {number} displaySize - Display size in pixels (e.g. 40, 64)
     * @param {string} alt - Alt text, normally user.avatar_alt
     */
    const avatarProps = (src, displaySize = 40, alt = '') => ({
        src: src || '',
        alt,
        loading: 'lazy',
        decoding: 'async',
        width: displaySize,
        height: displaySize,
    });

    return {
        thumbnailProps,
        avatarProps,
    };
}
