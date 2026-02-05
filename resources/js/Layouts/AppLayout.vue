<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, 
    Video, Radio, Home, TrendingUp, Zap, ListVideo, History, 
    ChevronLeft, ChevronRight, Shield, Sun, Moon, Monitor,
    X, Check, CheckCheck, Rss, LayoutDashboard
} from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';
import { useToast } from '@/Composables/useToast';
import ToastContainer from '@/Components/ToastContainer.vue';
import AgeVerificationModal from '@/Components/AgeVerificationModal.vue';

const toast = useToast();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const themeSettings = computed(() => page.props.theme || {});
const iconSettings = computed(() => themeSettings.value?.icons || {});
const showUserMenu = ref(false);
const showMobileMenu = ref(false);
const sidebarCollapsed = ref(false);
const searchQuery = ref('');
const showMobileSearch = ref(false);
const mobileSearchQuery = ref('');

// Notification state
const showNotifications = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const notificationsLoaded = ref(false);

const fetchNotifications = async () => {
    if (!user.value) return;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch('/notifications', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            notifications.value = data.notifications || [];
            unreadCount.value = data.unreadCount || 0;
            notificationsLoaded.value = true;
        }
    } catch (e) { /* silent */ }
};

const toggleNotifications = () => {
    showNotifications.value = !showNotifications.value;
    showUserMenu.value = false;
    if (showNotifications.value && !notificationsLoaded.value) {
        fetchNotifications();
    }
};

const markAllRead = async () => {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        await fetch('/notifications/read-all', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken || '', 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        });
        notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }));
        unreadCount.value = 0;
    } catch (e) { /* silent */ }
};

const handleMobileSearch = () => {
    if (mobileSearchQuery.value.trim()) {
        window.location.href = `/search?q=${encodeURIComponent(mobileSearchQuery.value)}`;
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

const navigation = [
    { name: 'Home', href: '/', icon: Home, key: 'home' },
    { name: 'Trending', href: '/trending', icon: TrendingUp, key: 'trending' },
    { name: 'Shorts', href: '/shorts', icon: Zap, key: 'shorts' },
    { name: 'Live', href: '/live', icon: Radio, key: 'live' },
];

const libraryNav = [
    { name: 'Playlists', href: '/playlists', icon: ListVideo, key: 'playlists' },
    { name: 'History', href: '/history', icon: History, key: 'history' },
];

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        window.location.href = `/search?q=${encodeURIComponent(searchQuery.value)}`;
    }
};

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};
</script>

<template>
    <div class="min-h-screen" style="background-color: var(--color-bg-primary);">
        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50" style="background-color: var(--color-bg-secondary); border-bottom: 1px solid var(--color-border);">
            <div class="flex items-center justify-between h-14 px-4">
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
                            class="hidden sm:block font-bold"
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
                        <Link href="/upload" class="btn btn-secondary gap-2 hidden sm:flex">
                            <Upload class="w-4 h-4" />
                            <span>Upload</span>
                        </Link>

                        <Link href="/go-live" class="btn btn-primary gap-2 hidden sm:flex">
                            <Radio class="w-4 h-4" />
                            <span>Go Live</span>
                        </Link>

                        <div class="relative">
                            <button @click="toggleNotifications" class="notification-trigger p-2 rounded-full relative" style="color: var(--color-text-secondary);">
                                <Bell class="w-5 h-5" />
                                <span v-if="unreadCount > 0" class="absolute top-1 right-1 w-2 h-2 rounded-full" style="background-color: var(--color-accent);"></span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div v-if="showNotifications" class="notification-dropdown absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto card shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
                                <div class="flex items-center justify-between p-3 border-b" style="border-color: var(--color-border);">
                                    <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">Notifications</h3>
                                    <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs hover:opacity-80" style="color: var(--color-accent);">
                                        Mark all read
                                    </button>
                                </div>
                                <div v-if="notifications.length">
                                    <div
                                        v-for="notif in notifications"
                                        :key="notif.id"
                                        class="flex items-start gap-3 p-3 border-b last:border-b-0 transition-colors"
                                        :style="{
                                            borderColor: 'var(--color-border)',
                                            backgroundColor: !notif.read_at ? 'rgba(var(--color-accent-rgb, 220, 38, 38), 0.03)' : 'transparent',
                                        }"
                                    >
                                        <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center" style="background-color: var(--color-bg-secondary);">
                                            <Bell class="w-4 h-4" style="color: var(--color-text-muted);" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium" style="color: var(--color-text-primary);">{{ notif.title }}</p>
                                            <p class="text-xs mt-0.5 line-clamp-2" style="color: var(--color-text-muted);">{{ notif.message }}</p>
                                        </div>
                                        <div v-if="!notif.read_at" class="w-2 h-2 rounded-full flex-shrink-0 mt-2" style="background-color: var(--color-accent);"></div>
                                    </div>
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
                                            :style="currentTheme !== 'light' ? { color: 'var(--color-text-secondary)' } : {}"
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
        </header>

        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed left-0 top-14 bottom-0 overflow-y-auto hidden lg:block transition-all duration-300',
                sidebarCollapsed ? 'w-16' : 'sidebar-expanded'
            ]"
            style="background-color: var(--color-bg-secondary); border-right: 1px solid var(--color-border);"
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
                                class="w-5 h-5 flex-shrink-0" 
                                :style="{ color: getIconColor(item.key) }"
                            />
                            <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>

                <template v-if="user && !sidebarCollapsed">
                    <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                        <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-muted);">Library</h3>
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
            </nav>
        </aside>

        <!-- Mobile Sidebar -->
        <div 
            v-if="showMobileMenu" 
            class="fixed inset-0 z-40 lg:hidden"
            @click="showMobileMenu = false"
        >
            <div class="absolute inset-0 bg-black/50"></div>
            <aside class="absolute left-0 top-0 bottom-0 w-64 pt-14 overflow-y-auto" style="background-color: var(--color-bg-secondary);">
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
                        <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                            <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-muted);">Library</h3>
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
                </nav>
            </aside>
        </div>

        <!-- Main Content -->
        <main :class="['pt-14 transition-all duration-300', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-sidebar']">
            <div class="p-4 lg:p-6">
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

        <!-- Toast Notifications -->
        <ToastContainer />
        
        <!-- Age Verification Modal -->
        <AgeVerificationModal />
    </div>
</template>

<style scoped>
.sidebar-expanded {
    width: fit-content;
    min-width: 120px;
    max-width: 180px;
    padding-right: 0.75rem;
}

.lg\:pl-sidebar {
    padding-left: 140px;
}

@media (min-width: 1024px) {
    .lg\:pl-sidebar {
        padding-left: 140px;
    }
}
</style>
