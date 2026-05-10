import { ref, computed } from 'vue';

/**
 * Resumable, parallel chunked uploader for the /upload/chunk + /upload/finalize backend.
 *
 * Usage:
 *   const upload = useChunkedUpload({ chunkSize, parallel, maxRetries });
 *   upload.start(file);
 *   upload.pause(); upload.resume(); upload.abort();
 *   await upload.finalize({ title, description, ... });
 *
 * State refs:
 *   status:   'idle' | 'uploading' | 'paused' | 'complete' | 'error' | 'aborted'
 *   percent:  0..100
 *   bytesPerSecond, etaSeconds
 *   error:    string | null
 *   uploadId, extension (set after start)
 */
export function useChunkedUpload(options = {}) {
    const chunkSize     = options.chunkSize     ?? 8 * 1024 * 1024; // 8 MB
    const parallel      = options.parallel      ?? 3;
    const maxRetries    = options.maxRetries    ?? 3;
    const sessionKey    = options.sessionKey    ?? 'hubtube.chunkedUpload';

    const status         = ref('idle');
    const percent        = ref(0);
    const bytesPerSecond = ref(0);
    const etaSeconds     = ref(null);
    const error          = ref(null);
    const uploadId       = ref(null);
    const extension      = ref(null);
    const filename       = ref(null);
    const fileSize       = ref(0);

    let file            = null;
    let totalChunks     = 0;
    let uploadedBytes   = 0;
    let completedChunks = new Set();
    let abortControllers = new Map();
    let startTime       = 0;
    let pausedFlag      = false;
    let abortedFlag     = false;
    let activeQueue     = [];

    const isUploading = computed(() => status.value === 'uploading');

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function generateUploadId() {
        // 32-char URL-safe random ID
        const arr = new Uint8Array(24);
        (window.crypto || window.msCrypto).getRandomValues(arr);
        return Array.from(arr)
            .map(b => b.toString(16).padStart(2, '0'))
            .join('')
            .slice(0, 32);
    }

    function persist() {
        try {
            sessionStorage.setItem(sessionKey, JSON.stringify({
                uploadId: uploadId.value,
                extension: extension.value,
                filename: filename.value,
                fileSize: fileSize.value,
                completed: Array.from(completedChunks),
                totalChunks,
            }));
        } catch (e) { /* quota / disabled — non-fatal */ }
    }

    function clearPersisted() {
        try { sessionStorage.removeItem(sessionKey); } catch (e) {}
    }

    function getPersisted() {
        try {
            const raw = sessionStorage.getItem(sessionKey);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function updateProgress() {
        if (fileSize.value <= 0) return;
        const pct = Math.min(100, Math.round((uploadedBytes / fileSize.value) * 100));
        percent.value = pct;
        const elapsed = (performance.now() - startTime) / 1000;
        if (elapsed > 0.5) {
            bytesPerSecond.value = uploadedBytes / elapsed;
            const remaining = fileSize.value - uploadedBytes;
            etaSeconds.value = bytesPerSecond.value > 0
                ? Math.round(remaining / bytesPerSecond.value)
                : null;
        }
    }

    async function uploadChunk(index) {
        if (completedChunks.has(index)) return;
        if (pausedFlag || abortedFlag) return;

        const start = index * chunkSize;
        const end = Math.min(start + chunkSize, fileSize.value);
        const blob = file.slice(start, end);

        const fd = new FormData();
        fd.append('chunk', blob, `chunk_${index}`);
        fd.append('chunkIndex', String(index));
        fd.append('totalChunks', String(totalChunks));
        fd.append('uploadId', uploadId.value);
        fd.append('filename', filename.value);
        fd.append('fileSize', String(fileSize.value));

        let attempt = 0;
        while (attempt < maxRetries) {
            if (pausedFlag || abortedFlag) return;
            const ctrl = new AbortController();
            abortControllers.set(index, ctrl);

            try {
                const res = await fetch('/upload/chunk', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    signal: ctrl.signal,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (res.status === 429) {
                    const data = await res.json().catch(() => ({}));
                    throw Object.assign(new Error(data.error || 'Upload limit reached'), { fatal: true, limitReached: true });
                }
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.error || `Chunk ${index} failed (${res.status})`);
                }

                const data = await res.json();
                completedChunks.add(index);
                uploadedBytes += (end - start);
                abortControllers.delete(index);
                updateProgress();
                persist();

                if (data.status === 'complete') {
                    extension.value = data.extension || extension.value;
                }
                return;
            } catch (err) {
                if (err.name === 'AbortError' || pausedFlag || abortedFlag) return;
                if (err.fatal) throw err;
                attempt++;
                if (attempt >= maxRetries) throw err;
                // exponential backoff: 500ms, 1s, 2s
                await new Promise(r => setTimeout(r, 500 * Math.pow(2, attempt - 1)));
            }
        }
    }

    async function runQueue() {
        const indices = [];
        for (let i = 0; i < totalChunks; i++) {
            if (!completedChunks.has(i)) indices.push(i);
        }
        activeQueue = indices;

        // Worker pool
        const workers = Array.from({ length: Math.min(parallel, indices.length) }, async () => {
            while (activeQueue.length > 0 && !pausedFlag && !abortedFlag) {
                const next = activeQueue.shift();
                if (next === undefined) return;
                await uploadChunk(next);
            }
        });

        await Promise.all(workers);
    }

    async function start(selectedFile) {
        if (!selectedFile) return;
        file = selectedFile;
        fileSize.value = selectedFile.size;
        filename.value = selectedFile.name;
        extension.value = (selectedFile.name.split('.').pop() || 'mp4').toLowerCase();
        totalChunks = Math.ceil(selectedFile.size / chunkSize);
        uploadedBytes = 0;
        completedChunks = new Set();
        abortControllers = new Map();
        pausedFlag = false;
        abortedFlag = false;
        error.value = null;
        percent.value = 0;
        uploadId.value = generateUploadId();
        startTime = performance.now();
        status.value = 'uploading';
        persist();

        try {
            await runQueue();
            if (abortedFlag) return;
            if (pausedFlag) { status.value = 'paused'; return; }
            if (completedChunks.size === totalChunks) {
                status.value = 'complete';
                percent.value = 100;
            }
        } catch (err) {
            error.value = err.message || 'Upload failed';
            status.value = 'error';
        }
    }

    function pause() {
        if (status.value !== 'uploading') return;
        pausedFlag = true;
        status.value = 'paused';
        for (const ctrl of abortControllers.values()) {
            try { ctrl.abort(); } catch (e) {}
        }
        abortControllers.clear();
    }

    async function resume() {
        if (status.value !== 'paused') return;
        pausedFlag = false;
        abortedFlag = false;
        status.value = 'uploading';
        startTime = performance.now() - (uploadedBytes / Math.max(bytesPerSecond.value, 1)) * 1000;
        try {
            await runQueue();
            if (completedChunks.size === totalChunks) {
                status.value = 'complete';
                percent.value = 100;
            }
        } catch (err) {
            error.value = err.message || 'Upload failed';
            status.value = 'error';
        }
    }

    function abort() {
        abortedFlag = true;
        pausedFlag = false;
        for (const ctrl of abortControllers.values()) {
            try { ctrl.abort(); } catch (e) {}
        }
        abortControllers.clear();
        status.value = 'aborted';
        clearPersisted();
    }

    /**
     * POST metadata to /upload/finalize. Returns { ok, redirect, errors }.
     */
    async function finalize(metadata) {
        if (status.value !== 'complete') {
            return { ok: false, errors: { upload: 'Upload is not complete yet.' } };
        }
        const fd = new FormData();
        fd.append('upload_id', uploadId.value);
        fd.append('extension', extension.value);
        fd.append('original_filename', filename.value);
        Object.entries(metadata).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((v, i) => fd.append(`${key}[${i}]`, v));
            } else if (value !== null && value !== undefined && value !== '') {
                fd.append(key, value);
            }
        });

        try {
            const res = await fetch('/upload/finalize', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (res.status === 422) {
                const data = await res.json().catch(() => ({}));
                return { ok: false, errors: data.errors || { upload: data.message || 'Validation failed' } };
            }
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                return { ok: false, errors: { upload: data.error || data.message || `Finalize failed (${res.status})` } };
            }

            const data = await res.json();
            clearPersisted();
            return { ok: true, redirect: data.redirect, videoId: data.video_id };
        } catch (err) {
            return { ok: false, errors: { upload: err.message || 'Network error' } };
        }
    }

    return {
        // state
        status, percent, bytesPerSecond, etaSeconds, error,
        uploadId, extension, filename, fileSize,
        isUploading,
        // actions
        start, pause, resume, abort, finalize,
        // helpers
        getPersisted, clearPersisted,
    };
}
