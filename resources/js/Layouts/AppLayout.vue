<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue';
import { useWindowScroll } from '@vueuse/core';
import {
    ComboboxAnchor, ComboboxContent, ComboboxInput, ComboboxRoot,
    DropdownMenuItem, DropdownMenuLabel,
} from 'reka-ui';
import { Link, usePage, useForm, router } from '@inertiajs/vue3';
import { 
    Menu, Search, Upload, Bell, User, LogOut, Settings, Wallet, 
    Video, Home, TrendingUp, Zap, ListVideo, History, 
    ChevronLeft, ChevronRight, Shield,
    X, Check, CheckCheck, Rss, LayoutDashboard, ChevronDown, ChevronUp, Film,
    Tag, Folder, Star, Eye, EyeOff, LayoutGrid, Plus,
    ImageIcon, MoreHorizontal, Loader2, Smartphone, Award, LogIn
} from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';
import { useToast } from '@/Composables/useToast';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { useGlobalAutoTranslate } from '@/Composables/useGlobalAutoTranslate';
import { useSearchSuggestions } from '@/Composables/useSearchSuggestions';
import ToastContainer from '@/Components/ToastContainer.vue';
import ImpersonationBar from '@/Components/ImpersonationBar.vue';
import AgeVerificationModal from '@/Components/AgeVerificationModal.vue';
import AdInterstitial from '@/Components/AdInterstitial.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import AdSlot from '@/Components/AdSlot.vue';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import SearchSuggestionList from '@/Components/SearchSuggestionList.vue';
import GenderMaleIcon from '@/Components/Icons/GenderMaleIcon.vue';
import GenderFemaleIcon from '@/Components/Icons/GenderFemaleIcon.vue';
import GaySymbolIcon from '@/Components/Icons/GaySymbolIcon.vue';
import LesbianSymbolIcon from '@/Components/Icons/LesbianSymbolIcon.vue';
import TransgenderSymbolIcon from '@/Components/Icons/TransgenderSymbolIcon.vue';
import StraightSymbolIcon from '@/Components/Icons/StraightSymbolIcon.vue';

const toast = useToast();
const { get, post } = useFetch();
const { localizedUrl, t, isTranslated } = useI18n();
useGlobalAutoTranslate();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const themeSettings = computed(() => page.props.theme || {});
const iconSettings = computed(() => themeSettings.value?.icons || {});
const sidebarCollapsed = ref(false);
const searchQuery = ref('');
const showMobileSearch = ref(false);
const showLoginModal = ref(false);
const showLoginPassword = ref(false);

// The old markup gated the modal on `showLoginModal && !user`; keep that guard
// so a session that becomes authenticated can never leave the dialog mounted.
const loginModalOpen = computed({
    get: () => showLoginModal.value && !user.value,
    set: (value) => { showLoginModal.value = value; },
});

const loginFieldRef = ref(null);

// Reka focuses the first tabbable element (the close button); focus the
// login field instead, matching the `autofocus` the old markup carried.
const focusLoginField = (event) => {
    event.preventDefault();
    nextTick(() => loginFieldRef.value?.focus());
};

const loginForm = useForm({
    login: '',
    password: '',
    remember: false,
});

const submitLogin = () => {
    loginForm.post('/login', {
        onSuccess: () => {
            showLoginModal.value = false;
            showLoginPassword.value = false;
            loginForm.reset();
        },
        onFinish: () => {
            loginForm.reset('password');
        },
    });
};
const mobileSearchQuery = ref('');

// Live search autocomplete. The fetch/debounce logic lives in the composable;
// the arrow/Enter/Escape handling that used to sit here is Reka Combobox's job
// now, so `activeSuggestionIndex` and its modular arithmetic are gone.
const {
    suggestions: searchSuggestions,
    hasResults: hasSuggestions,
    isLoading: suggestLoading,
    isOpen: showSuggestions,
    search: searchSuggest,
    clear: clearSuggestions,
    urlFor: suggestionUrl,
} = useSearchSuggestions();

// Menu items from admin panel
const menuItems = computed(() => page.props.menuItems || { header: [], mobile: [] });
const headerMenuItems = computed(() => menuItems.value.header || []);
const mobileMenuItems = computed(() => menuItems.value.mobile || []);

const lucideIconMap = {
    tag: Tag, folder: Folder, star: Star, home: Home, zap: Zap,
    'trending-up': TrendingUp, video: Video, film: Film,
    'list-video': ListVideo, history: History, search: Search,
};

// Gender/orientation category symbols (custom SVGs, not part of lucide-vue-next)
const genderIconMap = {
    'gender-male': GenderMaleIcon,
    'gender-female': GenderFemaleIcon,
    'gender-gay': GaySymbolIcon,
    'gender-lesbian': LesbianSymbolIcon,
    'gender-transgender': TransgenderSymbolIcon,
    'gender-straight': StraightSymbolIcon,
};

const menuIconMap = { ...lucideIconMap, ...genderIconMap };

const getMenuIcon = (iconName) => {
    if (!iconName) return null;
    return menuIconMap[iconName] || Tag;
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

const onNotificationsOpenChange = async (open) => {
    showNotifications.value = open;
    if (open) {
        // Always refresh notifications when opening the dropdown
        await fetchNotifications();
        // Auto-mark all as read when opening the dropdown
        if (unreadCount.value > 0) {
            markAllRead();
        }
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
        clearSuggestions();
    }
};

const handleSearch = () => {
    if (searchQuery.value.trim()) {
        window.location.href = `${localizedUrl('/search')}?q=${encodeURIComponent(searchQuery.value)}`;
        clearSuggestions();
    }
};

// Combobox emits the whole suggestion object as its model value on select.
const onSuggestionSelect = (suggestion) => {
    if (!suggestion) return;
    clearSuggestions();
    showMobileSearch.value = false;
    router.visit(suggestionUrl(suggestion));
};

onMounted(() => {
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

// Site is dark-only. useTheme() is still called to apply CSS variables on mount.
useTheme();

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

// Hide search suggestions on page navigation
watch(() => page.url, () => {
    clearSuggestions();
});

const getIconColor = (navKey) => {
    const icons = iconSettings.value;
    if (!icons) return 'var(--color-text-secondary)';
    
    // Check if there's a specific color for this nav item
    const navItem = icons[navKey];
    if (navItem?.color) return navItem.color;
    
    // Global icon color (dark mode only)
    if (icons.colorMode === 'global' || icons.colorMode === 'custom') {
        const color = icons.globalColorDark || icons.globalColor;
        if (color) return color;
    }
    
    // Default to text secondary
    return 'var(--color-text-secondary)';
};

const monetizationEnabled = computed(() => page.props.app?.monetization_enabled !== false);
const pointsEnabled = computed(() => page.props.app?.points_enabled !== false);

const navigation = computed(() => [
    { name: t('nav.home'), href: localizedUrl('/'), icon: Home, key: 'home' },
    { name: t('nav.shorts'), href: localizedUrl('/shorts'), icon: Smartphone, key: 'shorts' },
    { name: t('nav.trending'), href: localizedUrl('/trending'), icon: TrendingUp, key: 'trending' },
    { name: t('nav.categories'), href: localizedUrl('/categories'), icon: LayoutGrid, key: 'categories' },
    { name: t('nav.images'), href: localizedUrl('/images'), icon: ImageIcon, key: 'images' },
    { name: t('nav.tags'), href: localizedUrl('/tags'), icon: Tag, key: 'tags' },
]);

const libraryNav = computed(() => [
    { name: t('nav.playlists'), href: '/playlists', icon: ListVideo, key: 'playlists' },
    { name: t('nav.history'), href: '/history', icon: History, key: 'history' },
]);

// handleSearch moved above with suggestion logic

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

// Mobile bottom navbar — scroll-aware show/hide
const showMobileNav = ref(true);
const footerRef = ref(null);
const { y: windowScrollY } = useWindowScroll();

watch(windowScrollY, (currentY, previousY) => {
    const scrollingDown = currentY > previousY;

    // Hide when near footer
    if (footerRef.value) {
        const footerRect = footerRef.value.getBoundingClientRect();
        if (footerRect.top < window.innerHeight + 20) {
            showMobileNav.value = false;
            return;
        }
    }

    // Hide on scroll down, show on scroll up
    if (scrollingDown && currentY > 80) {
        showMobileNav.value = false;
    } else if (!scrollingDown) {
        showMobileNav.value = true;
    }
});


const mobileNavItems = computed(() => [
    { name: t('nav.home'), href: localizedUrl('/'), icon: Home },
    { name: t('common.search'), href: null, action: 'search', icon: Search },
    { name: '+', href: null, action: 'upload', icon: Plus, isCenter: true },
    { name: t('nav.categories'), href: localizedUrl('/categories'), icon: LayoutGrid },
    { name: t('nav.more'), href: null, action: 'more', icon: MoreHorizontal },
]);

const mobileMoreItems = computed(() => [
    { name: t('nav.shorts'), href: localizedUrl('/shorts'), icon: Smartphone },
    { name: t('nav.trending'), href: localizedUrl('/trending'), icon: TrendingUp },
    { name: t('nav.images'), href: localizedUrl('/images'), icon: ImageIcon },
    { name: t('nav.tags'), href: localizedUrl('/tags'), icon: Tag },
    { name: t('nav.playlists'), href: localizedUrl('/public-playlists'), icon: ListVideo },
]);

// The 'upload' and 'more' items are now BaseDropdown triggers that manage
// their own open state, so only 'search' is left to handle here.
const handleMobileNavClick = (item) => {
    if (item.action === 'search') {
        showMobileSearch.value = true;
    }
};
</script>

<template>
    <div class="min-h-screen bg-bg-primary">
        <!-- Header -->
        <header class="fixed top-0 start-0 end-0 z-50 w-full bg-bg-secondary border-b border-border">
            <div class="flex items-center justify-between h-14 px-4 w-full">
                <!-- Left: Logo & Menu -->
                <div class="flex items-center gap-4">
                    <button @click="toggleSidebar" class="p-2 rounded-full hidden lg:flex text-text-primary" :style="{ ':hover': { backgroundColor: 'var(--color-bg-card)' } }" aria-label="Toggle sidebar">
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
                <ComboboxRoot
                    v-model:open="showSuggestions"
                    ignore-filter
                    :reset-search-term-on-blur="false"
                    class="flex-1 max-w-2xl mx-4 hidden md:block relative"
                    @update:model-value="onSuggestionSelect"
                >
                    <ComboboxAnchor as-child>
                        <form @submit.prevent="handleSearch" class="relative" role="search">
                            <ComboboxInput
                                v-model="searchQuery"
                                :placeholder="t('common.search_placeholder')"
                                class="input pe-12 w-full"
                                aria-label="Search videos"
                                autocomplete="off"
                                autocapitalize="off"
                                @input="searchSuggest($event.target.value)"
                            />
                            <button type="submit" class="absolute end-2 top-1/2 -translate-y-1/2 p-2 rounded-full hover:opacity-80 text-text-muted" aria-label="Search">
                                <Search class="w-5 h-5" />
                            </button>
                        </form>
                    </ComboboxAnchor>

                    <!-- Autocomplete Dropdown -->
                    <ComboboxContent
                        v-if="hasSuggestions || suggestLoading"
                        position="popper"
                        :side-offset="4"
                        class="card shadow-xl bg-bg-card border border-border z-[9999] max-h-80 overflow-y-auto scrollbar-hide w-[var(--reka-combobox-trigger-width)]"
                    >
                        <SearchSuggestionList :suggestions="searchSuggestions" :loading="suggestLoading" />
                    </ComboboxContent>
                </ComboboxRoot>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2">
                    <LanguageSwitcher mobile align="right" class="md:hidden" />

                    <template v-if="user">
                        <!-- Upload Dropdown -->
                        <BaseDropdown content-class="min-w-34 p-1">
                            <template #trigger>
                                <button
                                    class="hidden sm:block p-2 rounded-full hover:opacity-80 transition-opacity text-text-secondary"
                                    title="Upload"
                                    aria-label="Upload"
                                >
                                    <Upload class="w-5 h-5" />
                                </button>
                            </template>

                            <DropdownMenuItem as-child>
                                <Link href="/upload" class="flex items-center justify-center gap-2 px-2.5 py-2 rounded-lg hover:opacity-80 transition-opacity text-text-primary cursor-pointer">
                                    <Film class="w-4 h-4 text-text-secondary" />
                                    <span class="text-sm">{{ t('nav.upload_video') }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link href="/image-upload" class="flex items-center justify-center gap-2 px-2.5 py-2 rounded-lg hover:opacity-80 transition-opacity text-text-primary cursor-pointer">
                                    <ImageIcon class="w-4 h-4 text-text-secondary" />
                                    <span class="text-sm">{{ t('nav.upload_image') }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </BaseDropdown>

                        <!-- Notification Dropdown -->
                        <BaseDropdown
                            :model-value="showNotifications"
                            content-class="w-[min(20rem,calc(100vw-1rem))] max-h-96 overflow-y-auto scrollbar-hide"
                            @update:model-value="onNotificationsOpenChange"
                        >
                            <template #trigger>
                                <button class="p-2 rounded-full relative text-text-secondary" aria-label="Notifications">
                                    <Bell class="w-5 h-5" />
                                    <span v-if="unreadCount > 0" class="absolute top-1 end-1 w-2 h-2 rounded-full bg-accent"></span>
                                </button>
                            </template>

                            <div class="flex items-center justify-between p-3 border-b border-border">
                                <h3 class="font-semibold text-sm text-text-primary">{{ t('nav.notifications') }}</h3>
                                <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs hover:opacity-80 text-accent">
                                    {{ t('nav.mark_all_read') }}
                                </button>
                            </div>
                            <div v-if="notifications.length">
                                <DropdownMenuItem
                                    v-for="notif in notifications"
                                    :key="notif.id"
                                    as-child
                                    @select="notif.data?.url ? null : $event.preventDefault()"
                                >
                                    <component
                                        :is="notif.data?.url ? 'a' : 'div'"
                                        :href="notif.data?.url || undefined"
                                        class="flex items-start gap-3 p-3 border-b last:border-b-0 transition-colors hover:opacity-80"
                                        :class="{ 'cursor-pointer': notif.data?.url }"
                                        :style="{
                                            borderColor: 'var(--color-border)',
                                            backgroundColor: !notif.read_at ? 'rgba(var(--color-accent-rgb, 220, 38, 38), 0.03)' : 'transparent',
                                            display: 'flex',
                                            textDecoration: 'none',
                                        }"
                                    >
                                        <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center bg-bg-secondary">
                                            <Bell class="w-4 h-4 text-text-muted" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-text-primary">{{ notif.title }}</p>
                                            <p class="text-xs mt-0.5 line-clamp-2 text-text-muted">{{ notif.message }}</p>
                                        </div>
                                        <div v-if="!notif.read_at" class="w-2 h-2 rounded-full shrink-0 mt-2 bg-accent"></div>
                                    </component>
                                </DropdownMenuItem>
                            </div>
                            <div v-else class="p-6 text-center">
                                <Bell class="w-8 h-8 mx-auto mb-2 text-text-muted" />
                                <p class="text-sm text-text-secondary">{{ t('nav.no_notifications') }}</p>
                            </div>
                        </BaseDropdown>

                        <!-- User Account Menu -->
                        <BaseDropdown content-class="w-56 p-2">
                            <template #trigger>
                                <button class="flex items-center gap-2" aria-label="User menu">
                                    <div class="w-8 h-8 avatar">
                                        <img :src="user.avatar || '/images/default_avatar.webp'" :alt="user.username" class="w-full h-full object-cover" />
                                    </div>
                                </button>
                            </template>

                            <DropdownMenuLabel class="px-3 py-2 border-b border-border">
                                <p class="font-medium text-text-primary">{{ user.username }}</p>
                                <p class="text-sm text-text-secondary">{{ user.email }}</p>
                            </DropdownMenuLabel>
                            <div class="py-2">
                                <!-- Admin Panel Link - Only for admins -->
                                <DropdownMenuItem v-if="user.is_admin" as-child>
                                    <a
                                        href="/admin"
                                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-accent cursor-pointer"
                                    >
                                        <Shield class="w-4 h-4" />
                                        <span>{{ t('nav.admin_panel') }}</span>
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link href="/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg text-text-primary cursor-pointer">
                                        <LayoutDashboard class="w-4 h-4" />
                                        <span>{{ t('nav.dashboard') }}</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link :href="`/channel/${user.username}`" class="flex items-center gap-3 px-3 py-2 rounded-lg text-text-primary cursor-pointer">
                                        <User class="w-4 h-4" />
                                        <span>{{ t('nav.your_channel') }}</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link href="/feed" class="flex items-center gap-3 px-3 py-2 rounded-lg text-text-primary cursor-pointer">
                                        <Rss class="w-4 h-4" />
                                        <span>{{ t('nav.subscriptions') }}</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="monetizationEnabled" as-child>
                                    <Link href="/wallet" class="flex items-center gap-3 px-3 py-2 rounded-lg text-text-primary cursor-pointer">
                                        <Wallet class="w-4 h-4" />
                                        <span>{{ t('nav.wallet') }}: ${{ user.wallet_balance }}</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem v-if="pointsEnabled" as-child>
                                    <Link href="/rewards" class="flex items-center gap-3 px-3 py-2 rounded-lg text-text-primary cursor-pointer">
                                        <Award class="w-4 h-4" />
                                        <span>{{ t('nav.rewards') }}: {{ user.points_balance?.toLocaleString() || 0 }} pts</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link href="/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-text-primary cursor-pointer">
                                        <Settings class="w-4 h-4" />
                                        <span>{{ t('nav.settings') }}</span>
                                    </Link>
                                </DropdownMenuItem>
                            </div>
                            <div class="pt-2 border-t border-border">
                                <DropdownMenuItem as-child>
                                    <Link href="/logout" method="post" as="button" class="flex items-center gap-3 px-3 py-2 rounded-lg w-full text-start text-red-400 cursor-pointer">
                                        <LogOut class="w-4 h-4" />
                                        <span>{{ t('nav.sign_out') }}</span>
                                    </Link>
                                </DropdownMenuItem>
                            </div>
                        </BaseDropdown>
                    </template>

                    <template v-else>
                        <!-- Icon-only at all screen sizes, matching the language switcher's understated style -->
                        <button
                            @click="showLoginModal = true"
                            class="p-2 rounded-full transition-all text-text-secondary opacity-70 hover:opacity-100"
                            title="Login / Register"
                            aria-label="Login / Register"
                        >
                            <LogIn class="w-5 h-5" />
                        </button>
                    </template>
                </div>
            </div>

            <!-- Menu Bar (tablet & desktop): site menu items on the left, language switcher on the far right -->
            <div class="hidden md:flex items-center justify-between border-t border-border h-10 px-4">
                <div class="flex items-center gap-1 min-w-0">
                    <template v-for="item in headerMenuItems" :key="item.id">
                        <!-- Divider -->
                        <div v-if="item.type === 'divider'" class="w-px h-5 mx-1" style="background-color: var(--color-border);"></div>

                        <!-- Dropdown / Mega menu parent -->
                        <BaseDropdown
                            v-else-if="(item.type === 'dropdown' || item.is_mega) && item.children?.length"
                            align="start"
                            :side-offset="4"
                            content-class="p-4"
                            :content-style="{ minWidth: item.is_mega ? (item.mega_columns * 160 + 'px') : '200px' }"
                        >
                            <template #trigger="{ open }">
                                <button
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors hover:opacity-80 text-text-secondary"
                                >
                                    <component v-if="item.icon && getMenuIcon(item.icon)" :is="getMenuIcon(item.icon)" class="w-4 h-4" />
                                    <span>{{ item.label }}</span>
                                    <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" />
                                </button>
                            </template>

                            <div
                                :class="item.is_mega ? 'grid gap-3' : 'flex flex-col gap-1'"
                                :style="item.is_mega ? { gridTemplateColumns: `repeat(${item.mega_columns || 4}, minmax(0, 1fr))` } : {}"
                            >
                                <template v-for="child in item.children" :key="child.id">
                                    <DropdownMenuItem v-if="child.type !== 'divider'" as-child>
                                        <Link
                                            :href="child.url || '#'"
                                            :target="child.target || '_self'"
                                            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors hover:opacity-80 text-text-secondary cursor-pointer"
                                        >
                                            <component v-if="child.icon && getMenuIcon(child.icon)" :is="getMenuIcon(child.icon)" class="w-4 h-4 shrink-0" />
                                            <span>{{ child.label }}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                    <div v-else class="border-t my-1 border-border"></div>
                                </template>
                            </div>
                        </BaseDropdown>

                        <!-- Regular link -->
                        <Link
                            v-else
                            :href="item.url || '#'"
                            :target="item.target || '_self'"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm transition-colors hover:opacity-80 text-text-secondary"
                        >
                            <component v-if="item.icon && getMenuIcon(item.icon)" :is="getMenuIcon(item.icon)" class="w-4 h-4" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </template>
                </div>

                <LanguageSwitcher align="right" class="shrink-0 ms-4" />
            </div>
        </header>

        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed start-0 bottom-0 overflow-y-auto scrollbar-hide hidden lg:block transition-all duration-300 z-30',
                sidebarCollapsed ? 'w-16' : 'sidebar-expanded'
            ]"
            :style="{
                top: '96px',
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
                            class="text-text-secondary"
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
                    <div class="mt-6 pt-6 border-t border-border">
                        <h3 class="px-3 text-xs font-semibold uppercase tracking-wider mb-2 text-text-muted">{{ t('nav.library') }}</h3>
                        <ul class="space-y-1">
                            <li v-for="item in libraryNav" :key="item.name">
                                <Link :href="item.href" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:opacity-80 text-text-secondary">
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
                    <div class="mt-6 pt-6 border-t border-border">
                        <ul class="space-y-1">
                            <li v-for="item in libraryNav" :key="item.name">
                                <Link 
                                    :href="item.href" 
                                    class="flex items-center justify-center px-3 py-2 rounded-lg hover:opacity-80 text-text-secondary"
                                    :title="item.name"
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

        <!-- Main Content -->
        <main 
            v-motion
            :initial="{ opacity: 0, y: 8 }"
            :enter="{ opacity: 1, y: 0, transition: { duration: 0.2 } }"
            :leave="{ opacity: 0, y: -8, transition: { duration: 0.15 } }"
            :class="['transition-all duration-300 pt-14 md:pt-24', sidebarCollapsed ? 'lg:ps-16' : 'lg:pl-sidebar']"
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
                class="w-full max-w-lg card p-4 shadow-xl bg-bg-card"
            >
                <ComboboxRoot
                    v-model:open="showSuggestions"
                    ignore-filter
                    :reset-search-term-on-blur="false"
                    @update:model-value="onSuggestionSelect"
                >
                    <ComboboxAnchor as-child>
                        <form @submit.prevent="handleMobileSearch" class="flex items-center gap-2" role="search">
                            <ComboboxInput
                                v-model="mobileSearchQuery"
                                :placeholder="t('common.search_placeholder')"
                                class="input flex-1"
                                aria-label="Search videos"
                                auto-focus
                                autocomplete="off"
                                autocapitalize="off"
                                @input="searchSuggest($event.target.value)"
                            />
                            <button type="submit" class="btn btn-primary p-2" aria-label="Search">
                                <Search class="w-5 h-5" />
                            </button>
                            <button type="button" @click="showMobileSearch = false; clearSuggestions()" class="p-2 rounded-full text-text-secondary" aria-label="Close search">
                                <X class="w-5 h-5" />
                            </button>
                        </form>
                    </ComboboxAnchor>

                    <!-- Mobile Autocomplete Dropdown: rendered inline under the input,
                         not portalled, so it stays inside the overlay card. -->
                    <ComboboxContent
                        v-if="hasSuggestions || suggestLoading"
                        class="mt-2 max-h-72 overflow-y-auto scrollbar-hide"
                    >
                        <SearchSuggestionList :suggestions="searchSuggestions" :loading="suggestLoading" />
                    </ComboboxContent>
                </ComboboxRoot>
            </div>
        </div>

        <!-- Footer -->
        <footer
            ref="footerRef"
            :class="['transition-all duration-300 py-6 px-4 mt-8', sidebarCollapsed ? 'lg:ps-16' : 'lg:pl-sidebar']"
            class="border-t border-border"
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
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-text-muted">
                    <a href="/pages/terms-of-service" class="hover:opacity-80 text-text-muted">{{ t('footer.terms') }}</a>
                    <a href="/pages/privacy-policy" class="hover:opacity-80 text-text-muted">{{ t('footer.privacy') }}</a>
                    <a href="/pages/dmca" class="hover:opacity-80 text-text-muted">{{ t('footer.dmca') }}</a>
                    <a href="/dmca-request" class="hover:opacity-80 text-text-muted">{{ t('footer.dmca_request') }}</a>
                    <a href="/pages/community-guidelines" class="hover:opacity-80 text-text-muted">{{ t('footer.guidelines') }}</a>
                    <a href="/pages/cookie-policy" class="hover:opacity-80 text-text-muted">{{ t('footer.cookies') }}</a>
                    <a href="/contact" class="hover:opacity-80 text-text-muted">{{ t('footer.contact') }}</a>
                </div>
            </div>
        </footer>

        <!-- Mobile Bottom Navbar Dock -->
        <Transition name="mobile-nav">
            <nav
                v-if="showMobileNav"
                class="fixed bottom-4 start-0 end-0 z-40 mx-auto w-[calc(100%-32px)] max-w-lg lg:hidden"
                aria-label="Mobile navigation"
            >
                <div
                    class="flex justify-between items-center px-3 py-2 rounded-2xl shadow-lg bg-bg-secondary border border-border"
                >
                    <template v-for="item in mobileNavItems" :key="item.name">
                        <!-- Center Upload Button -->
                        <div v-if="item.isCenter" class="flex flex-col items-center justify-center" style="flex: 1 1 0%; min-width: 0;">
                            <BaseDropdown side="top" align="center" :side-offset="12" content-class="rounded-xl p-1.5 w-[120px]">
                                <template #trigger>
                                    <button
                                        class="flex items-center justify-center w-11 h-11 rounded-full shadow-lg active:scale-95 transition-all duration-200 mx-auto"
                                        :style="{ backgroundColor: 'var(--color-accent)' }"
                                        :aria-label="item.name"
                                    >
                                        <component :is="item.icon" class="w-6 h-6 text-white" />
                                    </button>
                                </template>

                                <DropdownMenuItem as-child>
                                    <Link href="/upload" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg hover:opacity-80 transition-opacity text-text-primary cursor-pointer">
                                        <Video class="w-4 h-4 shrink-0 text-text-secondary" />
                                        <span class="text-sm">Video</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link href="/image-upload" class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg hover:opacity-80 transition-opacity text-text-primary cursor-pointer">
                                        <ImageIcon class="w-4 h-4 shrink-0 text-text-secondary" />
                                        <span class="text-sm">{{ t('nav.upload_image') }}</span>
                                    </Link>
                                </DropdownMenuItem>
                            </BaseDropdown>
                        </div>
                        <!-- More Button with popup menu -->
                        <div v-else-if="item.action === 'more'" class="flex flex-col items-center justify-center" style="flex: 1 1 0%; min-width: 0;">
                            <BaseDropdown side="top" align="end" :side-offset="12" content-class="rounded-xl p-2 min-w-[160px]">
                                <template #trigger>
                                    <button
                                        class="flex flex-col items-center justify-center p-2 group"
                                        :aria-label="item.name"
                                    >
                                        <component :is="item.icon" class="w-5 h-5 transition-colors" :style="{ color: 'var(--color-text-secondary)' }" />
                                        <span class="text-[10px] mt-0.5 transition-colors text-text-muted">{{ item.name }}</span>
                                    </button>
                                </template>

                                <DropdownMenuItem
                                    v-for="moreItem in mobileMoreItems"
                                    :key="moreItem.name"
                                    as-child
                                >
                                    <Link
                                        :href="moreItem.href"
                                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:opacity-80 transition-opacity text-text-primary cursor-pointer"
                                    >
                                        <component :is="moreItem.icon" class="w-4 h-4 text-text-secondary" />
                                        <span class="text-sm">{{ moreItem.name }}</span>
                                    </Link>
                                </DropdownMenuItem>
                            </BaseDropdown>
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
                                class="text-[10px] mt-0.5 transition-colors text-text-muted"
                            >{{ item.name }}</span>
                        </component>
                    </template>
                </div>
            </nav>
        </Transition>

        <!-- Login Modal -->
        <BaseDialog
            v-model="loginModalOpen"
            aria-label="Sign in"
            transition-name="login-modal"
            @open-auto-focus="focusLoginField"
        >
            <template #default>
                        <!-- Close button -->
                        <button @click="loginModalOpen = false" class="absolute top-3 end-3 p-1 rounded-full hover:opacity-80 text-text-muted" aria-label="Close login">
                            <X class="w-5 h-5" />
                        </button>

                        <div>
                            <div class="text-center mb-6">
                                <Link href="/" class="inline-block">
                                    <img v-if="themeSettings.site_logo" :src="themeSettings.site_logo" alt="Logo" class="h-12 w-auto mx-auto object-contain" />
                                    <div v-else class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto bg-accent">
                                        <span class="text-2xl font-bold text-white">{{ (themeSettings.siteTitle || 'H').charAt(0).toUpperCase() }}</span>
                                    </div>
                                </Link>
                                <h2 class="text-xl font-bold mt-3 text-text-primary">{{ t('auth.welcome_back') }}</h2>
                                <p class="text-sm mt-1 text-text-secondary">{{ t('auth.sign_in_desc') }}</p>
                            </div>

                            <form @submit.prevent="submitLogin" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-text-secondary">
                                        {{ t('auth.email_or_username') }}
                                    </label>
                                    <input
                                        ref="loginFieldRef"
                                        v-model="loginForm.login"
                                        type="text"
                                        class="input"
                                        required
                                    />
                                    <p v-if="loginForm.errors.login" class="text-red-500 text-sm mt-1">{{ loginForm.errors.login }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1 text-text-secondary">
                                        {{ t('auth.password') }}
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model="loginForm.password"
                                            :type="showLoginPassword ? 'text' : 'password'"
                                            class="input pe-10"
                                            required
                                        />
                                        <button
                                            type="button"
                                            @click="showLoginPassword = !showLoginPassword"
                                            class="absolute end-3 top-1/2 -translate-y-1/2 text-text-secondary"
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
                                        <span class="text-sm text-text-secondary">{{ t('auth.remember_me') }}</span>
                                    </label>
                                    <Link href="/forgot-password" class="text-sm text-accent" @click="loginModalOpen = false">
                                        {{ t('auth.forgot_password') }}
                                    </Link>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="loginForm.processing"
                                    class="btn btn-primary w-full"
                                >
                                    <span v-if="loginForm.processing">{{ t('auth.signing_in') }}</span>
                                    <span v-else>{{ t('auth.login') }}</span>
                                </button>
                            </form>

                            <div class="mt-6 text-center">
                                <p class="text-text-secondary">
                                    {{ t('auth.no_account') }}
                                    <Link href="/register" class="font-medium text-accent" @click="loginModalOpen = false">
                                        {{ t('auth.sign_up') }}
                                    </Link>
                                </p>
                            </div>
                        </div>
            </template>
        </BaseDialog>

        <!-- Impersonation Banner (visible while an admin is logged in as this user) -->
        <ImpersonationBar />

        <!-- Toast Notifications -->
        <ToastContainer />
        
        <!-- Age Verification Modal -->
        <AgeVerificationModal />

        <!-- Interstitial Ad -->
        <AdInterstitial />

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
