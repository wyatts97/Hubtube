<script setup>
/**
 * Top bar: wordmark, search, account actions.
 *
 * Layout is deliberately NOT the logo / centred-search / actions arrangement the
 * site used to have — that silhouette reads as YouTube regardless of colour. The
 * search sits immediately beside the wordmark and left-aligned, which is the
 * layout catalogue and directory sites use, and the hamburger is gone: the
 * sidebar collapses from its own edge instead.
 */
import { computed, nextTick, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ComboboxAnchor, ComboboxContent, ComboboxInput, ComboboxRoot, DropdownMenuItem, DropdownMenuLabel } from 'reka-ui';
import {
    Search, Upload, Bell, User, LogOut, Settings, Wallet, Film, Shield,
    Rss, LayoutDashboard, ImageIcon, Award, LogIn, PanelLeft,
} from 'lucide-vue-next';
import { useFetch } from '@/Composables/useFetch';
import { useI18n } from '@/Composables/useI18n';
import { useSearchSuggestions } from '@/Composables/useSearchSuggestions';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import SearchSuggestionList from '@/Components/SearchSuggestionList.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const emit = defineEmits(['open-mobile-search', 'open-login', 'toggle-sidebar']);

const page = usePage();
const { get, post } = useFetch();
const { localizedUrl, t } = useI18n();

const user = computed(() => page.props.auth?.user);
const themeSettings = computed(() => page.props.theme || {});
const monetizationEnabled = computed(() => page.props.app?.monetization_enabled !== false);
const pointsEnabled = computed(() => page.props.app?.points_enabled !== false);

/* ── Search ─────────────────────────────────────────────────────────────── */

const searchQuery = ref('');
const {
    suggestions: searchSuggestions,
    hasResults: hasSuggestions,
    isLoading: suggestLoading,
    isOpen: showSuggestions,
    search: searchSuggest,
    clear: clearSuggestions,
    urlFor: suggestionUrl,
} = useSearchSuggestions();

const handleSearch = () => {
    if (!searchQuery.value.trim()) return;
    clearSuggestions();
    router.visit(`${localizedUrl('/search')}?q=${encodeURIComponent(searchQuery.value)}`);
};

const onSuggestionSelect = (suggestion) => {
    if (!suggestion) return;
    clearSuggestions();
    router.visit(suggestionUrl(suggestion));
};

defineExpose({ clearSuggestions });

/* ── Notifications ──────────────────────────────────────────────────────── */

const showNotifications = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

const fetchNotifications = async () => {
    if (!user.value) return;
    const { ok, data } = await get('/notifications');
    if (ok && data) {
        notifications.value = data.notifications || [];
        unreadCount.value = data.unreadCount || 0;
    }
};

const markAllRead = async () => {
    const { ok } = await post('/notifications/read-all');
    if (ok) {
        notifications.value = notifications.value.map((n) => ({ ...n, read_at: new Date().toISOString() }));
        unreadCount.value = 0;
    }
};

const onNotificationsOpenChange = async (open) => {
    showNotifications.value = open;
    if (!open) return;

    await fetchNotifications();
    if (unreadCount.value > 0) markAllRead();
};

const loadUnreadCount = async () => {
    if (!user.value) return;
    const { ok, data } = await get('/notifications/unread-count');
    if (ok && data) unreadCount.value = data.count || 0;
};

nextTick(loadUnreadCount);
</script>

<template>
    <header class="fixed top-0 start-0 end-0 z-50 w-full bg-bg-secondary border-b border-border">
        <!--
            Three-column grid from `md` up: equal 1fr side columns with the search
            between them, so the field is centred against the header itself rather
            than against whatever space the side clusters happen to leave. A plain
            flex-1 spacer would drift as the right cluster changes width — it has
            five controls when signed in and two when signed out.

            Below `md` the search collapses to an icon button, so the row falls
            back to flex with the left cluster expanding to push the actions right.
        -->
        <div
            class="flex md:grid md:grid-cols-[1fr_minmax(0,34rem)_1fr] items-center gap-2 sm:gap-3 h-header px-3 sm:px-4"
        >
            <!-- Left: sidebar toggle + wordmark -->
            <div class="flex items-center gap-2 min-w-0 flex-1 md:flex-none">
                <button
                    class="hidden lg:flex p-2 shrink-0 text-text-secondary hover:text-text-primary hover:bg-bg-hover transition-colors"
                    :style="{ borderRadius: 'var(--radius-card)' }"
                    :aria-label="t('nav.toggle_sidebar')"
                    @click="emit('toggle-sidebar')"
                >
                    <PanelLeft class="w-5 h-5" />
                </button>

                <Link href="/" class="flex items-center min-w-0">
                    <img
                        v-if="themeSettings.site_logo"
                        :src="themeSettings.site_logo"
                        :alt="themeSettings.siteTitle || 'HubTube'"
                        class="h-7 object-contain"
                    />
                    <span
                        v-else
                        class="font-display font-bold uppercase tracking-tight leading-none truncate"
                        :style="{
                            color: themeSettings.siteTitleColor || 'var(--color-text-primary)',
                            fontSize: (themeSettings.siteTitleSize || 22) + 'px',
                            fontFamily: themeSettings.siteTitleFont || undefined,
                        }"
                    >{{ themeSettings.siteTitle || 'HubTube' }}</span>
                </Link>
            </div>

            <!-- Centre: search -->
            <ComboboxRoot
                v-model:open="showSuggestions"
                ignore-filter
                :reset-search-term-on-blur="false"
                class="hidden md:block relative flex-1 max-w-xl"
                @update:model-value="onSuggestionSelect"
            >
                <ComboboxAnchor as-child>
                    <form class="relative" role="search" @submit.prevent="handleSearch">
                        <Search class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none text-text-muted" />
                        <ComboboxInput
                            v-model="searchQuery"
                            :placeholder="t('common.search_placeholder')"
                            class="input ps-9 pe-3 w-full"
                            aria-label="Search videos"
                            autocomplete="off"
                            autocapitalize="off"
                            @input="searchSuggest($event.target.value)"
                        />
                    </form>
                </ComboboxAnchor>

                <ComboboxContent
                    v-if="hasSuggestions || suggestLoading"
                    position="popper"
                    :side-offset="4"
                    class="card shadow-xl z-[9999] max-h-80 overflow-y-auto scrollbar-hide w-[var(--reka-combobox-trigger-width)]"
                >
                    <SearchSuggestionList :suggestions="searchSuggestions" :loading="suggestLoading" />
                </ComboboxContent>
            </ComboboxRoot>

            <div class="flex-1 md:hidden"></div>

            <!-- Actions -->
            <div class="flex items-center gap-0.5 sm:gap-1 shrink-0">
                <button
                    class="md:hidden p-2 text-text-secondary hover:text-text-primary transition-colors"
                    :style="{ borderRadius: 'var(--radius-card)' }"
                    :aria-label="t('common.search')"
                    @click="emit('open-mobile-search')"
                >
                    <Search class="w-5 h-5" />
                </button>

                <ThemeToggle />

                <LanguageSwitcher mobile align="right" class="md:hidden" />

                <template v-if="user">
                    <BaseDropdown content-class="min-w-40 p-1">
                        <template #trigger>
                            <button
                                class="hidden sm:flex p-2 text-text-secondary hover:text-text-primary hover:bg-bg-hover transition-colors"
                                :style="{ borderRadius: 'var(--radius-card)' }"
                                :title="t('nav.upload_video')"
                                :aria-label="t('nav.upload_video')"
                            >
                                <Upload class="w-5 h-5" />
                            </button>
                        </template>

                        <DropdownMenuItem as-child>
                            <Link href="/upload" class="nav-item cursor-pointer">
                                <Film class="w-4 h-4" />
                                <span>{{ t('nav.upload_video') }}</span>
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link href="/image-upload" class="nav-item cursor-pointer">
                                <ImageIcon class="w-4 h-4" />
                                <span>{{ t('nav.upload_image') }}</span>
                            </Link>
                        </DropdownMenuItem>
                    </BaseDropdown>

                    <BaseDropdown
                        :model-value="showNotifications"
                        content-class="w-[min(21rem,calc(100vw-1rem))] max-h-96 overflow-y-auto scrollbar-hide"
                        @update:model-value="onNotificationsOpenChange"
                    >
                        <template #trigger>
                            <button
                                class="relative p-2 text-text-secondary hover:text-text-primary hover:bg-bg-hover transition-colors"
                                :style="{ borderRadius: 'var(--radius-card)' }"
                                :aria-label="t('nav.notifications')"
                            >
                                <Bell class="w-5 h-5" />
                                <span v-if="unreadCount > 0" class="absolute top-1 end-1 w-2 h-2 rounded-full bg-accent"></span>
                            </button>
                        </template>

                        <div class="flex items-center justify-between p-3 border-b border-border">
                            <h3 class="font-semibold text-sm text-text-primary">{{ t('nav.notifications') }}</h3>
                            <button v-if="unreadCount > 0" class="text-xs text-accent-text hover:underline" @click="markAllRead">
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
                                    class="flex items-start gap-3 p-3 border-b border-border last:border-b-0 no-underline transition-colors hover:bg-bg-hover"
                                    :class="[notif.data?.url ? 'cursor-pointer' : '', !notif.read_at ? 'bg-accent-subtle' : '']"
                                >
                                    <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center bg-bg-elevated">
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

                    <BaseDropdown content-class="w-60 p-2">
                        <template #trigger>
                            <button class="flex items-center ms-1" aria-label="User menu">
                                <div class="w-8 h-8 avatar">
                                    <img :src="user.avatar || '/assets/default_avatar.webp'" :alt="user.username" class="w-full h-full object-cover" />
                                </div>
                            </button>
                        </template>

                        <DropdownMenuLabel class="px-2.5 py-2 border-b border-border">
                            <p class="font-semibold text-text-primary">{{ user.username }}</p>
                            <p class="text-xs text-text-secondary truncate">{{ user.email }}</p>
                        </DropdownMenuLabel>
                        <div class="py-1.5">
                            <DropdownMenuItem v-if="user.is_admin" as-child>
                                <a href="/admin" class="nav-item text-accent-text cursor-pointer">
                                    <Shield class="w-4 h-4" />
                                    <span>{{ t('nav.admin_panel') }}</span>
                                </a>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link href="/dashboard" class="nav-item cursor-pointer">
                                    <LayoutDashboard class="w-4 h-4" />
                                    <span>{{ t('nav.dashboard') }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="`/channel/${user.username}`" class="nav-item cursor-pointer">
                                    <User class="w-4 h-4" />
                                    <span>{{ t('nav.your_channel') }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link href="/feed" class="nav-item cursor-pointer">
                                    <Rss class="w-4 h-4" />
                                    <span>{{ t('nav.subscriptions') }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="monetizationEnabled" as-child>
                                <Link href="/wallet" class="nav-item cursor-pointer">
                                    <Wallet class="w-4 h-4" />
                                    <span>{{ t('nav.wallet') }}: ${{ user.wallet_balance }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="pointsEnabled" as-child>
                                <Link href="/rewards" class="nav-item cursor-pointer">
                                    <Award class="w-4 h-4" />
                                    <span>{{ t('nav.rewards') }}: {{ user.points_balance?.toLocaleString() || 0 }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link href="/settings" class="nav-item cursor-pointer">
                                    <Settings class="w-4 h-4" />
                                    <span>{{ t('nav.settings') }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </div>
                        <div class="pt-1.5 border-t border-border">
                            <DropdownMenuItem as-child>
                                <Link href="/logout" method="post" as="button" class="nav-item w-full text-start text-accent-text cursor-pointer">
                                    <LogOut class="w-4 h-4" />
                                    <span>{{ t('nav.sign_out') }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </div>
                    </BaseDropdown>
                </template>

                <button
                    v-else
                    class="btn btn-primary px-3 py-1.5 ms-1"
                    :aria-label="t('auth.login')"
                    @click="emit('open-login')"
                >
                    <LogIn class="w-4 h-4" />
                    <span class="hidden sm:inline">{{ t('auth.login') }}</span>
                </button>
            </div>
        </div>

        <slot name="below" />
    </header>
</template>
