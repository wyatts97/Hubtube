/**
 * Image optimization composable for HubTube.
 *
 * Provides helpers to generate responsive srcset attributes,
 * lazy-loading props, and WebP source hints for thumbnails and avatars.
 *
 * Usage:
 *   const { thumbnailProps, avatarProps } = useOptimizedImage();
 *
 *   <img v-bind="thumbnailProps(video.thumbnail_url)" />
 *   <img v-bind="avatarProps(user.avatar, 40)" />
 */

const THUMBNAIL_WIDTHS = [320, 480, 640, 960];
const AVATAR_SIZES = [32, 48, 64, 96, 128];

/**
 * Build a srcset string for responsive images.
 * If the src is a storage URL, append width params for server-side resizing.
 * Falls back to the original src if no transform is possible.
 */
function buildSrcset(src, widths) {
    if (!src) return '';

    // If using a CDN or image transform service, build srcset
    // For local storage images, we provide the original at all sizes
    // This is designed to work with a future image CDN/transform service
    if (src.includes('?') || src.startsWith('data:')) {
        return '';
    }

    // For images served from /storage/ we can hint at sizes
    // A server-side middleware or CDN can intercept these
    if (src.startsWith('/storage/') || src.includes('/storage/')) {
        return widths
            .map(w => {
                const separator = src.includes('?') ? '&' : '?';
                return `${src}${separator}w=${w} ${w}w`;
            })
            .join(', ');
    }

    return '';
}

/**
 * Derive a WebP URL from a JPEG/PNG source.
 * Returns empty string if not applicable.
 */
function webpUrl(src) {
    if (!src) return '';
    if (src.match(/\.(jpe?g|png)$/i)) {
        return src.replace(/\.(jpe?g|png)$/i, '.webp');
    }
    return '';
}

export function useOptimizedImage() {
    /**
     * Generate optimized img attributes for video thumbnails.
     */
    const thumbnailProps = (src, alt = '') => {
        const srcset = buildSrcset(src, THUMBNAIL_WIDTHS);
        const props = {
            src: src || '',
            alt,
            loading: 'lazy',
            decoding: 'async',
            // Matches actual grid layout: 1-col mobile (~100vw), 2-col tablet (~50vw), 3-col (~33vw), 4-col desktop (~25vw)
            // Subtract padding/gaps so browser picks a smaller image (PageSpeed: "image larger than displayed")
            sizes: '(max-width: 640px) 45vw, (max-width: 1024px) 33vw, 25vw',
        };
        if (srcset) {
            props.srcset = srcset;
        }
        return props;
    };

    /**
     * Generate optimized img attributes for user avatars.
     * @param {string} src - Avatar URL
     * @param {number} displaySize - Display size in pixels (e.g. 40, 64)
     */
    const avatarProps = (src, displaySize = 40) => {
        const sizes = AVATAR_SIZES.filter(s => s >= displaySize).slice(0, 3);
        const srcset = buildSrcset(src, sizes.length ? sizes : [displaySize]);
        const props = {
            src: src || '',
            loading: 'lazy',
            decoding: 'async',
            width: displaySize,
            height: displaySize,
        };
        if (srcset) {
            props.srcset = srcset;
        }
        return props;
    };

    /**
     * Generate a <picture> source set for WebP with fallback.
     * Returns { webpSrc, fallbackSrc } for use in <picture> elements.
     */
    const pictureSource = (src) => {
        return {
            webpSrc: webpUrl(src),
            fallbackSrc: src || '',
        };
    };

    return {
        thumbnailProps,
        avatarProps,
        pictureSource,
        buildSrcset,
        webpUrl,
    };
}
