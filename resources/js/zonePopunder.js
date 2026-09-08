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

    // Defensive: ensure config values are valid numbers
    const triggerType = config.triggerType || 'clicks';
    const clickFrequency = Math.max(1, parseInt(config.clickFrequency, 10) || 3);
    const cooldownMinutes = Math.max(0, parseInt(config.cooldownMinutes, 10) || 5);
    const maxPerSession = Math.max(1, parseInt(config.maxPerSession, 10) || 3);

    const STORAGE_KEY = 'ht_zone_popunder';
    const isMobileUA = /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|webOS/i.test(navigator.userAgent);
    const targetUrl = (isMobileUA ? (config.mobileUrl || config.url) : config.url).trim();

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

    function fire() {
        // A popunder needs the window reference (blur the new window, refocus
        // the parent), so `noopener` — which forces window.open to return null
        // — is not an option. Open about:blank instead: we keep the handle,
        // sever `opener` while the popup is still same-origin (a cross-origin
        // `win.opener = null` throws and used to be swallowed, leaving the ad
        // page a live handle on this one), then navigate it to the ad.
        //
        // Note: true background-tab behavior is browser-dependent; some browsers
        // always focus a user-initiated popup regardless of script focus calls.
        const win = window.open('about:blank', '_blank');
        if (!win) {
            // Popup blocked or no window reference — do not reset counters so we try again.
            return false;
        }

        try { win.opener = null; } catch { /* already detached */ }
        try {
            win.location.replace(targetUrl);
        } catch {
            // Navigation refused — close the blank window rather than stranding it.
            try { win.close(); } catch { /* nothing to do */ }
            return false;
        }

        const state = getState();
        state.fired++;
        state.clicks = 0;
        state.lastFiredAt = Date.now();
        setState(state);

        try { win.blur(); } catch { /* browser kept focus on the popup */ }
        try { window.focus(); } catch { /* nothing to refocus */ }

        return true;
    }

    function isWhitelistedTarget(el) {
        if (!el) return false;
        
        // Find closest <a> ancestor (or self if already an <a>)
        const linkEl = el.closest('a');
        if (!linkEl) return false;
        
        const href = linkEl.getAttribute('href') || '';
        
        // Exclude non-navigational links
        if (href === '#' || href === '' || href.startsWith('javascript:')) return false;
        if (href.startsWith('mailto:') || href.startsWith('tel:')) return false;
        
        const target = linkEl.getAttribute('target');
        if (target === '_blank') return false;
        
        return true;
    }

    function onClick(e) {
        if (!isWhitelistedTarget(e.target)) return;
        
        const state = getState();
        state.clicks++;
        setState(state);

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
