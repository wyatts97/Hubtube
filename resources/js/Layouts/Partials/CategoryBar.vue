<script setup>
/**
 * Persistent category / navigation strip directly under the header.
 *
 * This is the row that most distinguishes a tube site from a generic video SPA:
 * browsing is category-first, so the taxonomy is always one click away instead
 * of hidden behind a hamburger. It renders the admin-configured header menu
 * (MenuItem tree), which falls back to top-level categories when an operator
 * hasn't customised it — see HandleInertiaRequests::getMenuItems().
 *
 * The orientation glyphs in Components/Icons are given real presence here. They
 * are the only bespoke artwork the frontend has, and they were previously used
 * at 16px as incidental nav decoration.
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import {
    ChevronDown, Tag, Folder, Star, Home, Zap, TrendingUp, Video, Film,
    ListVideo, History, Search,
} from 'lucide-vue-next';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import GenderMaleIcon from '@/Components/Icons/GenderMaleIcon.vue';
import GenderFemaleIcon from '@/Components/Icons/GenderFemaleIcon.vue';
import GaySymbolIcon from '@/Components/Icons/GaySymbolIcon.vue';
import LesbianSymbolIcon from '@/Components/Icons/LesbianSymbolIcon.vue';
import TransgenderSymbolIcon from '@/Components/Icons/TransgenderSymbolIcon.vue';
import StraightSymbolIcon from '@/Components/Icons/StraightSymbolIcon.vue';

const page = usePage();

const menuItems = computed(() => page.props.menuItems?.header || []);

const lucideIconMap = {
    tag: Tag, folder: Folder, star: Star, home: Home, zap: Zap,
    'trending-up': TrendingUp, video: Video, film: Film,
    'list-video': ListVideo, history: History, search: Search,
};

const genderIconMap = {
    'gender-male': GenderMaleIcon,
    'gender-female': GenderFemaleIcon,
    'gender-gay': GaySymbolIcon,
    'gender-lesbian': LesbianSymbolIcon,
    'gender-transgender': TransgenderSymbolIcon,
    'gender-straight': StraightSymbolIcon,
};

const menuIconMap = { ...lucideIconMap, ...genderIconMap };

const getMenuIcon = (iconName) => (iconName ? menuIconMap[iconName] || Tag : null);

/**
 * The bar had no active state at all before, so you could never tell which
 * category you were browsing. Compares pathname only — query strings carry
 * filters and paging, which must not break the highlight.
 */
const currentPath = computed(() => {
    try {
        return new URL(page.url, 'http://x').pathname.replace(/\/+$/, '') || '/';
    } catch {
        return '/';
    }
});

const isActive = (url) => {
    if (!url) return false;
    try {
        const path = new URL(url, 'http://x').pathname.replace(/\/+$/, '') || '/';
        return path === currentPath.value;
    } catch {
        return false;
    }
};

const hasActiveChild = (item) => (item.children || []).some((child) => isActive(child.url));
</script>

<template>
    <div v-if="menuItems.length" class="hidden md:flex items-center gap-3 h-catbar px-4 border-t border-border-subtle">
        <nav class="tab-strip flex-1 min-w-0" aria-label="Categories">
            <template v-for="item in menuItems" :key="item.id">
                <div v-if="item.type === 'divider'" class="w-px h-4 mx-1.5 bg-border" role="separator"></div>

                <BaseDropdown
                    v-else-if="(item.type === 'dropdown' || item.is_mega) && item.children?.length"
                    align="start"
                    :side-offset="6"
                    content-class="p-3"
                    :content-style="{ minWidth: item.is_mega ? (item.mega_columns * 170) + 'px' : '210px' }"
                >
                    <template #trigger="{ open }">
                        <button class="chip" :class="{ 'chip-active': hasActiveChild(item) }">
                            <component :is="getMenuIcon(item.icon)" v-if="getMenuIcon(item.icon)" class="w-4 h-4" />
                            <span>{{ item.label }}</span>
                            <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" />
                        </button>
                    </template>

                    <div
                        :class="item.is_mega ? 'grid gap-1' : 'flex flex-col gap-0.5'"
                        :style="item.is_mega ? { gridTemplateColumns: `repeat(${item.mega_columns || 4}, minmax(0, 1fr))` } : {}"
                    >
                        <template v-for="child in item.children" :key="child.id">
                            <DropdownMenuItem v-if="child.type !== 'divider'" as-child>
                                <Link
                                    :href="child.url || '#'"
                                    :target="child.target || '_self'"
                                    class="nav-item cursor-pointer"
                                    :class="{ 'nav-item-active': isActive(child.url) }"
                                >
                                    <component :is="getMenuIcon(child.icon)" v-if="getMenuIcon(child.icon)" class="w-4 h-4 shrink-0" />
                                    <span class="truncate">{{ child.label }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <div v-else class="border-t my-1 border-border"></div>
                        </template>
                    </div>
                </BaseDropdown>

                <Link
                    v-else
                    :href="item.url || '#'"
                    :target="item.target || '_self'"
                    class="chip"
                    :class="{ 'chip-active': isActive(item.url) }"
                    :aria-current="isActive(item.url) ? 'page' : undefined"
                >
                    <component :is="getMenuIcon(item.icon)" v-if="getMenuIcon(item.icon)" class="w-4 h-4" />
                    <span>{{ item.label }}</span>
                </Link>
            </template>
        </nav>

        <LanguageSwitcher align="right" class="shrink-0" />
    </div>
</template>
