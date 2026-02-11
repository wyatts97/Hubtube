<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, 
    Video, Radio, Home, TrendingUp, Zap, ListVideo, History, 
    ChevronLeft, ChevronRight, Shield, Sun, Moon, Monitor,
    X, Check, CheckCheck, Rss, LayoutDashboard, ChevronDown, ChevronUp, Film, Clapperboard,
    Tag, Folder, Star, ExternalLink
} from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';
import { useToast } from '@/Composables/useToast';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import ToastContainer from '@/Components/ToastContainer.vue';
import AgeVerificationModal from '@/Components/AgeVerificationModal.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const toast = useToast();
const { get, post } = useFetch();
const { localizedUrl, t, isTranslated } = useI18n();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const themeSettings = computed(() => page.props.theme || {});
const iconSettings = computed(() => themeSettings.value?.icons || {});
const showUserMenu = ref(false);
const showMobileMenu = ref(false);
const showUploadMenu = ref(false);
const sidebarCollapsed = ref(false);
const searchQuery = ref('');
const showMobileSearch = ref(false);
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
};

onMounted(() => {
    document.addEventListener('click', closeDropdowns);
    if (user.value) {
        // Fetch unread count on mount
        fetch('/notifications/unread-count', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(r => r.ok ? r.json() : null).then(d => {
            if (d) unreadCount.value = d.count || 0;
        }).catch(() => {});
    }
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdowns);
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
    
    // Check for global icon color
    if (icons.colorMode === 'custom' && icons.globalColor) {
        return icons.globalColor;
    }
    
    // Default to text secondary
    return 'var(--color-text-secondary)';
};

const navigation = computed(() => [
    { name: t('nav.home') || 'Home', href: localizedUrl('/'), icon: Home, key: 'home' },
    { name: t('nav.trending') || 'Trending', href: localizedUrl('/trending'), icon: TrendingUp, key: 'trending' },
    { name: t('nav.shorts') || 'Shorts', href: localizedUrl('/shorts'), icon: Zap, key: 'shorts' },
    { name: t('nav.live') || 'Live', href: localizedUrl('/live'), icon: Radio, key: 'live' },
]);

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
</script>

<template>
    <div class="min-h-screen" style="background-color: var(--color-bg-primary);">
        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50 w-full" style="background-color: var(--color-bg-secondary); border-bottom: 1px solid var(--color-border);">
            <div class="flex items-center justify-between h-14 px-4 w-full">
                <!-- Left: Logo & Menu -->
                <div class="flex items-center gap-4">
                    <button @click="toggleSidebar" class="p-2 rounded-full hidden lg:flex" style="color: var(--color-text-primary);" :style="{ ':hover': { backgroundColor: 'var(--color-bg-card)' } }">
                        <Menu class="w-5 h-5" />
                    </button>
                    <button @click="showMobileMenu = !showMobileMenu" class="p-2 rounded-full lg:hidden" style="color: var(--color-text-primary);">
                        <Menu class="w-5 h-5" />
                    </button>
                    <Link href="/" class="flex items-center">
                        <span 
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
                    <form @submit.prevent="handleSearch" class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search videos..."
                            class="input pr-12"
                        />
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-full hover:opacity-80" style="color: var(--color-text-muted);">
                            <Search class="w-5 h-5" />
                        </button>
                    </form>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2">
                    <button @click="showMobileSearch = true" class="p-2 rounded-full md:hidden" style="color: var(--color-text-secondary);">
                        <Search class="w-5 h-5" />
                    </button>

                    <template v-if="user">
                        <!-- Upload Dropdown -->
                        <div class="relative hidden sm:block">
                            <button 
                                @click="showUploadMenu = !showUploadMenu; showNotifications = false; showUserMenu = false"
                                class="upload-menu-trigger p-2 rounded-full hover:opacity-80 transition-opacity"
                                style="color: var(--color-text-secondary);"
                                title="Upload"
                            >
                                <Upload class="w-5 h-5" />
                            </button>
                            <div v-if="showUploadMenu" class="upload-menu-dropdown absolute right-0 mt-2 w-44 card p-1 shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
                                <Link href="/upload" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80 transition-opacity" style="color: var(--color-text-primary);" @click="showUploadMenu = false">
                                    <Film class="w-4 h-4" style="color: var(--color-text-secondary);" />
                                    <span class="text-sm">Upload Video</span>
                                </Link>
                                <Link href="/upload?type=short" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80 transition-opacity" style="color: var(--color-text-primary);" @click="showUploadMenu = false">
                                    <Clapperboard class="w-4 h-4" style="color: var(--color-text-secondary);" />
                                    <span class="text-sm">Upload Short</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Go Live Icon -->
                        <Link href="/go-live" class="p-2 rounded-full hover:opacity-80 transition-opacity hidden sm:flex" style="color: var(--color-text-secondary);" title="Go Live">
                            <Radio class="w-5 h-5" />
                        </Link>

                        <div class="relative">
                            <button @click="toggleNotifications" class="notification-trigger p-2 rounded-full relative" style="color: var(--color-text-secondary);">
                                <Bell class="w-5 h-5" />
                                <span v-if="unreadCount > 0" class="absolute top-1 right-1 w-2 h-2 rounded-full" style="background-color: var(--color-accent);"></span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div v-if="showNotifications" class="notification-dropdown absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto scrollbar-hide card shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
                                <div class="flex items-center justify-between p-3 border-b" style="border-color: var(--color-border);">
                                    <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">Notifications</h3>
                                    <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs hover:opacity-80" style="color: var(--color-accent);">
                                        Mark all read
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
                                    <p class="text-sm" style="color: var(--color-text-secondary);">No notifications</p>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <button @click="showUserMenu = !showUserMenu; showNotifications = false" class="user-menu-trigger flex items-center gap-2">
                                <div class="w-8 h-8 avatar">
                                    <img v-if="user.avatar" :src="user.avatar" :alt="user.username" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white font-medium">
                                        {{ user.username?.charAt(0)?.toUpperCase() }}
                                    </div>
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
                                        <span>Admin Panel</span>
                                    </a>
                                    <Link href="/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <LayoutDashboard class="w-4 h-4" />
                                        <span>Dashboard</span>
                                    </Link>
                                    <Link :href="`/channel/${user.username}`" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <User class="w-4 h-4" />
                                        <span>Your Channel</span>
                                    </Link>
                                    <Link href="/feed" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <Rss class="w-4 h-4" />
                                        <span>Subscriptions</span>
                                    </Link>
                                    <Link href="/wallet" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <Wallet class="w-4 h-4" />
                                        <span>Wallet: ${{ user.wallet_balance }}</span>
                                    </Link>
                                    <Link href="/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <Settings class="w-4 h-4" />
                                        <span>Settings</span>
                                    </Link>
                                </div>
                                <!-- Theme Toggle -->
                                <div v-if="themeSettings.allowToggle" class="py-2" style="border-top: 1px solid var(--color-border);">
                                    <p class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Theme</p>
                                    <div class="flex gap-1 px-2">
                                        <button 
                                            @click="setTheme('light')"
                                            :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm', currentTheme === 'light' ? 'bg-primary-600 text-white' : '']"
                                            :style="currentTheme === 'light' ? { color: '#f59e0b' } : { color: 'var(--color-text-secondary)' }"
                                        >
                                            <Sun class="w-4 h-4" />
                                        </button>
                                        <button 
                                            @click="setTheme('dark')"
                                            :class="['flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm', currentTheme === 'dark' ? 'bg-primary-600 text-white' : '']"
                                            :style="currentTheme !== 'dark' ? { color: 'var(--color-text-secondary)' } : {}"
                                        >
                                            <Moon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div class="pt-2" style="border-top: 1px solid var(--color-border);">
                                    <Link href="/logout" method="post" as="button" class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-left text-red-400">
                                        <LogOut class="w-4 h-4" />
                                        <span>Sign Out</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <Link href="/login" class="btn btn-ghost">Sign In</Link>
                        <Link href="/register" class="btn btn-primary hidden sm:flex">Sign Up</Link>
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
                'fixed left-0 bottom-0 overflow-y-auto scrollbar-hide hidden lg:block transition-all duration-300',
                sidebarCollapsed ? 'w-16' : 'sidebar-expanded'
            ]"
            :style="{
                top: headerMenuItems.length ? '96px' : '56px',
                backgroundColor: 'var(--color-bg-secondary)',
                borderRight: '1px solid var(--color-border)',
            }"
        >
            <nav class="p-2">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.name">
                        <Link 
                            :href="item.href" 
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-lg transition-colors hover:opacity-80',
                                sidebarCollapsed ? 'justify-center' : ''
                            ]"
                            :title="sidebarCollapsed ? item.name : ''"
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

        <!-- Mobile Sidebar -->
        <div 
            v-if="showMobileMenu" 
            class="fixed inset-0 z-40 lg:hidden"
            @click="showMobileMenu = false"
        >
            <div class="absolute inset-0 bg-black/50"></div>
            <aside class="absolute left-0 top-0 bottom-0 w-64 pt-14 overflow-y-auto scrollbar-hide" style="background-color: var(--color-bg-secondary);">
                <nav class="p-4">
                    <ul class="space-y-1">
                        <li v-for="item in navigation" :key="item.name">
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
                    
                    <template v-if="user">
                        <!-- Mobile Create Actions -->
                        <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                            <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-muted);">Create</h3>
                            <ul class="space-y-1">
                                <li>
                                    <Link href="/upload" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80" style="color: var(--color-text-secondary);">
                                        <Film class="w-5 h-5" style="color: var(--color-text-secondary);" />
                                        <span>Upload Video</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/upload?type=short" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80" style="color: var(--color-text-secondary);">
                                        <Clapperboard class="w-5 h-5" style="color: var(--color-text-secondary);" />
                                        <span>Upload Short</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/go-live" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80" style="color: var(--color-text-secondary);">
                                        <Radio class="w-5 h-5" style="color: var(--color-text-secondary);" />
                                        <span>Go Live</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Mobile Custom Menu Items -->
                        <div v-if="mobileMenuItems.length" class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                            <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-muted);">Browse</h3>
                            <ul class="space-y-1">
                                <template v-for="item in mobileMenuItems" :key="item.id">
                                    <li v-if="item.type === 'divider'" class="my-2 mx-3 border-t" style="border-color: var(--color-border);"></li>
                                    <li v-else-if="item.children?.length">
                                        <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-muted);">{{ item.label }}</p>
                                        <ul class="space-y-0.5 pl-2">
                                            <li v-for="child in item.children" :key="child.id">
                                                <Link
                                                    :href="child.url || '#'"
                                                    :target="child.target || '_self'"
                                                    class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80"
                                                    style="color: var(--color-text-secondary);"
                                                >
                                                    <component v-if="child.icon && getMenuIcon(child.icon)" :is="getMenuIcon(child.icon)" class="w-4 h-4" />
                                                    <span class="text-sm">{{ child.label }}</span>
                                                </Link>
                                            </li>
                                        </ul>
                                    </li>
                                    <li v-else>
                                        <Link
                                            :href="item.url || '#'"
                                            :target="item.target || '_self'"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80"
                                            style="color: var(--color-text-secondary);"
                                        >
                                            <component v-if="item.icon && getMenuIcon(item.icon)" :is="getMenuIcon(item.icon)" class="w-5 h-5" />
                                            <span>{{ item.label }}</span>
                                        </Link>
                                    </li>
                                </template>
                            </ul>
                        </div>

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

                    <!-- Language Switcher (Mobile) -->
                    <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                        <LanguageSwitcher direction="down" />
                    </div>
                </nav>
            </aside>
        </div>

        <!-- Main Content -->
        <main 
            :class="['transition-all duration-300', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-sidebar']"
            :style="{ paddingTop: headerMenuItems.length ? '96px' : '56px' }"
        >
            <div class="px-3 py-4 sm:p-4 lg:p-6">
                <slot />
            </div>
        </main>

        <!-- Mobile Search Overlay -->
        <div v-if="showMobileSearch" class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-4" style="background-color: rgba(0,0,0,0.6);" @click.self="showMobileSearch = false">
            <div class="w-full max-w-lg card p-4 shadow-xl" style="background-color: var(--color-bg-card);">
                <form @submit.prevent="handleMobileSearch" class="flex items-center gap-2">
                    <input
                        v-model="mobileSearchQuery"
                        type="text"
                        placeholder="Search videos..."
                        class="input flex-1"
                        autofocus
                    />
                    <button type="submit" class="btn btn-primary p-2">
                        <Search class="w-5 h-5" />
                    </button>
                    <button type="button" @click="showMobileSearch = false" class="p-2 rounded-full" style="color: var(--color-text-secondary);">
                        <X class="w-5 h-5" />
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <footer
            :class="['transition-all duration-300 py-6 px-4 mt-8', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-sidebar']"
            style="border-top: 1px solid var(--color-border);"
        >
            <div class="max-w-6xl mx-auto">
                <!-- Footer Ad Banner -->
                <div v-if="themeSettings.footer_ad_enabled && themeSettings.footer_ad_code" class="flex justify-center mb-6">
                    <div v-html="themeSettings.footer_ad_code"></div>
                </div>

                <!-- Site Logo / Title -->
                <div class="flex justify-center mb-4">
                    <a href="/" class="inline-flex items-center gap-2 hover:opacity-80 transition-opacity">
                        <img
                            v-if="themeSettings.footer_logo_url"
                            :src="themeSettings.footer_logo_url"
                            alt="Site Logo"
                            class="h-8 object-contain"
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
                    <a href="/pages/terms-of-service" class="hover:opacity-80" style="color: var(--color-text-muted);">Terms of Service</a>
                    <a href="/pages/privacy-policy" class="hover:opacity-80" style="color: var(--color-text-muted);">Privacy Policy</a>
                    <a href="/pages/dmca" class="hover:opacity-80" style="color: var(--color-text-muted);">DMCA</a>
                    <a href="/pages/community-guidelines" class="hover:opacity-80" style="color: var(--color-text-muted);">Community Guidelines</a>
                    <a href="/pages/cookie-policy" class="hover:opacity-80" style="color: var(--color-text-muted);">Cookie Policy</a>
                    <a href="/contact" class="hover:opacity-80" style="color: var(--color-text-muted);">Contact</a>
                </div>
            </div>
        </footer>

        <!-- Toast Notifications -->
        <ToastContainer />
        
        <!-- Age Verification Modal -->
        <AgeVerificationModal />
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
</style>
