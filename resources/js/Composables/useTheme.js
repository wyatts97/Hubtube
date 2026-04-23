import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

// The site is dark-only. This composable is kept as a thin stub for
// backwards compatibility with callers that still read `isDark` or
// `themeSettings`. All light-mode code paths have been removed.

const currentTheme = ref('dark');
const isInitialized = ref(false);

export function useTheme() {
    const page = usePage();
    const themeSettings = computed(() => page.props.theme || {});

    const isDark = computed(() => true);
    const isLight = computed(() => false);

    const applyTheme = () => {
        const root = document.documentElement;
        const colors = themeSettings.value?.dark || {};

        root.style.setProperty('--color-bg-primary', colors.bgPrimary || '#0a0a0a');
        root.style.setProperty('--color-bg-secondary', colors.bgSecondary || '#171717');
        root.style.setProperty('--color-bg-card', colors.bgCard || '#1f1f1f');
        root.style.setProperty('--color-accent', colors.accent || '#ef4444');
        root.style.setProperty('--color-text-primary', colors.textPrimary || '#ffffff');
        root.style.setProperty('--color-text-secondary', colors.textSecondary || '#a3a3a3');
        root.style.setProperty('--color-text-muted', colors.textMuted || '#8a8a8a');
        root.style.setProperty('--color-border', colors.border || '#262626');
        root.style.setProperty('--color-input-bg', colors.inputBg || '#262626');
        root.style.setProperty('--color-hover', colors.hover || '#2a2a2a');

        document.body.classList.remove('theme-light');
        document.body.classList.add('theme-dark');
        document.documentElement.classList.add('dark');
    };

    const initTheme = () => {
        if (isInitialized.value) return;
        applyTheme();
        isInitialized.value = true;
    };

    // No-ops kept for backwards-compat with any stray callers.
    const setTheme = () => {};
    const toggleTheme = () => {};

    onMounted(() => {
        initTheme();
    });

    return {
        currentTheme,
        isDark,
        isLight,
        themeSettings,
        setTheme,
        toggleTheme,
        initTheme,
        applyTheme,
    };
}
