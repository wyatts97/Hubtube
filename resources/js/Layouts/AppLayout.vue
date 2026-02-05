<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, 
    Video, Radio, Home, TrendingUp, Zap, ListVideo, History, 
    ChevronLeft, ChevronRight, Shield, Sun, Moon, Monitor
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
                    <button class="p-2 rounded-full md:hidden" style="color: var(--color-text-secondary);">
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

                        <button class="p-2 rounded-full relative" style="color: var(--color-text-secondary);">
                            <Bell class="w-5 h-5" />
                            <span class="absolute top-1 right-1 w-2 h-2 rounded-full" style="background-color: var(--color-accent);"></span>
                        </button>

                        <div class="relative">
                            <button @click="showUserMenu = !showUserMenu" class="flex items-center gap-2">
                                <div class="w-8 h-8 avatar">
                                    <img v-if="user.avatar" :src="user.avatar" :alt="user.username" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white font-medium">
                                        {{ user.username?.charAt(0)?.toUpperCase() }}
                                    </div>
                                </div>
                            </button>

                            <div v-if="showUserMenu" class="absolute right-0 mt-2 w-56 card p-2 shadow-xl" style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);">
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
                                    <Link :href="`/channel/${user.username}`" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-primary);">
                                        <User class="w-4 h-4" />
                                        <span>Your Channel</span>
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
