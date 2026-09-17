/**
 * Comment text, split into the pieces a template can render.
 *
 * Comments are user input, so they are never handed to v-html. The text is cut
 * into plain, mention and timestamp segments instead, and the component renders
 * each with the right element — a channel link, a seek button, or an escaped
 * text node.
 *
 * The mention pattern matches the one in App\Services\CommentMentions, which
 * decides who gets notified, so the text that links is the text that notifies.
 */

// A mention, or a m:ss / h:mm:ss timestamp.
const PATTERN = /@(?<mention>[A-Za-z0-9._-]{3,30})|(?:(?<h>\d{1,2}):)?(?<m>\d{1,2}):(?<s>\d{2})/g;

/**
 * Reject a timestamp that is really part of a longer number, so the "23:45" in
 * "2023:45" stays plain text.
 */
const isStandaloneTime = (text, match) => {
    const before = text[match.index - 1] ?? '';
    const after = text[match.index + match[0].length] ?? '';

    return !/[\d:]/.test(before) && !/\d/.test(after);
};

/**
 * @param {string} text
 * @returns {Array<{type: 'text'|'mention'|'timestamp', text: string, username?: string, seconds?: number}>}
 */
export function parseCommentText(text) {
    const segments = [];

    if (!text) return segments;

    let cursor = 0;
    let match;
    PATTERN.lastIndex = 0;

    while ((match = PATTERN.exec(text)) !== null) {
        const { mention, h, m, s } = match.groups;

        if (!mention && !isStandaloneTime(text, match)) continue;

        if (match.index > cursor) {
            segments.push({ type: 'text', text: text.slice(cursor, match.index) });
        }

        if (mention) {
            segments.push({ type: 'mention', text: match[0], username: mention });
        } else {
            const seconds = (Number(h || 0) * 3600) + (Number(m) * 60) + Number(s);
            segments.push({ type: 'timestamp', text: match[0], seconds });
        }

        cursor = match.index + match[0].length;
    }

    if (cursor < text.length) {
        segments.push({ type: 'text', text: text.slice(cursor) });
    }

    return segments;
}

/** Seconds as the m:ss or h:mm:ss a comment would have written. */
export function formatTimestamp(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`
        : `${minutes}:${String(rest).padStart(2, '0')}`;
}
