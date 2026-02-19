/**
 * Shared formatting utilities for dates and view counts.
 */

/**
 * Returns a human-readable relative time string (e.g. "3 hours ago").
 * Uses Intl.RelativeTimeFormat for locale-aware output when available.
 * @param {string|Date} date
 * @param {string} [locale='en'] - BCP 47 locale code
 * @returns {string}
 */
export function timeAgo(date, locale = 'en') {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    const intervals = [
        { unit: 'year', seconds: 31536000 },
        { unit: 'month', seconds: 2592000 },
        { unit: 'week', seconds: 604800 },
        { unit: 'day', seconds: 86400 },
        { unit: 'hour', seconds: 3600 },
        { unit: 'minute', seconds: 60 },
    ];

    // Use Intl.RelativeTimeFormat for locale-aware formatting
    if (typeof Intl !== 'undefined' && Intl.RelativeTimeFormat) {
        try {
            const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'always' });
            for (const interval of intervals) {
                const count = Math.floor(seconds / interval.seconds);
                if (count >= 1) {
                    return rtf.format(-count, interval.unit);
                }
            }
            return rtf.format(0, 'second');
        } catch (e) {
            // Fall through to English fallback
        }
    }

    // Fallback for environments without Intl
    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) {
            return `${count} ${interval.unit}${count > 1 ? 's' : ''} ago`;
        }
    }
    return 'Just now';
}

/**
 * Formats a view count into a compact string (e.g. 1.2M, 3.4K).
 * @param {number} views
 * @returns {string}
 */
export function formatViews(views) {
    if (views >= 1000000) {
        return (views / 1000000).toFixed(1) + 'M';
    }
    if (views >= 1000) {
        return (views / 1000).toFixed(1) + 'K';
    }
    return String(views);
}
