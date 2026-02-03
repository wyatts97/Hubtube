<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, Video, Radio } from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);
const showUserMenu = ref(false);
const showMobileMenu = ref(false);
const searchQuery = ref('');

const navigation = [
    { name: 'Home', href: '/', icon: Video },
    { name: 'Trending', href: '/trending', icon: Video },
    { name: 'Shorts', href: '/shorts', icon: Video },
    { name: 'Live', href: '/live', icon: Radio },
];

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        window.location.href = `/search?q=${encodeURIComponent(searchQuery.value)}`;
    }
};
</script>

<template>
    <div class="min-h-screen bg-dark-950">
        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50 bg-dark-900 border-b border-dark-800">
            <div class="flex items-center justify-between h-14 px-4">
                <!-- Left: Logo & Menu -->
                <div class="flex items-center gap-4">
                    <button @click="showMobileMenu = !showMobileMenu" class="p-2 hover:bg-dark-800 rounded-full lg:hidden">
                        <Menu class="w-5 h-5" />
                    </button>
                    <Link href="/" class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                            <Video class="w-5 h-5 text-white" />
                        </div>
                        <span class="text-xl font-bold text-white hidden sm:block">HubTube</span>
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
                                        {{ user.username.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                            </button>

                            <div v-if="showUserMenu" class="absolute right-0 mt-2 w-56 card p-2 shadow-xl">
                                <div class="px-3 py-2 border-b border-dark-800">
                                    <p class="font-medium">{{ user.username }}</p>
                                    <p class="text-sm text-dark-400">{{ user.email }}</p>
                                </div>
                                <div class="py-2">
                                    <Link :href="`/channel/${user.username}`" class="flex items-center gap-3 px-3 py-2 hover:bg-dark-800 rounded-lg">
                                        <User class="w-4 h-4" />
                                        <span>Your Channel</span>
                                    </Link>
                                    <Link href="/wallet" class="flex items-center gap-3 px-3 py-2 hover:bg-dark-800 rounded-lg">
                                        <Wallet class="w-4 h-4" />
                                        <span>Wallet: ${{ user.wallet_balance }}</span>
                                    </Link>
                                    <Link href="/settings" class="flex items-center gap-3 px-3 py-2 hover:bg-dark-800 rounded-lg">
                                        <Settings class="w-4 h-4" />
                                        <span>Settings</span>
                                    </Link>
                                </div>
                                <div class="pt-2 border-t border-dark-800">
                                    <Link href="/logout" method="post" as="button" class="flex items-center gap-3 px-3 py-2 hover:bg-dark-800 rounded-lg w-full text-left text-red-400">
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
        <aside class="fixed left-0 top-14 bottom-0 w-64 bg-dark-900 border-r border-dark-800 overflow-y-auto hidden lg:block">
            <nav class="p-4">
                <ul class="space-y-1">
                    <li v-for="item in navigation" :key="item.name">
                        <Link :href="item.href" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-dark-800 text-dark-300 hover:text-white transition-colors">
                            <component :is="item.icon" class="w-5 h-5" />
                            <span>{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>

                <template v-if="user">
                    <div class="mt-6 pt-6 border-t border-dark-800">
                        <h3 class="px-3 text-xs font-semibold text-dark-500 uppercase tracking-wider mb-2">Library</h3>
                        <ul class="space-y-1">
                            <li>
                                <Link href="/playlists" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-dark-800 text-dark-300 hover:text-white">
                                    <Video class="w-5 h-5" />
                                    <span>Playlists</span>
                                </Link>
                            </li>
                            <li>
                                <Link href="/history" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-dark-800 text-dark-300 hover:text-white">
                                    <Video class="w-5 h-5" />
                                    <span>History</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </template>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="pt-14 lg:pl-64">
            <div class="p-4 lg:p-6">
                <slot />
            </div>
        </main>
    </div>
</template>
