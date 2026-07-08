/**
 * Zone Popunder — client-side trigger for raw ad-zone URLs.
 *
 * Unlike the generic script-code popunder, this opens the configured
 * zone URL directly with our own frequency/cooldown/session caps, which
 * avoids popup blockers because the call is synchronously tied to a
 * real user click.
 */
(function () {
    const config = window.__zonePopunder || null;
    if (!config || !config.enabled || !config.url) return;

    const STORAGE_KEY = 'ht_zone_popunder';
    const isMobileUA = /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|webOS/i.test(navigator.userAgent);
    const targetUrl = (isMobileUA ? (config.mobileUrl || config.url) : config.url).trim();
    const triggerType = config.triggerType || 'clicks';
    const clickFrequency = Math.max(1, parseInt(config.clickFrequency, 10) || 3);
    const cooldownMinutes = Math.max(0, parseInt(config.cooldownMinutes, 10) || 5);
    const maxPerSession = Math.max(1, parseInt(config.maxPerSession, 10) || 3);

    function getState() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (raw) return JSON.parse(raw);
        } catch {}
        return { clicks: 0, fired: 0, lastFiredAt: 0 };
    }

    function setState(state) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch {}
    }

    function shouldFire() {
        if (!targetUrl) return false;
        const state = getState();
        if (state.fired >= maxPerSession) return false;

        const now = Date.now();
        const lastFired = state.lastFiredAt || 0;
        const minutesSinceLast = (now - lastFired) / 60000;

        if (triggerType === 'clicks') {
            return state.clicks >= clickFrequency;
        }
        if (triggerType === 'time') {
            return minutesSinceLast >= cooldownMinutes;
        }
        // both
        return state.clicks >= clickFrequency && minutesSinceLast >= cooldownMinutes;
    }

    function fire(target) {
        // Use a plain window.open so we keep a reference to the new window.
        // This is required for a popunder (blur new window, focus parent).
        // Note: true background-tab behavior is browser-dependent; some browsers
        // always focus a user-initiated popup regardless of script focus calls.
        const win = window.open(targetUrl, '_blank');
        if (!win) {
            // Popup blocked or no window reference — do not reset counters so we try again.
            return false;
        }

        const state = getState();
        state.fired++;
        state.clicks = 0;
        state.lastFiredAt = Date.now();
        setState(state);

        try { win.blur(); } catch {}
        try { window.focus(); } catch {}
        try { win.opener = null; } catch {}

        return true;
    }

    function isWhitelistedTarget(el) {
        if (!el) return false;
        // Allow clicks on most of the page, but not form controls, links that open in new tab already,
        // or <a> elements with explicit _blank (browser handles those).
        if (el.tagName === 'A') {
            const target = el.getAttribute('target');
            const href = el.getAttribute('href') || '';
            if (target === '_blank') return false;
            if (href.startsWith('mailto:') || href.startsWith('tel:')) return false;
        }
        if (['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'LABEL'].includes(el.tagName)) return false;
        return true;
    }

    function onClick(e) {
        const state = getState();
        state.clicks++;
        setState(state);

        if (!isWhitelistedTarget(e.target)) return;
        if (!shouldFire()) return;

        const fired = fire();
        if (fired) {
            // Briefly store that this click resulted in a popunder so the app
            // doesn't immediately try to perform other actions if needed.
            window.__zonePopunderFired = true;
            setTimeout(() => { window.__zonePopunderFired = false; }, 100);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('click', onClick, true);
        });
    } else {
        document.addEventListener('click', onClick, true);
    }
})();
