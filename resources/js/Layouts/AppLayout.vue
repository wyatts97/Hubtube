<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, usePage, useForm, router } from '@inertiajs/vue3';
import { 
    Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, 
    Video, Radio, Home, TrendingUp, Zap, ListVideo, History, 
    ChevronLeft, ChevronRight, Shield, Sun, Moon, Monitor,
    X, Check, CheckCheck, Rss, LayoutDashboard, ChevronDown, ChevronUp, Film,
    Tag, Folder, Star, ExternalLink, Eye, EyeOff, LayoutGrid, Plus,
    ImageIcon, MoreHorizontal
} from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';
import { useToast } from '@/Composables/useToast';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { useGlobalAutoTranslate } from '@/Composables/useGlobalAutoTranslate';
import ToastContainer from '@/Components/ToastContainer.vue';
import AgeVerificationModal from '@/Components/AgeVerificationModal.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import AdSlot from '@/Components/AdSlot.vue';

const toast = useToast();
const { get, post } = useFetch();
const { localizedUrl, t, isTranslated } = useI18n();
const { showOverlay } = useGlobalAutoTranslate();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const themeSettings = computed(() => page.props.theme || {});
const iconSettings = computed(() => themeSettings.value?.icons || {});
const showUserMenu = ref(false);
const showUploadMenu = ref(false);
const sidebarCollapsed = ref(false);
const searchQuery = ref('');
const showMobileSearch = ref(false);
const showLoginModal = ref(false);
const showLoginPassword = ref(false);

const loginForm = useForm({
    login: '',
    password: '',
    remember: false,
});

const submitLogin = () => {
    loginForm.post('/login', {
        onSuccess: () => {
            showLoginModal.value = false;
            loginForm.reset();
            window.location.reload();
        },
        onFinish: () => {
            loginForm.reset('password');
        },
    });
};
const mobileSearchQuery = ref('');
const openMegaMenu = ref(null);

// Menu items from admin panel
const menuItems = computed(() => page.props.menuItems || { header: [], mobile: [] });
const headerMenuItems = computed(() => menuItems.value.header || []);
const mobileMenuItems = computed(() => menuItems.value.mobile || []);

const lucideIconMap = {
    tag: Tag, folder: Folder, star: Star, home: Home, zap: Zap,
    radio: Radio, 'trending-up': TrendingUp, video: Video, film: Film,
    'list-video': ListVideo, history: History, search: Search,
};

const getMenuIcon = (iconName) => {
    if (!iconName) return null;
    return lucideIconMap[iconName] || Tag;
};

const toggleMegaMenu = (itemId) => {
    openMegaMenu.value = openMegaMenu.value === itemId ? null : itemId;
};

const closeMegaMenu = () => {
    openMegaMenu.value = null;
};

// Notification state
const showNotifications = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const notificationsLoaded = ref(false);

const fetchNotifications = async () => {
    if (!user.value) return;
    const { ok, data } = await get('/notifications');
    if (ok && data) {
        notifications.value = data.notifications || [];
        unreadCount.value = data.unreadCount || 0;
        notificationsLoaded.value = true;
    }
};

const toggleNotifications = () => {
    showNotifications.value = !showNotifications.value;
    showUserMenu.value = false;
    if (showNotifications.value && !notificationsLoaded.value) {
        fetchNotifications();
    }
};

const markAllRead = async () => {
    const { ok } = await post('/notifications/read-all');
    if (ok) {
        notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
        unreadCount.value = 0;
    }
};

const handleMobileSearch = () => {
    if (mobileSearchQuery.value.trim()) {
        window.location.href = `${localizedUrl('/search')}?q=${encodeURIComponent(mobileSearchQuery.value)}`;
        showMobileSearch.value = false;
    }
};

// Close dropdowns on outside click
const closeDropdowns = (e) => {
    if (!e.target.closest('.notification-dropdown') && !e.target.closest('.notification-trigger')) {
        showNotifications.value = false;
    }
    if (!e.target.closest('.user-menu-dropdown') && !e.target.closest('.user-menu-trigger')) {
        showUserMenu.value = false;
    }
    if (!e.target.closest('.upload-menu-dropdown') && !e.target.closest('.upload-menu-trigger')) {
        showUploadMenu.value = false;
    }
    if (!e.target.closest('.mega-menu-area')) {
        openMegaMenu.value = null;
    }
    if (!e.target.closest('.mobile-upload-menu')) {
        showMobileUploadMenu.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdowns);
    window.addEventListener('scroll', handleScroll, { passive: true });
    if (user.value) {
        // Fetch unread count on mount
        fetch('/notifications/unread-count', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(r => r.ok ? r.json() : null).then(d => {
            if (d) unreadCount.value = d.count || 0;
        }).catch((err) => {
            console.warn('[AppLayout] Failed to fetch unread count:', err);
        });
    }
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdowns);
    window.removeEventListener('scroll', handleScroll);
});

const { currentTheme, isDark, setTheme, toggleTheme } = useTheme();

// Watch for flash messages and show toasts
const flash = computed(() => page.props.flash);
watch(flash, (newFlash) => {
    if (newFlash?.success) {
        toast.success(newFlash.success);
    }
    if (newFlash?.error) {
        toast.error(newFlash.error);
    }
    if (newFlash?.warning) {
        toast.warning(newFlash.warning);
    }
    if (newFlash?.info) {
        toast.info(newFlash.info);
    }
}, { immediate: true, deep: true });

const getIconColor = (navKey) => {
    const icons = iconSettings.value;
    if (!icons) return 'var(--color-text-secondary)';
    
    // Check if there's a specific color for this nav item
    const navItem = icons[navKey];
    if (navItem?.color) return navItem.color;
    
    // Check for global icon color (with dark mode variant)
    if (icons.colorMode === 'global' || icons.colorMode === 'custom') {
        const color = isDark.value ? (icons.globalColorDark || icons.globalColor) : icons.globalColor;
        if (color) return color;
    }
    
    // Default to text secondary
    return 'var(--color-text-secondary)';
};

const liveStreamingEnabled = computed(() => page.props.app?.live_streaming_enabled !== false);
const monetizationEnabled = computed(() => page.props.app?.monetization_enabled !== false);

const navigation = computed(() => {
    const items = [
        { name: t('nav.home') || 'Home', href: localizedUrl('/'), icon: Home, key: 'home' },
        { name: t('nav.trending') || 'Trending', href: localizedUrl('/trending'), icon: TrendingUp, key: 'trending' },
        { name: t('nav.categories') || 'Categories', href: localizedUrl('/categories'), icon: LayoutGrid, key: 'categories' },
        { name: t('nav.tags') || 'Tags', href: localizedUrl('/tags'), icon: Tag, key: 'tags' },
    ];
    if (liveStreamingEnabled.value) {
        items.push({ name: t('nav.live') || 'Live', href: localizedUrl('/live'), icon: Radio, key: 'live' });
    }
    return items;
});

const libraryNav = computed(() => [
    { name: t('nav.playlists') || 'Playlists', href: '/playlists', icon: ListVideo, key: 'playlists' },
    { name: t('nav.history') || 'History', href: '/history', icon: History, key: 'history' },
]);

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        window.location.href = `${localizedUrl('/search')}?q=${encodeURIComponent(searchQuery.value)}`;
    }
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

// Mobile bottom navbar — scroll-aware show/hide
const showMobileNav = ref(true);
const lastScrollY = ref(0);
const footerRef = ref(null);

const handleScroll = () => {
    const currentY = window.scrollY;
    const scrollingDown = currentY > lastScrollY.value;

    // Hide when near footer
    if (footerRef.value) {
        const footerRect = footerRef.value.getBoundingClientRect();
        if (footerRect.top < window.innerHeight + 20) {
            showMobileNav.value = false;
            lastScrollY.value = currentY;
            return;
        }
    }

    // Hide on scroll down, show on scroll up (with 10px threshold)
    if (scrollingDown && currentY > 80) {
        showMobileNav.value = false;
    } else if (!scrollingDown) {
        showMobileNav.value = true;
    }

    lastScrollY.value = currentY;
};

const tSafe = (key, fallback) => {
    const val = t(key);
    return val !== key ? val : fallback;
};

const showMobileUploadMenu = ref(false);
const showMobileMoreMenu = ref(false);

const mobileNavItems = computed(() => [
    { name: tSafe('nav.home', 'Home'), href: localizedUrl('/'), icon: Home },
    { name: tSafe('common.search', 'Search'), href: null, action: 'search', icon: Search },
    { name: '+', href: null, action: 'upload', icon: Plus, isCenter: true },
    { name: tSafe('nav.categories', 'Categories'), href: localizedUrl('/categories'), icon: LayoutGrid },
    { name: tSafe('nav.more', 'More'), href: null, action: 'more', icon: MoreHorizontal },
]);

const mobileMoreItems = computed(() => [
    { name: tSafe('nav.trending', 'Trending'), href: localizedUrl('/trending'), icon: TrendingUp },
    { name: tSafe('nav.tags', 'Tags'), href: localizedUrl('/tags'), icon: Tag },
    { name: tSafe('nav.playlists', 'Playlists'), href: localizedUrl('/public-playlists'), icon: ListVideo },
]);

const handleMobileNavClick = (item) => {
    if (item.action === 'more') {
        showMobileMoreMenu.value = !showMobileMoreMenu.value;
        showMobileUploadMenu.value = false;
        return;
    }
    showMobileMoreMenu.value = false;
    if (item.action === 'search') {
        showMobileSearch.value = true;
    } else if (item.action === 'upload') {
        showMobileUploadMenu.value = !showMobileUploadMenu.value;
    }
};
</script>

<template>
    <div class="min-h-screen" style="background-color: var(--color-bg-primary);">
        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50 w-full" style="background-color: var(--color-bg-secondary); border-bottom: 1px solid var(--color-border);">
            <div class="flex items-center justify-between h-14 px-4 w-full">
                <!-- Left: Logo & Menu -->
                <div class="flex items-center gap-4">
                    <button @click="toggleSidebar" class="p-2 rounded-full hidden lg:flex" style="color: var(--color-text-primary);" :style="{ ':hover': { backgroundColor: 'var(--color-bg-card)' } }" aria-label="Toggle sidebar">
                        <Menu class="w-5 h-5" />
                    </button>
                    <Link href="/" class="flex items-center">
                        <img
                            v-if="themeSettings.site_logo"
                            :src="themeSettings.site_logo"
                            :alt="themeSettings.siteTitle || 'HubTube'"
                            class="h-8 object-contain"
                        />
                        <span 
                            v-else
                            class="font-bold truncate max-w-[120px] sm:max-w-none"
                            :style="{
                                color: themeSettings.siteTitleColor || 'var(--color-text-primary)',
                                fontSize: (themeSettings.siteTitleSize || 20) + 'px',
                                fontFamily: themeSettings.siteTitleFont || 'inherit'
                            }"
                        >
                            {{ themeSettings.siteTitle || 'HubTube' }}
                        </span>
                    </Link>
                </div>

                <!-- Center: Search -->
                <div class="flex-1 max-w-2xl mx-4 hidden md:block">
                    <form @submit.prevent="handleSearch" class="relative" role="search">
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('common.search_placeholder') || 'Search videos...'"
                            class="input pr-12"
                            aria-label="Search videos"
                        />
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full hover:opacity-80" style="color: var(--color-text-muted);" aria-label="Search">
                            <Search class="w-5 h-5" />
                        </button>
                    </form>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2">
                    <LanguageSwitcher mobile align="right" class="md:hidden" />

                    <template v-if="user">
                        <!-- Upload Dropdown -->
                        <div class="relative hidden sm:block">
                            <button 
                                @click="showUploadMenu = !showUploadMenu; showNotifications = false; showUserMenu = false"
                                class="upload-menu-trigger p-2 rounded-full hover:opacity-80 transition-opacity"
                                style="color: var(--color-text-secondary);"
                                title="Upload"
                                aria-label="Upload"
                            >
                                <Upload class="w-5 h-5" />
                            </button>
                            <div v-if="showUploadMenu" class="upload-menu-dropdown absolute right-0 mt-2 w-44 card p-1 shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
                                <Link href="/upload" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80 transition-opacity" style="color: var(--color-text-primary);" @click="showUploadMenu = false">
                                    <Film class="w-4 h-4" style="color: var(--color-text-secondary);" />
                                    <span class="text-sm">{{ t('nav.upload_video') || 'Upload Video' }}</span>
                                </Link>
                                <Link href="/image-upload" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80 transition-opacity" style="color: var(--color-text-primary);" @click="showUploadMenu = false">
                                    <ImageIcon class="w-4 h-4" style="color: var(--color-text-secondary);" />
                                    <span class="text-sm">{{ t('nav.upload_image') || 'Upload Image' }}</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Go Live Icon -->
                        <Link v-if="liveStreamingEnabled" href="/go-live" class="p-2 rounded-full hover:opacity-80 transition-opacity hidden sm:flex" style="color: var(--color-text-secondary);" title="Go Live" aria-label="Go Live">
                            <Radio class="w-5 h-5" />
                        </Link>

                        <div class="relative">
                            <button @click="toggleNotifications" class="notification-trigger p-2 rounded-full relative" style="color: var(--color-text-secondary);" aria-label="Notifications">
                                <Bell class="w-5 h-5" />
                                <span v-if="unreadCount > 0" class="absolute top-1 right-1 w-2 h-2 rounded-full" style="background-color: var(--color-accent);"></span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div v-if="showNotifications" class="notification-dropdown absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto scrollbar-hide card shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
                                <div class="flex items-center justify-between p-3 border-b" style="border-color: var(--color-border);">
                                    <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">{{ t('nav.notifications') || 'Notifications' }}</h3>
                                    <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs hover:opacity-80" style="color: var(--color-accent);">
                                        {{ t('nav.mark_all_read') || 'Mark all read' }}
                                    </button>
                                </div>
                                <div v-if="notifications.length">
                                    <component
                                        :is="notif.data?.url ? 'a' : 'div'"
                                        v-for="notif in notifications"
                                        :key="notif.id"
                                        :href="notif.data?.url || undefined"
                                        @click="notif.data?.url ? (showNotifications = false) : null"
                                        class="flex items-start gap-3 p-3 border-b last:border-b-0 transition-colors hover:opacity-80"
                                        :style="{
                                            borderColor: 'var(--color-border)',
                                            backgroundColor: !notif.read_at ? 'rgba(var(--color-accent-rgb, 220, 38, 38), 0.03)' : 'transparent',
                                            display: 'flex',
                                            textDecoration: 'none',
                                        }"
                                    >
                                        <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center" style="background-color: var(--color-bg-secondary);">
                                            <Bell class="w-4 h-4" style="color: var(--color-text-muted);" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium" style="color: var(--color-text-primary);">{{ notif.title }}</p>
                                            <p class="text-xs mt-0.5 line-clamp-2" style="color: var(--color-text-muted);">{{ notif.message }}</p>
                                        </div>
                                        <div v-if="!notif.read_at" class="w-2 h-2 rounded-full shrink-0 mt-2" style="background-color: var(--color-accent);"></div>
                                    </component>
                                </div>
                                <div v-else class="p-6 text-center">
                                    <Bell class="w-8 h-8 mx-auto mb-2" style="color: var(--color-text-muted);" />
                                    <p class="text-sm" style="color: var(--color-text-secondary);">{{ t('nav.no_notifications') || 'No notifications' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <button @click="showUserMenu = !showUserMenu; showNotifications = false" class="user-menu-trigger flex items-center gap-2" aria-label="User menu">
                                <div class="w-8 h-8 avatar">
                                    <img :src="user.avatar || '/images/default_avatar.webp'" :alt="user.username" class="w-full h-full object-cover" />
                                </div>
                            </button>

                            <div v-if="showUserMenu" class="user-menu-dropdown absolute right-0 mt-2 w-56 card p-2 shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
                                <div class="px-3 py-2" style="border-bottom: 1px solid var(--color-border);">
                                    <p class="font-medium" style="color: var(--color-text-primary);">{{ user.username }}</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">{{ user.email }}</p>
                                </div>
                                <div class="py-2">
                                    <!-- Admin Panel Link - Only for admins -->
                                    <a 
                                        v-if="user.is_admin" 
                                        href="/admin" 
                                        class="flex items-center gap-3 px-3 py-2 rounded-lg"
                                        style="color: var(--color-accent);"
                                    >
                                        <Shield class="w-4 h-4" />
                                        <span>{{ t('nav.admin_panel') || 'Admin Panel' }}</span>
                                    </a>
                                    <Link href="/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <LayoutDashboard class="w-4 h-4" />
                                        <span>{{ t('nav.dashboard') || 'Dashboard' }}</span>
                                    </Link>
                                    <Link :href="`/channel/${user.username}`" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <User class="w-4 h-4" />
                                        <span>{{ t('nav.your_channel') || 'Your Channel' }}</span>
                                    </Link>
                                    <Link href="/feed" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <Rss class="w-4 h-4" />
                                        <span>{{ t('nav.subscriptions') || 'Subscriptions' }}</span>
                                    </Link>
                                    <Link v-if="monetizationEnabled" href="/wallet" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <Wallet class="w-4 h-4" />
                                        <span>{{ t('nav.wallet') || 'Wallet' }}: ${{ user.wallet_balance }}</span>
                                    </Link>
                                    <Link href="/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <Settings class="w-4 h-4" />
                                        <span>{{ t('nav.settings') || 'Settings' }}</span>
                                    </Link>
                                </div>
                                <!-- Theme Toggle -->
                                <div v-if="themeSettings.allowToggle" class="py-2" style="border-top: 1px solid var(--color-border);">
                                    <p class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">{{ t('nav.theme') || 'Theme' }}</p>
                                    <div class="flex gap-1 px-2">
                                        <button 
                                            @click="setTheme('light')"
                                            :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm', currentTheme === 'light' ? 'bg-primary-600 text-white' : '']"
                                            :style="currentTheme === 'light' ? { color: '#f59e0b' } : { color: 'var(--color-text-secondary)' }"
                                            aria-label="Light mode"
                                        >
                                            <Sun class="w-4 h-4" />
                                        </button>
                                        <button 
                                            @click="setTheme('dark')"
                                            :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm', currentTheme === 'dark' ? 'bg-primary-600 text-white' : '']"
                                            :style="currentTheme !== 'dark' ? { color: 'var(--color-text-secondary)' } : {}"
                                            aria-label="Dark mode"
                                        >
                                            <Moon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div class="pt-2" style="border-top: 1px solid var(--color-border);">
                                    <Link href="/logout" method="post" as="button" class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-left text-red-400">
                                        <LogOut class="w-4 h-4" />
                                        <span>{{ t('nav.sign_out') || 'Sign Out' }}</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <button @click="showLoginModal = true" class="btn btn-primary">Login / Register</button>
                    </template>
                </div>
            </div>

            <!-- Mega Menu Bar (desktop only) -->
            <div v-if="headerMenuItems.length" class="hidden lg:block border-t mega-menu-area" style="border-color: var(--color-border);">
                <div class="flex items-center gap-1 px-4 h-10">
                    <template v-for="item in headerMenuItems" :key="item.id">
                        <!-- Divider -->
                        <div v-if="item.type === 'divider'" class="w-px h-5 mx-1" style="background-color: var(--color-border);"></div>

                        <!-- Dropdown / Mega menu parent -->
                        <div v-else-if="(item.type === 'dropdown' || item.is_mega) && item.children?.length" class="relative">
                            <button
                                @click.stop="toggleMegaMenu(item.id)"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors hover:opacity-80"
                                style="color: var(--color-text-secondary);"
                            >
                                <component v-if="item.icon && getMenuIcon(item.icon)" :is="getMenuIcon(item.icon)" class="w-4 h-4" />
                                <span>{{ item.label }}</span>
                                <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': openMegaMenu === item.id }" />
                            </button>

                            <!-- Mega dropdown -->
                            <div
                                v-if="openMegaMenu === item.id"
                                class="absolute left-0 top-full mt-1 card shadow-xl p-4 z-50"
                                :style="{
                                    backgroundColor: 'var(--color-bg-card)',
                                    border: '1px solid var(--color-border)',
                                    minWidth: item.is_mega ? (item.mega_columns * 160 + 'px') : '200px',
                                }"
                            >
                                <div
                                    :class="item.is_mega ? 'grid gap-3' : 'flex flex-col gap-1'"
                                    :style="item.is_mega ? { gridTemplateColumns: `repeat(${item.mega_columns || 4}, minmax(0, 1fr))` } : {}"
                                >
                                    <template v-for="child in item.children" :key="child.id">
                                        <Link
                                            v-if="child.type !== 'divider'"
                                            :href="child.url || '#'"
                                            :target="child.target || '_self'"
                                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors hover:opacity-80"
                                            style="color: var(--color-text-secondary);"
                                            @click="closeMegaMenu"
                                        >
                                            <component v-if="child.icon && getMenuIcon(child.icon)" :is="getMenuIcon(child.icon)" class="w-4 h-4 shrink-0" />
                                            <span>{{ child.label }}</span>
                                            <ExternalLink v-if="child.target === '_blank'" class="w-3 h-3 ml-auto opacity-50" />
                                        </Link>
                                        <div v-else class="border-t my-1" style="border-color: var(--color-border);"></div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Regular link -->
                        <Link
                            v-else
                            :href="item.url || '#'"
                            :target="item.target || '_self'"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors hover:opacity-80"
                            style="color: var(--color-text-secondary);"
                        >
                            <component v-if="item.icon && getMenuIcon(item.icon)" :is="getMenuIcon(item.icon)" class="w-4 h-4" />
                            <span>{{ item.label }}</span>
                            <ExternalLink v-if="item.target === '_blank'" class="w-3 h-3 opacity-50" />
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed left-0 bottom-0 overflow-y-auto scrollbar-hide hidden lg:block transition-all duration-300 z-30',
                sidebarCollapsed ? 'w-16' : 'sidebar-expanded'
            ]"
            :style="{
                top: headerMenuItems.length ? '96px' : '56px',
                backgroundColor: 'var(--color-bg-secondary)',
                borderRight: '1px solid var(--color-border)',
            }"
        >
            <nav class="p-2" aria-label="Main navigation">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.name">
                        <Link 
                            :href="item.href" 
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-lg transition-colors hover:opacity-80',
                                sidebarCollapsed ? 'justify-center' : ''
                            ]"
                            :title="sidebarCollapsed ? item.name : ''"
                            :aria-label="item.name"
                            style="color: var(--color-text-secondary);"
                        >
                            <component 
                                :is="item.icon" 
                                class="w-5 h-5 shrink-0" 
                                :style="{ color: getIconColor(item.key) }"
                            />
                            <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>

                <template v-if="user && !sidebarCollapsed">
                    <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                        <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-muted);">{{ t('nav.library') || 'Library' }}</h3>
                        <ul class="space-y-1">
                            <li v-for="item in libraryNav" :key="item.name">
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80" style="color: var(--color-text-secondary);">
                                    <component 
                                        :is="item.icon" 
                                        class="w-5 h-5" 
                                        :style="{ color: getIconColor(item.key) }"
                                    />
                                    <span>{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>

                <template v-if="user && sidebarCollapsed">
                    <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                        <ul class="space-y-1">
                            <li v-for="item in libraryNav" :key="item.name">
                                <Link 
                                    :href="item.href" 
                                    class="flex items-center justify-center px-3 py-2 rounded-lg hover:opacity-80"
                                    :title="item.name"
                                    style="color: var(--color-text-secondary);"
                                >
                                    <component 
                                        :is="item.icon" 
                                        class="w-5 h-5" 
                                        :style="{ color: getIconColor(item.key) }"
                                    />
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>

                <!-- Language Switcher -->
                <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                    <LanguageSwitcher :compact="sidebarCollapsed" />
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main 
            v-motion
            :initial="{ opacity: 0, y: 8 }"
            :enter="{ opacity: 1, y: 0, transition: { duration: 0.2 } }"
            :leave="{ opacity: 0, y: -8, transition: { duration: 0.15 } }"
            :class="['transition-all duration-300', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-sidebar']"
            :style="{ paddingTop: headerMenuItems.length ? '96px' : '56px' }"
        >
            <div class="px-3 py-4 pb-20 lg:pb-4 sm:p-4 sm:pb-20 lg:p-6">
                <slot />
            </div>
        </main>

        <!-- Mobile Search Overlay -->
        <div v-if="showMobileSearch" class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-4" style="background-color: rgba(0,0,0,0.6);" @click.self="showMobileSearch = false">
            <div
                v-motion
                :initial="{ opacity: 0, y: -10 }"
                :enter="{ opacity: 1, y: 0, transition: { duration: 0.18 } }"
                :leave="{ opacity: 0, y: -10, transition: { duration: 0.12 } }"
                class="w-full max-w-lg card p-4 shadow-xl"
                style="background-color: var(--color-bg-card);"
            >
                <form @submit.prevent="handleMobileSearch" class="flex items-center gap-2" role="search">
                    <input
                        v-model="mobileSearchQuery"
                        type="text"
                        :placeholder="t('common.search_placeholder') || 'Search videos...'"
                        class="input flex-1"
                        aria-label="Search videos"
                        autofocus
                    />
                    <button type="submit" class="btn btn-primary p-2" aria-label="Search">
                        <Search class="w-5 h-5" />
                    </button>
                    <button type="button" @click="showMobileSearch = false" class="p-2 rounded-full" style="color: var(--color-text-secondary);" aria-label="Close search">
                        <X class="w-5 h-5" />
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <footer
            ref="footerRef"
            :class="['transition-all duration-300 py-6 px-4 mt-8', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-sidebar']"
            style="border-top: 1px solid var(--color-border);"
        >
            <div class="max-w-6xl mx-auto">
                <!-- Footer Ad Banner -->
                <div v-if="themeSettings.footer_ad_enabled && (themeSettings.footer_ad_code || themeSettings.footer_ad_mobile_code)" class="flex justify-center mb-6">
                    <AdSlot :html="themeSettings.footer_ad_code" class="hidden sm:block" />
                    <AdSlot :html="themeSettings.footer_ad_mobile_code || themeSettings.footer_ad_code" class="sm:hidden" />
                </div>

                <!-- Site Logo / Title -->
                <div class="flex justify-center mb-4">
                    <a href="/" class="inline-flex items-center gap-2 hover:opacity-80 transition-opacity">
                        <img
                            v-if="themeSettings.footer_logo_url"
                            :src="themeSettings.footer_logo_url"
                            alt="Site Logo"
                            class="h-8 object-contain"
                            loading="lazy"
                        />
                        <span
                            v-else
                            class="text-lg font-bold"
                            :style="{
                                color: themeSettings.site_title_color || 'var(--color-text-primary)',
                                fontFamily: themeSettings.site_title_font || 'inherit',
                            }"
                        >{{ themeSettings.site_title || 'HubTube' }}</span>
                    </a>
                </div>

                <!-- Legal Links -->
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs" style="color: var(--color-text-muted);">
                    <a href="/pages/terms-of-service" class="hover:opacity-80" style="color: var(--color-text-muted);">{{ t('footer.terms') || 'Terms of Service' }}</a>
                    <a href="/pages/privacy-policy" class="hover:opacity-80" style="color: var(--color-text-muted);">{{ t('footer.privacy') || 'Privacy Policy' }}</a>
                    <a href="/pages/dmca" class="hover:opacity-80" style="color: var(--color-text-muted);">{{ t('footer.dmca') || 'DMCA' }}</a>
                    <a href="/pages/community-guidelines" class="hover:opacity-80" style="color: var(--color-text-muted);">{{ t('footer.guidelines') || 'Community Guidelines' }}</a>
                    <a href="/pages/cookie-policy" class="hover:opacity-80" style="color: var(--color-text-muted);">{{ t('footer.cookies') || 'Cookie Policy' }}</a>
                    <a href="/contact" class="hover:opacity-80" style="color: var(--color-text-muted);">{{ t('footer.contact') || 'Contact' }}</a>
                </div>
            </div>
        </footer>

        <!-- Mobile Bottom Navbar Dock -->
        <Transition name="mobile-nav">
            <nav
                v-if="showMobileNav"
                class="fixed bottom-4 left-0 right-0 z-40 mx-auto w-[calc(100%-32px)] max-w-lg lg:hidden"
                aria-label="Mobile navigation"
            >
                <div
                    class="flex justify-between items-center px-3 py-2 rounded-2xl shadow-lg"
                    style="background-color: var(--color-bg-secondary); border: 1px solid var(--color-border);"
                >
                    <template v-for="item in mobileNavItems" :key="item.name">
                        <!-- Center Upload Button -->
                        <div v-if="item.isCenter" class="mobile-upload-menu relative flex flex-col items-center justify-center" style="flex: 1 1 0%; min-width: 0;">
                            <!-- Upload Menu Popup -->
                            <Transition name="fade">
                                <div
                                    v-if="showMobileUploadMenu"
                                    class="absolute bottom-full mb-3 rounded-xl shadow-xl p-1.5 w-[120px]"
                                    style="background-color: var(--color-bg-card); border: 1px solid var(--color-border); left: 50%; transform: translateX(-50%);"
                                >
                                    <Link href="/upload" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg hover:opacity-80 transition-opacity" style="color: var(--color-text-primary);" @click="showMobileUploadMenu = false">
                                        <Video class="w-4 h-4 shrink-0" style="color: var(--color-text-secondary);" />
                                        <span class="text-sm">Video</span>
                                    </Link>
                                    <Link href="/image-upload" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg hover:opacity-80 transition-opacity" style="color: var(--color-text-primary);" @click="showMobileUploadMenu = false">
                                        <ImageIcon class="w-4 h-4 shrink-0" style="color: var(--color-text-secondary);" />
                                        <span class="text-sm">{{ t('nav.upload_image') || 'Image' }}</span>
                                    </Link>
                                </div>
                            </Transition>
                            <button
                                class="flex items-center justify-center w-11 h-11 rounded-full shadow-lg active:scale-95 transition-all duration-200 mx-auto"
                                :style="{ backgroundColor: 'var(--color-accent)' }"
                                :aria-label="item.name"
                                @click="handleMobileNavClick(item)"
                            >
                                <component :is="item.icon" class="w-6 h-6 text-white" />
                            </button>
                        </div>
                        <!-- More Button with popup menu -->
                        <div v-else-if="item.action === 'more'" class="relative flex flex-col items-center justify-center" style="flex: 1 1 0%; min-width: 0;">
                            <!-- More Menu Popup -->
                            <Transition name="fade">
                                <div
                                    v-if="showMobileMoreMenu"
                                    class="absolute bottom-full mb-3 right-0 rounded-xl shadow-xl p-2 min-w-[160px]"
                                    style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
                                >
                                    <Link
                                        v-for="moreItem in mobileMoreItems"
                                        :key="moreItem.name"
                                        :href="moreItem.href"
                                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:opacity-80 transition-opacity"
                                        style="color: var(--color-text-primary);"
                                        @click="showMobileMoreMenu = false"
                                    >
                                        <component :is="moreItem.icon" class="w-4 h-4" style="color: var(--color-text-secondary);" />
                                        <span class="text-sm">{{ moreItem.name }}</span>
                                    </Link>
                                </div>
                            </Transition>
                            <button
                                class="flex flex-col items-center justify-center p-2 group"
                                :aria-label="item.name"
                                @click="handleMobileNavClick(item)"
                            >
                                <component :is="item.icon" class="w-5 h-5 transition-colors" :style="{ color: 'var(--color-text-secondary)' }" />
                                <span class="text-[10px] mt-0.5 transition-colors" style="color: var(--color-text-muted);">{{ item.name }}</span>
                            </button>
                        </div>

                        <!-- Regular Nav Button -->
                        <component
                            v-else
                            :is="item.href ? Link : 'button'"
                            :href="item.href || undefined"
                            class="flex flex-col items-center justify-center p-2 group"
                            style="flex: 1 1 0%; min-width: 0;"
                            :aria-label="item.name"
                            @click="!item.href ? handleMobileNavClick(item) : null"
                        >
                            <component
                                :is="item.icon"
                                class="w-5 h-5 transition-colors"
                                :style="{ color: 'var(--color-text-secondary)' }"
                            />
                            <span
                                class="text-[10px] mt-0.5 transition-colors"
                                style="color: var(--color-text-muted);"
                            >{{ item.name }}</span>
                        </component>
                    </template>
                </div>
            </nav>
        </Transition>

        <!-- Login Modal -->
        <Teleport to="body">
            <Transition name="login-modal">
                <div v-if="showLoginModal && !user" class="fixed inset-0 z-[9998] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Sign in" @click.self="showLoginModal = false">
                    <div class="fixed inset-0 bg-black/60" style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);" @click="showLoginModal = false"></div>
                    <div class="relative w-full max-w-md rounded-xl shadow-2xl" style="background-color: var(--color-bg-card);">
                        <!-- Close button -->
                        <button @click="showLoginModal = false" class="absolute top-3 right-3 p-1 rounded-full hover:opacity-80" style="color: var(--color-text-muted);" aria-label="Close login">
                            <X class="w-5 h-5" />
                        </button>

                        <div class="p-6">
                            <div class="text-center mb-6">
                                <Link href="/" class="inline-block">
                                    <img v-if="themeSettings.site_logo" :src="themeSettings.site_logo" alt="Logo" class="h-12 w-auto mx-auto object-contain" />
                                    <div v-else class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto" style="background-color: var(--color-accent);">
                                        <span class="text-2xl font-bold text-white">{{ (themeSettings.siteTitle || 'H').charAt(0).toUpperCase() }}</span>
                                    </div>
                                </Link>
                                <h2 class="text-xl font-bold mt-3" style="color: var(--color-text-primary);">{{ t('auth.welcome_back') || 'Welcome back' }}</h2>
                                <p class="text-sm mt-1" style="color: var(--color-text-secondary);">{{ t('auth.sign_in_desc') || 'Sign in to your account' }}</p>
                            </div>

                            <form @submit.prevent="submitLogin" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                                        {{ t('auth.email_or_username') || 'Email or Username' }}
                                    </label>
                                    <input
                                        v-model="loginForm.login"
                                        type="text"
                                        class="input"
                                        required
                                        autofocus
                                    />
                                    <p v-if="loginForm.errors.login" class="text-red-500 text-sm mt-1">{{ loginForm.errors.login }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                                        {{ t('auth.password') || 'Password' }}
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model="loginForm.password"
                                            :type="showLoginPassword ? 'text' : 'password'"
                                            class="input pr-10"
                                            required
                                        />
                                        <button
                                            type="button"
                                            @click="showLoginPassword = !showLoginPassword"
                                            class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-secondary);"
                                            :aria-label="showLoginPassword ? 'Hide password' : 'Show password'"
                                        >
                                            <EyeOff v-if="showLoginPassword" class="w-5 h-5" />
                                            <Eye v-else class="w-5 h-5" />
                                        </button>
                                    </div>
                                    <p v-if="loginForm.errors.password" class="text-red-500 text-sm mt-1">{{ loginForm.errors.password }}</p>
                                </div>

                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="loginForm.remember" type="checkbox" class="w-4 h-4 rounded" />
                                        <span class="text-sm" style="color: var(--color-text-secondary);">{{ t('auth.remember_me') || 'Remember me' }}</span>
                                    </label>
                                    <Link href="/forgot-password" class="text-sm" style="color: var(--color-accent);" @click="showLoginModal = false">
                                        {{ t('auth.forgot_password') || 'Forgot password?' }}
                                    </Link>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="loginForm.processing"
                                    class="btn btn-primary w-full"
                                >
                                    <span v-if="loginForm.processing">{{ t('auth.signing_in') || 'Signing in...' }}</span>
                                    <span v-else>{{ t('auth.login') || 'Sign In' }}</span>
                                </button>
                            </form>

                            <div class="mt-6 text-center">
                                <p style="color: var(--color-text-secondary);">
                                    {{ t('auth.no_account') || "Don't have an account?" }}
                                    <Link href="/register" class="font-medium" style="color: var(--color-accent);" @click="showLoginModal = false">
                                        {{ t('auth.sign_up') || 'Sign up' }}
                                    </Link>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Toast Notifications -->
        <ToastContainer />
        
        <!-- Age Verification Modal -->
        <AgeVerificationModal />

        <!-- Translation Loading Overlay — full-page blur with min 3s display -->
        <Transition name="translate-fade">
            <div v-if="showOverlay" class="fixed inset-0 z-[9999] flex items-center justify-center" style="background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
                <div class="flex flex-col items-center gap-4">
                    <img src="/images/globe.svg" alt="" class="w-16 h-16 animate-spin-slow" style="filter: invert(1) brightness(2);" />
                    <span class="text-base font-semibold text-white tracking-wide">{{ t('common.translating') || 'Translating...' }}</span>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.sidebar-expanded {
    width: 160px;
}

@media (min-width: 1024px) {
    .lg\:pl-sidebar {
        padding-left: 160px;
    }
}

/* Globe spin animation — slower than default for a smooth feel */
.animate-spin-slow {
    animation: spin 2.5s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Translate overlay fade transition */
.translate-fade-enter-active {
    transition: opacity 0.4s ease-out;
}
.translate-fade-leave-active {
    transition: opacity 0.6s ease-in;
}
.translate-fade-enter-from,
.translate-fade-leave-to {
    opacity: 0;
}

/* Mobile bottom navbar slide transition */
.mobile-nav-enter-active {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease-out;
}
.mobile-nav-leave-active {
    transition: transform 0.25s ease-in, opacity 0.25s ease-in;
}
.mobile-nav-enter-from,
.mobile-nav-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
.mobile-nav-enter-to,
.mobile-nav-leave-from {
    transform: translateY(0);
    opacity: 1;
}
</style>
