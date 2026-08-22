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

    const destroy = () => {
        try { imaAdsManager?.destroy(); } catch (_) {}
        try { imaAdsLoader?.contentComplete(); } catch (_) {}
        imaAdsManager = null;
        imaAdsLoader = null;
        imaDisplayContainer = null;
        loadPromise.value = null;
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

    return { play, destroy };
}
