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
    position: relative;
    width: 100%;
    max-width: 24rem;
    background: linear-gradient(180deg, rgb(24 24 27 / 0.95), rgb(18 18 20 / 0.95));
    border: 1px solid rgb(255 255 255 / 0.08);
    border-radius: 0.875rem;
    padding: 1.5rem;
    text-align: center;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.03);
    overflow: hidden;
}
.ht-session-overlay__card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgb(99 102 241 / 0.65), rgb(168 85 247 / 0.45), transparent);
    opacity: 0.8;
    pointer-events: none;
}
.ht-session-overlay__icon {
    width: 2.5rem;
    height: 2.5rem;
    color: rgb(251 191 36); /* amber-400 */
    margin: 0 auto 1rem;
}
.ht-session-overlay__title {
    color: rgb(243 244 246); /* gray-100 */
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.ht-session-overlay__text {
    color: rgb(148 163 184); /* slate-400 */
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
    background: rgb(39 39 42); /* zinc-800 */
    color: rgb(244 244 245); /* zinc-100 */
    font-weight: 500;
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid rgb(63 63 70); /* zinc-700 */
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    text-decoration: none;
}
.ht-session-overlay__btn:hover {
    background: rgb(63 63 70); /* zinc-700 */
    border-color: rgb(82 82 91); /* zinc-600 */
    color: rgb(255 255 255);
}
.ht-session-overlay__link {
    color: rgb(148 163 184); /* slate-400 */
    font-size: 0.875rem;
    text-decoration: underline;
}
.ht-session-overlay__link:hover {
    color: rgb(209 213 219); /* gray-300 */
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
