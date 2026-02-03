import { ref, computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const currentTheme = ref('dark');
const isInitialized = ref(false);

export function useTheme() {
    const page = usePage();
    const themeSettings = computed(() => page.props.theme || {});
    
    const isDark = computed(() => currentTheme.value === 'dark');
    const isLight = computed(() => currentTheme.value === 'light');
    
    const initTheme = () => {
        if (isInitialized.value) return;
        
        const savedTheme = localStorage.getItem('hubtube-theme');
        const defaultMode = themeSettings.value?.mode || 'dark';
        
        if (savedTheme && themeSettings.value?.allowToggle) {
            currentTheme.value = savedTheme;
        } else if (defaultMode === 'system') {
            currentTheme.value = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } else {
            currentTheme.value = defaultMode;
        }
        
        applyTheme();
        isInitialized.value = true;
    };
    
    const setTheme = (theme) => {
        currentTheme.value = theme;
        localStorage.setItem('hubtube-theme', theme);
        applyTheme();
    };
    
    const toggleTheme = () => {
        setTheme(currentTheme.value === 'dark' ? 'light' : 'dark');
    };
    
    const applyTheme = (animate = true) => {
        const root = document.documentElement;
        const colors = currentTheme.value === 'dark' 
            ? themeSettings.value?.dark 
            : themeSettings.value?.light;
        
        if (!colors) return;
        
        // Add transition class for smooth theme switching
        if (animate && isInitialized.value) {
            root.classList.add('theme-transitioning');
            setTimeout(() => {
                root.classList.remove('theme-transitioning');
            }, 300);
        }
        
        root.style.setProperty('--color-bg-primary', colors.bgPrimary || (currentTheme.value === 'dark' ? '#0a0a0a' : '#ffffff'));
        root.style.setProperty('--color-bg-secondary', colors.bgSecondary || (currentTheme.value === 'dark' ? '#171717' : '#f5f5f5'));
        root.style.setProperty('--color-bg-card', colors.bgCard || (currentTheme.value === 'dark' ? '#1f1f1f' : '#ffffff'));
        root.style.setProperty('--color-accent', colors.accent || '#ef4444');
        root.style.setProperty('--color-text-primary', colors.textPrimary || (currentTheme.value === 'dark' ? '#ffffff' : '#171717'));
        root.style.setProperty('--color-text-secondary', colors.textSecondary || (currentTheme.value === 'dark' ? '#a3a3a3' : '#525252'));
        root.style.setProperty('--color-text-muted', colors.textMuted || (currentTheme.value === 'dark' ? '#737373' : '#737373'));
        root.style.setProperty('--color-border', colors.border || (currentTheme.value === 'dark' ? '#262626' : '#e5e5e5'));
        root.style.setProperty('--color-input-bg', colors.inputBg || (currentTheme.value === 'dark' ? '#262626' : '#f5f5f5'));
        root.style.setProperty('--color-hover', colors.hover || (currentTheme.value === 'dark' ? '#2a2a2a' : '#e5e5e5'));
        
        // Update body classes
        document.body.classList.remove('theme-dark', 'theme-light');
        document.body.classList.add(`theme-${currentTheme.value}`);
        
        // Update html class for Tailwind dark mode
        if (currentTheme.value === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };
    
    // Watch for theme settings changes from server
    watch(() => themeSettings.value, () => {
        if (isInitialized.value) {
            applyTheme();
        }
    }, { deep: true });
    
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
