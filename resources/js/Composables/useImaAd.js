import { ref, nextTick } from 'vue';

export function useImaAd(containerRef, videoRef, emit) {
    let imaDisplayContainer = null;
    let imaAdsLoader = null;
    let imaAdsManager = null;
    const loadPromise = ref(null);

    const loadImaSdk = () => new Promise((resolve, reject) => {
        if (window.google?.ima) { resolve(); return; }
        const s = document.createElement('script');
        s.src = 'https://imasdk.googleapis.com/js/sdkloader/ima3.js';
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });

    const destroy = () => {
        try { imaAdsManager?.destroy(); } catch (_) {}
        try { imaAdsLoader?.contentComplete(); } catch (_) {}
        imaAdsManager = null;
        imaAdsLoader = null;
        imaDisplayContainer = null;
    };

    const play = async (ad, { onStart, onComplete, onError, fireImpression } = {}) => {
        if (loadPromise.value) return loadPromise.value;

        loadPromise.value = (async () => {
            try { await loadImaSdk(); }
            catch (e) {
                console.warn('[useImaAd] IMA SDK failed to load:', e);
                onError?.(e);
                return;
            }

            await nextTick();
            if (!containerRef.value || !videoRef.value) {
                onError?.(new Error('Missing container or video refs'));
                return;
            }

            try {
                const ima = window.google.ima;
                destroy();

                ima.settings.setDisableCustomPlaybackForIOS10Plus(true);

                imaDisplayContainer = new ima.AdDisplayContainer(containerRef.value, videoRef.value);
                imaDisplayContainer.initialize();

                imaAdsLoader = new ima.AdsLoader(imaDisplayContainer);

                imaAdsLoader.addEventListener(
                    ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED,
                    (event) => {
                        imaAdsManager = event.getAdsManager(videoRef.value);

                        imaAdsManager.addEventListener(ima.AdEvent.Type.STARTED, () => {
                            onStart?.();
                            fireImpression?.();
                        });
                        imaAdsManager.addEventListener(ima.AdEvent.Type.COMPLETE, () => { destroy(); onComplete?.(); });
                        imaAdsManager.addEventListener(ima.AdEvent.Type.SKIPPED, () => { destroy(); onComplete?.(); });
                        imaAdsManager.addEventListener(ima.AdEvent.Type.ALL_ADS_COMPLETED, () => { destroy(); onComplete?.(); });
                        imaAdsManager.addEventListener(ima.AdErrorEvent.Type.AD_ERROR, (err) => {
                            console.warn('[useImaAd] IMA ad error:', err.getError().toString());
                            destroy();
                            onError?.(err);
                        });

                        try {
                            const w = containerRef.value?.offsetWidth || 640;
                            const h = containerRef.value?.offsetHeight || 360;
                            imaAdsManager.init(w, h, ima.ViewMode.NORMAL);
                            imaAdsManager.start();
                        } catch (err) {
                            console.warn('[useImaAd] IMA start error:', err);
                            destroy();
                            onError?.(err);
                        }
                    }
                );

                imaAdsLoader.addEventListener(ima.AdErrorEvent.Type.AD_ERROR, (err) => {
                    console.warn('[useImaAd] IMA loader error:', err.getError().toString());
                    destroy();
                    onError?.(err);
                });

                const req = new ima.AdsRequest();
                req.adTagUrl = ad.content.trim();
                req.linearAdSlotWidth = containerRef.value?.offsetWidth || 640;
                req.linearAdSlotHeight = containerRef.value?.offsetHeight || 360;
                req.nonLinearAdSlotWidth = containerRef.value?.offsetWidth || 640;
                req.nonLinearAdSlotHeight = 150;
                imaAdsLoader.requestAds(req);
            } catch (e) {
                console.warn('[useImaAd] IMA setup error:', e);
                destroy();
                onError?.(e);
            }
        })();

        return loadPromise.value;
    };

    return { play, destroy };
}
