import { onMounted, onUnmounted, watch } from 'vue';

/**
 * Reports the signed-in viewer's playback position to /videos/{id}/progress,
 * which fills watch_history.watched_seconds.
 *
 * Positions are throttled to one request per `intervalSeconds` while playing,
 * and flushed on pause, when the tab is hidden, and when leaving the video.
 * The flush uses fetch keepalive so it survives page unload while still
 * carrying the CSRF header.
 *
 * @param {() => number|null} getVideoId  Getter, so navigating between videos
 *                                         on the same page component is handled.
 */
export function useWatchProgress(getVideoId, { intervalSeconds = 15 } = {}) {
    let position = 0;
    let lastSentPosition = -1;
    let lastSentAt = 0;
    let videoId = null;

    const send = (id, seconds) => {
        const whole = Math.floor(seconds);
        if (!id || whole <= 0 || whole === lastSentPosition) return;

        lastSentPosition = whole;
        lastSentAt = Date.now();

        try {
            fetch(`/videos/${id}/progress`, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ seconds: whole }),
            }).catch(() => {});
        } catch {
            // Progress is best-effort; never let it surface to the viewer.
        }
    };

    const flush = () => send(videoId, position);

    /** Call with the content (not ad) playback position. */
    const onProgress = (seconds) => {
        position = seconds;
        if (Date.now() - lastSentAt >= intervalSeconds * 1000) {
            send(videoId, position);
        }
    };

    const onPaused = (seconds) => {
        position = seconds;
        flush();
    };

    const onVisibilityChange = () => {
        if (document.visibilityState === 'hidden') flush();
    };

    watch(getVideoId, (nextId) => {
        // Leaving one video for another: save where the previous one got to.
        flush();
        videoId = nextId;
        position = 0;
        lastSentPosition = -1;
        lastSentAt = 0;
    }, { immediate: true });

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', flush);
    });

    onUnmounted(() => {
        flush();
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('pagehide', flush);
    });

    return { onProgress, onPaused, flush };
}
