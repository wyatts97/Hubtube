import { ref, nextTick } from 'vue';
import { useScriptTag } from '@vueuse/core';

export function useImaAd(containerRef, videoRef, callbacks = {}) {
    let imaDisplayContainer = null;
    let imaAdsLoader = null;
    let imaAdsManager = null;
    const loadPromise = ref(null);

    const cb = callbacks || {};

    const { load: loadImaScript } = useScriptTag(
        'https://imasdk.googleapis.com/js/sdkloader/ima3.js',
        () => {},
        { manual: true }
    );

    const loadImaSdk = () => {
        if (window.google?.ima) return Promise.resolve();
        return loadImaScript();
    };

    /**
     * Tear down the IMA objects.
     *
     * `clearGuard` exists because destroy() is called from two very different
     * places. When the caller is finishing with an ad, the in-flight guard must
     * be released so the next ad can start. When play() calls it to clear stale
     * IMA state *before* building new objects, releasing the guard would undo
     * the reentrancy protection play() had just taken out — a second concurrent
     * play() would sail past the `if (loadPromise.value)` check and issue a
     * duplicate VAST request against the same video element.
     */
    const destroy = ({ clearGuard = true } = {}) => {
        try { imaAdsManager?.destroy(); } catch (_) {}
        try { imaAdsLoader?.contentComplete(); } catch (_) {}
        imaAdsManager = null;
        imaAdsLoader = null;
        imaDisplayContainer = null;
        if (clearGuard) {
            loadPromise.value = null;
        }
    };

    const play = async (ad) => {
        if (loadPromise.value) return loadPromise.value;

        loadPromise.value = (async () => {
            try { await loadImaSdk(); }
            catch (e) {
                console.warn('[useImaAd] IMA SDK failed to load:', e);
                cb.onError?.(e);
                loadPromise.value = null;
                return;
            }

            await nextTick();
            if (!containerRef.value || !videoRef.value) {
                cb.onError?.(new Error('Missing container or video refs'));
                loadPromise.value = null;
                return;
            }

            try {
                const ima = window.google.ima;
                // Keep the guard: we are still inside the play() that owns it.
                destroy({ clearGuard: false });

                ima.settings.setDisableCustomPlaybackForIOS10Plus(true);

                imaDisplayContainer = new ima.AdDisplayContainer(containerRef.value, videoRef.value);
                imaDisplayContainer.initialize();

                imaAdsLoader = new ima.AdsLoader(imaDisplayContainer);

                imaAdsLoader.addEventListener(
                    ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED,
                    (event) => {
                        imaAdsManager = event.getAdsManager(videoRef.value);

                        imaAdsManager.addEventListener(ima.AdEvent.Type.STARTED, () => {
                            cb.onStart?.();
                            cb.fireImpression?.();
                        });
                        imaAdsManager.addEventListener(ima.AdEvent.Type.COMPLETE, () => { destroy(); cb.onComplete?.(); });
                        imaAdsManager.addEventListener(ima.AdEvent.Type.SKIPPED, () => { destroy(); cb.onComplete?.(); });
                        imaAdsManager.addEventListener(ima.AdEvent.Type.ALL_ADS_COMPLETED, () => { destroy(); cb.onComplete?.(); });
                        imaAdsManager.addEventListener(ima.AdErrorEvent.Type.AD_ERROR, (err) => {
                            console.warn('[useImaAd] IMA ad error:', err.getError().toString());
                            destroy();
                            cb.onError?.(err);
                        });

                        try {
                            const w = containerRef.value?.offsetWidth || 640;
                            const h = containerRef.value?.offsetHeight || 360;
                            imaAdsManager.init(w, h, ima.ViewMode.NORMAL);
                            imaAdsManager.start();
                        } catch (err) {
                            console.warn('[useImaAd] IMA start error:', err);
                            destroy();
                            cb.onError?.(err);
                        }
                    }
                );

                imaAdsLoader.addEventListener(ima.AdErrorEvent.Type.AD_ERROR, (err) => {
                    console.warn('[useImaAd] IMA loader error:', err.getError().toString());
                    destroy();
                    cb.onError?.(err);
                });

                const req = new ima.AdsRequest();
                req.adTagUrl = ad.content.trim();
                const cw = containerRef.value?.offsetWidth || 0;
                const ch = containerRef.value?.offsetHeight || 0;
                const isPortrait = ch > cw && ch > 0;
                const fallbackW = isPortrait ? 360 : 640;
                const fallbackH = isPortrait ? 640 : 360;
                req.linearAdSlotWidth = cw || fallbackW;
                req.linearAdSlotHeight = ch || fallbackH;
                req.nonLinearAdSlotWidth = cw || fallbackW;
                req.nonLinearAdSlotHeight = 150;
                imaAdsLoader.requestAds(req);
            } catch (e) {
                console.warn('[useImaAd] IMA setup error:', e);
                destroy();
                cb.onError?.(e);
            }
        })().finally(() => {
            loadPromise.value = null;
        });

        return loadPromise.value;
    };

    /**
     * Fetch the IMA SDK ahead of time, without requesting any ad.
     *
     * The SDK script is a few hundred KB and its download previously sat in the
     * critical path of the pre-roll: the viewer pressed play, and only then did
     * the browser start fetching the library that has to run before the ad can
     * resolve. Warming it while the video metadata loads moves that cost off
     * the moment the viewer is actually waiting.
     *
     * Deliberately does not call requestAds — pre-resolving the VAST document
     * would mean holding an AdsLoader open against a video element that may
     * never play, and IMA is not forgiving about that lifecycle.
     */
    const preload = () => loadImaSdk().catch(() => {
        // A failed warm-up is not an error: play() will retry and report.
    });

    return { play, destroy, preload };
}
