<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, 
    Video, Radio, Home, TrendingUp, Zap, ListVideo, History, 
    ChevronLeft, ChevronRight, Shield, Sun, Moon, Monitor
} from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const themeSettings = computed(() => page.props.theme || {});
const showUserMenu = ref(false);
const showMobileMenu = ref(false);
const sidebarCollapsed = ref(false);
const searchQuery = ref('');

const { currentTheme, isDark, setTheme, toggleTheme } = useTheme();

const navigation = [
    { name: 'Home', href: '/', icon: Home },
    { name: 'Trending', href: '/trending', icon: TrendingUp },
    { name: 'Shorts', href: '/shorts', icon: Zap },
    { name: 'Live', href: '/live', icon: Radio },
];

const libraryNav = [
    { name: 'Playlists', href: '/playlists', icon: ListVideo },
    { name: 'History', href: '/history', icon: History },
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
                    <Link href="/" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background-color: var(--color-accent);">
                            <Video class="w-5 h-5 text-white" />
                        </div>
                        <span class="text-xl font-bold hidden sm:block" style="color: var(--color-text-primary);">HubTube</span>
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
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 p-2 hover:bg-dark-700 rounded-full">
                            <Search class="w-5 h-5 text-dark-400" />
                        </button>
                    </form>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2">
                    <button class="p-2 hover:bg-dark-800 rounded-full md:hidden">
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

                        <button class="p-2 hover:bg-dark-800 rounded-full relative">
                            <Bell class="w-5 h-5" />
                            <span class="absolute top-1 right-1 w-2 h-2 bg-primary-600 rounded-full"></span>
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
                sidebarCollapsed ? 'w-16' : 'w-64'
            ]"
            style="background-color: var(--color-bg-secondary); border-right: 1px solid var(--color-border);"
        >
            <nav class="p-2">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.name">
                        <Link 
                            :href="item.href" 
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-lg transition-colors',
                                sidebarCollapsed ? 'justify-center' : ''
                            ]"
                            :title="sidebarCollapsed ? item.name : ''"
                            style="color: var(--color-text-secondary);"
                        >
                            <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                            <span v-if="!sidebarCollapsed">{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>

                <template v-if="user && !sidebarCollapsed">
                    <div class="mt-6 pt-6" style="border-top: 1px solid var(--color-border);">
                        <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-secondary);">Library</h3>
                        <ul class="space-y-1">
                            <li v-for="item in libraryNav" :key="item.name">
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2 rounded-lg" style="color: var(--color-text-secondary);">
                                    <component :is="item.icon" class="w-5 h-5" />
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
                                    class="flex items-center justify-center px-3 py-2 rounded-lg"
                                    :title="item.name"
                                    style="color: var(--color-text-secondary);"
                                >
                                    <component :is="item.icon" class="w-5 h-5" />
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
            <aside class="absolute left-0 top-0 bottom-0 w-64 bg-dark-900 pt-14 overflow-y-auto">
                <nav class="p-4">
                    <ul class="space-y-1">
                        <li v-for="item in navigation" :key="item.name">
                            <Link :href="item.href" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-dark-800 text-dark-300 hover:text-white">
                                <component :is="item.icon" class="w-5 h-5" />
                                <span>{{ item.name }}</span>
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>
        </div>

        <!-- Main Content -->
        <main :class="['pt-14 transition-all duration-300', sidebarCollapsed ? 'lg:pl-16' : 'lg:pl-64']">
            <div class="p-4 lg:p-6">
                <slot />
            </div>
        </main>
    </div>
</template>
