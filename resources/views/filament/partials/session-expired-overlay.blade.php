{{-- Graceful session-expired overlay for the Filament admin SPA.
     Intercepts Livewire failures caused by expired sessions/CSRF and shows
     a friendly "Refresh page" prompt instead of the default white error modal. --}}
<div id="ht-session-expired-overlay" class="ht-session-overlay" role="alert" aria-live="assertive" hidden>
    <div class="ht-session-overlay__card">
        <div class="ht-session-overlay__icon-wrap">
            <x-phosphor-warning-circle class="ht-session-overlay__icon" />
        </div>
        <h2 class="ht-session-overlay__title" data-session-text>Session expired</h2>
        <p class="ht-session-overlay__text" data-session-text>You’ve been idle for a while. Refresh the page to continue.</p>
        <div class="ht-session-overlay__actions">
            <button type="button" class="ht-session-overlay__btn" onclick="window.location.reload()">
                <x-phosphor-arrows-clockwise class="w-4 h-4" />
                <span>Refresh page</span>
            </button>
            <a href="{{ route('login') }}" class="ht-session-overlay__link" data-session-only>
                Log in again
            </a>
        </div>
    </div>
</div>

<style>
/* Fall back to the admin panel's design tokens when injected inside .fi-body.
   If the tokens are unavailable (e.g. overlay rendered outside the panel),
   these defaults keep the same muted-red-on-dark-zinc palette. */
.ht-session-overlay {
    --ht-accent: 184 65 76;
    --ht-accent-hover: 199 89 96;
    --ht-accent-soft: 88 28 32;
    --ht-surface: 24 24 27;
    --ht-surface-2: 30 30 34;
    --ht-surface-3: 39 39 42;
    --ht-border: 39 39 42;
    --ht-border-soft: 55 55 60;
    --ht-text: 228 228 231;
    --ht-text-heading: 244 244 245;
    --ht-text-muted: 161 161 170;
    --ht-warning: 234 179 8;
    --ht-danger: 239 68 68;

    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgb(0 0 0 / 0.75);
    backdrop-filter: blur(4px);
    padding: 1rem;
}
.ht-session-overlay[hidden] {
    display: none !important;
}
.ht-session-overlay__card {
    position: relative;
    width: 100%;
    max-width: 24rem;
    background: linear-gradient(180deg, rgb(var(--ht-surface) / 0.97), rgb(18 18 20 / 0.97));
    border: 1px solid rgb(var(--ht-accent) / 0.2);
    border-radius: 0.875rem;
    padding: 1.5rem;
    text-align: center;
    box-shadow:
        0 25px 50px -12px rgb(0 0 0 / 0.5),
        0 0 0 1px rgb(var(--ht-accent) / 0.05),
        inset 0 1px 0 rgb(255 255 255 / 0.03);
    overflow: hidden;
}
.ht-session-overlay__card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgb(var(--ht-accent) / 0.8), rgb(var(--ht-accent-hover) / 0.5), transparent);
    opacity: 0.85;
    pointer-events: none;
}
.ht-session-overlay__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 9999px;
    background: rgb(var(--ht-accent) / 0.12);
    border: 1px solid rgb(var(--ht-accent) / 0.2);
    margin: 0 auto 1rem;
}
.ht-session-overlay__icon {
    width: 1.75rem;
    height: 1.75rem;
    color: rgb(var(--ht-accent-hover));
}
.ht-session-overlay__title {
    color: rgb(var(--ht-text-heading));
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.ht-session-overlay__text {
    color: rgb(var(--ht-text-muted));
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
    line-height: 1.5;
}
.ht-session-overlay__actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}
.ht-session-overlay__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: rgb(var(--ht-accent));
    color: rgb(255 255 255);
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.5rem 1.25rem;
    border-radius: 0.5rem;
    border: 1px solid rgb(var(--ht-accent-hover));
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.2), 0 0 0 1px rgb(var(--ht-accent) / 0.25);
}
.ht-session-overlay__btn:hover {
    background: rgb(var(--ht-accent-hover));
    border-color: rgb(var(--ht-accent-hover));
    box-shadow: 0 4px 12px -2px rgb(var(--ht-accent) / 0.4), 0 0 0 1px rgb(var(--ht-accent) / 0.3);
}
.ht-session-overlay__btn:active {
    transform: translateY(1px);
}
.ht-session-overlay__link {
    color: rgb(var(--ht-text-muted));
    font-size: 0.875rem;
    text-decoration: underline;
    transition: color 0.2s ease;
}
.ht-session-overlay__link:hover {
    color: rgb(var(--ht-accent-hover));
}
</style>

<script>
(function () {
    const overlay = document.getElementById('ht-session-expired-overlay');
    if (!overlay) return;

    const titleEl = overlay.querySelector('[data-session-text].ht-session-overlay__title');
    const textEl = overlay.querySelector('[data-session-text].ht-session-overlay__text');
    const loginLink = overlay.querySelector('[data-session-only]');
    const iconWrap = overlay.querySelector('.ht-session-overlay__icon-wrap');

    // Session/auth-related statuses show the "Session expired" prompt.
    const SESSION_STATUSES = new Set([401, 403, 405, 419]);

    const showOverlay = (isSessionError) => {
        if (isSessionError) {
            if (titleEl) titleEl.textContent = 'Session expired';
            if (textEl) textEl.textContent = 'You’ve been idle for a while. Refresh the page to continue.';
            if (loginLink) loginLink.style.display = '';
            if (iconWrap) iconWrap.style.setProperty('--ht-accent', '184 65 76');
        } else {
            // Server error — not a session issue. Show an accurate message
            // instead of misleading the user with "You've been idle".
            if (titleEl) titleEl.textContent = 'Something went wrong';
            if (textEl) textEl.textContent = 'The server encountered an error while processing your request. Try refreshing the page.';
            if (loginLink) loginLink.style.display = 'none';
            // Switch the icon tint to danger red for server errors
            if (iconWrap) iconWrap.style.setProperty('--ht-accent', '239 68 68');
        }
        overlay.removeAttribute('hidden');
    };

    // Livewire v3 hook: intercept failed update requests and show a graceful overlay
    // instead of the default white "Oops! An Error Occurred" modal.
    if (window.Livewire && typeof Livewire.hook === 'function') {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                // 401  = unauthenticated (our middleware response)
                // 403  = forbidden (e.g. user no longer admin)
                // 405  = method not allowed (the original symptom on redirect)
                // 419  = CSRF token expired (Laravel page-expired)
                // >=500 = server error (bug, not a session issue)
                if (SESSION_STATUSES.has(status) || status >= 500) {
                    preventDefault();
                    showOverlay(SESSION_STATUSES.has(status));
                }
            });
        });
    }

    // Fallback: catch unhandled Livewire errors that bubble to window.
    // Treat as a server error since we can't confirm the status here.
    window.addEventListener('livewire:error', () => showOverlay(false));
})();
</script>
