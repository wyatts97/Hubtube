{{-- Graceful session-expired overlay for the Filament admin SPA.
     Intercepts Livewire failures caused by expired sessions/CSRF and shows
     a friendly "Refresh page" prompt instead of the default white error modal. --}}
<div id="ht-session-expired-overlay" class="ht-session-overlay" role="alert" aria-live="assertive" hidden>
    <div class="ht-session-overlay__card">
        <x-phosphor-warning-circle class="ht-session-overlay__icon" />
        <h2 class="ht-session-overlay__title">Session expired</h2>
        <p class="ht-session-overlay__text">You’ve been idle for a while. Refresh the page to continue.</p>
        <div class="ht-session-overlay__actions">
            <button type="button" class="ht-session-overlay__btn" onclick="window.location.reload()">
                <x-phosphor-arrows-clockwise class="w-4 h-4" />
                <span>Refresh page</span>
            </button>
            <a href="{{ route('login') }}" class="ht-session-overlay__link">
                Log in again
            </a>
        </div>
    </div>
</div>

<style>
.ht-session-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    padding: 1rem;
}
.ht-session-overlay[hidden] {
    display: none !important;
}
.ht-session-overlay__card {
    width: 100%;
    max-width: 24rem;
    background: #111827; /* gray-900 — admin is always dark */
    border: 1px solid #374151; /* gray-700 */
    border-radius: 0.75rem;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.ht-session-overlay__icon {
    width: 2.5rem;
    height: 2.5rem;
    color: #f59e0b; /* amber-500 */
    margin: 0 auto 1rem;
}
.ht-session-overlay__title {
    color: #f9fafb; /* gray-50 */
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.ht-session-overlay__text {
    color: #9ca3af; /* gray-400 */
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
    background: #3b82f6; /* blue-500 */
    color: #ffffff;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: none;
    cursor: pointer;
    transition: background 0.2s ease;
    text-decoration: none;
}
.ht-session-overlay__btn:hover {
    background: #2563eb; /* blue-600 */
}
.ht-session-overlay__link {
    color: #9ca3af; /* gray-400 */
    font-size: 0.875rem;
    text-decoration: underline;
}
.ht-session-overlay__link:hover {
    color: #e5e7eb; /* gray-200 */
}
</style>

<script>
(function () {
    const overlay = document.getElementById('ht-session-expired-overlay');
    if (!overlay) return;

    const showOverlay = () => {
        overlay.removeAttribute('hidden');
    };

    // Livewire v3 hook: intercept failed update requests and show a graceful overlay
    // instead of the default white "Oops! An Error Occurred" modal.
    if (window.Livewire && typeof Livewire.hook === 'function') {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                // 401  = unauthenticated (our new middleware response)
                // 403  = forbidden (e.g. user no longer admin)
                // 405  = method not allowed (the original symptom on redirect)
                // 419  = CSRF token expired (Laravel page-expired)
                // >=500 = server error that breaks Livewire response parsing
                if (status === 401 || status === 403 || status === 405 || status === 419 || status >= 500) {
                    preventDefault();
                    showOverlay();
                }
            });
        });
    }

    // Fallback: catch unhandled Livewire errors that bubble to window
    window.addEventListener('livewire:error', () => showOverlay());
})();
</script>
