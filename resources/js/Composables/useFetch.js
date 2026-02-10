/**
 * Shared fetch helper with CSRF, JSON headers, and credentials.
 * Centralizes header construction so every page doesn't inline its own.
 */
export function useFetch() {
    const getHeaders = (extra = {}) => ({
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'X-Requested-With': 'XMLHttpRequest',
        ...extra,
    });

    /**
     * Perform a JSON fetch with CSRF + credentials baked in.
     * @param {string} url
     * @param {object} options - standard fetch options; headers are merged with defaults
     * @returns {Promise<{ok: boolean, status: number, data: any}>}
     */
    const jsonFetch = async (url, options = {}) => {
        const { headers: extraHeaders, ...rest } = options;
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: getHeaders(extraHeaders),
                ...rest,
            });
            let data = null;
            const ct = response.headers.get('content-type') || '';
            if (ct.includes('application/json')) {
                data = await response.json();
            }
            return { ok: response.ok, status: response.status, data };
        } catch (e) {
            return { ok: false, status: 0, data: null };
        }
    };

    const get = (url, options = {}) => jsonFetch(url, { method: 'GET', ...options });
    const post = (url, body, options = {}) =>
        jsonFetch(url, { method: 'POST', body: body ? JSON.stringify(body) : undefined, ...options });
    const put = (url, body, options = {}) =>
        jsonFetch(url, { method: 'PUT', body: body ? JSON.stringify(body) : undefined, ...options });
    const del = (url, body, options = {}) =>
        jsonFetch(url, { method: 'DELETE', body: body ? JSON.stringify(body) : undefined, ...options });

    return { getHeaders, jsonFetch, get, post, put, del };
}
