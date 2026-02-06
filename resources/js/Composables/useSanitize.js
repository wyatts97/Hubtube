/**
 * Sanitizes HTML strings to mitigate XSS from ad code injected via v-html.
 *
 * Strips:
 *  - <script> tags and their contents
 *  - Inline event handlers (onclick, onerror, onload, etc.)
 *  - javascript: protocol in href/src attributes
 *  - <object>, <embed>, <applet>, <form>, <base> tags
 *
 * Allows:
 *  - Common ad markup: <div>, <a>, <img>, <span>, <p>, <iframe>, <ins>, etc.
 */

const EVENT_HANDLER_RE = /\s+on\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]*)/gi;
const SCRIPT_TAG_RE = /<script[\s>][\s\S]*?<\/script\s*>/gi;
const DANGEROUS_TAG_RE = /<\/?(object|embed|applet|form|base|meta|link)\b[^>]*>/gi;
const JS_PROTOCOL_RE = /\b(href|src|action)\s*=\s*(?:"javascript:[^"]*"|'javascript:[^']*')/gi;

/**
 * Sanitize an HTML string for safe use with v-html.
 * @param {string} html - Raw HTML string (e.g. ad code from admin panel)
 * @returns {string} Sanitized HTML
 */
export function sanitizeHtml(html) {
    if (!html || typeof html !== 'string') return '';

    let clean = html;

    // Remove <script> tags and contents
    clean = clean.replace(SCRIPT_TAG_RE, '');

    // Remove dangerous tags
    clean = clean.replace(DANGEROUS_TAG_RE, '');

    // Remove inline event handlers
    clean = clean.replace(EVENT_HANDLER_RE, '');

    // Remove javascript: protocol URIs
    clean = clean.replace(JS_PROTOCOL_RE, '');

    return clean;
}
