import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Light/dark theme controller.
 *
 * The colours themselves are NOT set from here. app.blade.php emits both
 * palettes as `:root { … }` and `:root.theme-light { … }` rules generated from
 * App\Support\ThemeTokens, so a theme switch is one class toggle rather than
 * eighteen style.setProperty() calls — which also means the inline script in the
 * document head can resolve the theme before first paint without duplicating any
 * colour values. This composable only decides WHICH class is on, and remembers
 * the choice.
 *
 * Resolution order:
 *   1. Admin forced the site to 'light' or 'dark'  -> that wins, no toggle shown
 *   2. Admin allows 'user'                          -> localStorage, else dark
 *
 * prefers-color-scheme is deliberately not consulted. Defaulting an adult site
 * to a bright white page because someone's OS is in light mode is a surprise
 * with real-world consequences, so an unset visitor always gets dark.
 */

const STORAGE_KEY = 'ht-theme';

// Module-level so every component shares one source of truth rather than each
// instance tracking its own copy.
const currentTheme = ref('dark');
const isInitialized = ref(false);

function readStored() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        return saved === 'light' || saved === 'dark' ? saved : null;
    } catch {
        // Private mode, or the browser is blocking site data.
        return null;
    }
}

function writeStored(theme) {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Non-fatal: the theme still applies for this page view.
    }
}

/** Toggle the classes and keep the browser-chrome colour in step. */
function applyThemeClass(theme) {
    const root = document.documentElement;

    root.classList.toggle('theme-light', theme === 'light');
    // Kept in sync for the handful of `dark:` Tailwind variants still in the tree.
    root.classList.toggle('dark', theme !== 'light');

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        const ground = getComputedStyle(root).getPropertyValue('--color-bg-primary').trim();
        if (ground) meta.setAttribute('content', ground);
    }
}

export function useTheme() {
    const page = usePage();

    const themeSettings = computed(() => page.props.theme || {});

    /** 'dark' | 'light' | 'user' — set by the admin in Filament Theme Settings. */
    const mode = computed(() => themeSettings.value.mode || 'user');

    /** Only offer a switcher when the admin hasn't pinned the site to one theme. */
    const canToggle = computed(() => mode.value === 'user');

    const isDark = computed(() => currentTheme.value !== 'light');
    const isLight = computed(() => currentTheme.value === 'light');

    /** What the server rendered, and whether it outranks localStorage. */
    const serverTheme = computed(() => themeSettings.value.resolved || 'dark');
    const serverIsAuthoritative = computed(() => themeSettings.value.authoritative === true);

    const resolveTheme = () => {
        // Admin pinned the site, or a signed-in user has a saved choice. Either
        // way the server already knows better than this browser's localStorage,
        // which may be stale from a different account on a shared machine.
        if (serverIsAuthoritative.value) return serverTheme.value;

        return readStored() || serverTheme.value;
    };

    const setTheme = (theme, { persist = true, animate = true } = {}) => {
        const next = theme === 'light' ? 'light' : 'dark';
        if (next === currentTheme.value && isInitialized.value) return;

        const root = document.documentElement;

        // Animate the swap, but only the swap. Leaving transitions on permanently
        // makes every subsequent hover and navigation pay for them.
        if (animate && isInitialized.value) {
            root.classList.add('theme-transitioning');
            window.setTimeout(() => root.classList.remove('theme-transitioning'), 250);
        }

        currentTheme.value = next;
        applyThemeClass(next);

        if (persist && canToggle.value) {
            writeStored(next);

            // Mirror onto the account when signed in, so the choice follows the
            // user to other devices. Fire-and-forget: the local change already
            // took effect and a failed sync must not surface as an error toast.
            if (page.props.auth?.user) {
                router.post('/api/theme', { theme: next }, {
                    preserveScroll: true,
                    preserveState: true,
                    only: [],
                    onError: () => {},
                });
            }
        }
    };

    const toggleTheme = () => setTheme(isLight.value ? 'dark' : 'light');

    const initTheme = () => {
        if (isInitialized.value) return;

        // The head script already applied the right class before paint; this
        // syncs the reactive ref to it without animating or re-persisting.
        const fromDom = document.documentElement.classList.contains('theme-light') ? 'light' : 'dark';
        const expected = resolveTheme();

        currentTheme.value = fromDom;

        if (fromDom !== expected) {
            setTheme(expected, { persist: false, animate: false });
        }

        // Bring this browser's copy in line after a login on a new device, so a
        // later signed-out visit on the same machine keeps the same theme.
        if (serverIsAuthoritative.value && canToggle.value && readStored() !== expected) {
            writeStored(expected);
        }

        isInitialized.value = true;
    };

    onMounted(initTheme);

    return {
        currentTheme,
        mode,
        canToggle,
        isDark,
        isLight,
        themeSettings,
        setTheme,
        toggleTheme,
        initTheme,
    };
}
