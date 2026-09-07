<script setup>
/**
 * Desktop left rail.
 *
 * Offsets derive from the --spacing-header / --spacing-catbar tokens rather than
 * the hardcoded `top: 96px` this used to carry. That number was correct — the
 * 56px bar plus the 40px menu row — but it was repeated in three files with
 * nothing tying it to either, so any change to the header height silently
 * desynchronised the rail and the main column.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Home, TrendingUp, Smartphone, LayoutGrid, ImageIcon, Tag,
    ListVideo, History, Rss,
} from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    collapsed: { type: Boolean, default: false },
});

const page = usePage();
const { localizedUrl, t } = useI18n();

const user = computed(() => page.props.auth?.user);
const iconSettings = computed(() => page.props.theme?.icons || {});

const navigation = computed(() => [
    { name: t('nav.home'), href: localizedUrl('/'), icon: Home, key: 'home' },
    { name: t('nav.trending'), href: localizedUrl('/trending'), icon: TrendingUp, key: 'trending' },
    { name: t('nav.shorts'), href: localizedUrl('/shorts'), icon: Smartphone, key: 'shorts' },
    { name: t('nav.categories'), href: localizedUrl('/categories'), icon: LayoutGrid, key: 'categories' },
    { name: t('nav.tags'), href: localizedUrl('/tags'), icon: Tag, key: 'tags' },
    { name: t('nav.images'), href: localizedUrl('/images'), icon: ImageIcon, key: 'images' },
]);

const libraryNav = computed(() => [
    { name: t('nav.subscriptions'), href: '/feed', icon: Rss, key: 'feed' },
    { name: t('nav.playlists'), href: '/playlists', icon: ListVideo, key: 'playlists' },
    { name: t('nav.history'), href: '/history', icon: History, key: 'history' },
]);

/** Per-item and global icon colours configured by the admin in Theme Settings. */
const getIconColor = (navKey) => {
    const icons = iconSettings.value;
    if (!icons) return undefined;

    if (icons[navKey]?.color) return icons[navKey].color;

    if (icons.colorMode === 'global' || icons.colorMode === 'custom') {
        return icons.globalColorDark || icons.globalColor || undefined;
    }

    return undefined;
};

const currentPath = computed(() => {
    try {
        return new URL(page.url, 'http://x').pathname.replace(/\/+$/, '') || '/';
    } catch {
        return '/';
    }
});

const isActive = (href) => {
    try {
        const path = new URL(href, 'http://x').pathname.replace(/\/+$/, '') || '/';
        return path === currentPath.value;
    } catch {
        return false;
    }
};

const width = computed(() => (props.collapsed ? 'var(--spacing-sidebar-collapsed)' : 'var(--spacing-sidebar)'));
</script>

<template>
    <aside
        class="fixed start-0 bottom-0 z-30 hidden lg:block overflow-y-auto scrollbar-hide transition-[width] duration-200 bg-bg-secondary border-e border-border"
        :style="{ top: 'calc(var(--spacing-header) + var(--spacing-catbar))', width }"
    >
        <nav class="p-2" aria-label="Main navigation">
            <ul class="space-y-0.5">
                <li v-for="item in navigation" :key="item.key">
                    <Link
                        :href="item.href"
                        class="nav-item"
                        :class="[collapsed ? 'justify-center px-0' : '', isActive(item.href) ? 'nav-item-active' : '']"
                        :title="collapsed ? item.name : undefined"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                    >
                        <component :is="item.icon" class="w-5 h-5 shrink-0" :style="{ color: getIconColor(item.key) }" />
                        <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                    </Link>
                </li>
            </ul>

            <div v-if="user" class="mt-5 pt-5 border-t border-border">
                <h3 v-if="!collapsed" class="nav-heading">{{ t('nav.library') }}</h3>
                <ul class="space-y-0.5">
                    <li v-for="item in libraryNav" :key="item.key">
                        <Link
                            :href="item.href"
                            class="nav-item"
                            :class="[collapsed ? 'justify-center px-0' : '', isActive(item.href) ? 'nav-item-active' : '']"
                            :title="collapsed ? item.name : undefined"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                        >
                            <component :is="item.icon" class="w-5 h-5 shrink-0" :style="{ color: getIconColor(item.key) }" />
                            <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                        </Link>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>
</template>
