<script setup>
/**
 * Scroll-aware bottom navigation for phones and tablets.
 *
 * Hides on scroll-down and near the footer so it never sits on top of the legal
 * links, which have to stay reachable.
 */
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useWindowScroll } from '@vueuse/core';
import { DropdownMenuItem } from 'reka-ui';
import {
    Home, Search, Plus, LayoutGrid, MoreHorizontal, Video, ImageIcon,
    Smartphone, TrendingUp, Tag, ListVideo,
} from 'lucide-vue-next';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    /** Footer root element — the dock hides once this scrolls into view. */
    footerEl: { type: Object, default: null },
});

const emit = defineEmits(['open-search']);

const { localizedUrl, t } = useI18n();

const visible = ref(true);
const { y } = useWindowScroll();

watch(y, (currentY, previousY) => {
    const el = props.footerEl?.root ?? props.footerEl;

    if (el?.getBoundingClientRect) {
        if (el.getBoundingClientRect().top < window.innerHeight + 20) {
            visible.value = false;
            return;
        }
    }

    if (currentY > previousY && currentY > 80) {
        visible.value = false;
    } else if (currentY < previousY) {
        visible.value = true;
    }
});

const items = computed(() => [
    { name: t('nav.home'), href: localizedUrl('/'), icon: Home },
    { name: t('common.search'), action: 'search', icon: Search },
    { name: t('nav.upload_video'), action: 'upload', icon: Plus, isCenter: true },
    { name: t('nav.categories'), href: localizedUrl('/categories'), icon: LayoutGrid },
    { name: t('nav.more'), action: 'more', icon: MoreHorizontal },
]);

const moreItems = computed(() => [
    { name: t('nav.shorts'), href: localizedUrl('/shorts'), icon: Smartphone },
    { name: t('nav.trending'), href: localizedUrl('/trending'), icon: TrendingUp },
    { name: t('nav.images'), href: localizedUrl('/images'), icon: ImageIcon },
    { name: t('nav.tags'), href: localizedUrl('/tags'), icon: Tag },
    { name: t('nav.playlists'), href: localizedUrl('/public-playlists'), icon: ListVideo },
]);
</script>

<template>
    <Transition name="mobile-nav">
        <nav
            v-if="visible"
            class="fixed bottom-3 start-0 end-0 z-40 mx-auto w-[calc(100%-1.5rem)] max-w-lg lg:hidden safe-bottom"
            aria-label="Mobile navigation"
        >
            <div
                class="flex justify-between items-center px-2 py-1.5 shadow-lg bg-bg-secondary border border-border-strong"
                :style="{ borderRadius: 'calc(var(--radius-card) * 3)' }"
            >
                <template v-for="item in items" :key="item.name">
                    <div v-if="item.isCenter" class="flex-1 min-w-0 flex justify-center">
                        <BaseDropdown side="top" align="center" :side-offset="12" content-class="p-1.5 w-36">
                            <template #trigger>
                                <button
                                    class="flex items-center justify-center w-11 h-11 rounded-full shadow-lg bg-accent active:scale-95 transition-transform"
                                    :aria-label="item.name"
                                >
                                    <Plus class="w-6 h-6 text-accent-contrast" />
                                </button>
                            </template>

                            <DropdownMenuItem as-child>
                                <Link href="/upload" class="nav-item cursor-pointer">
                                    <Video class="w-4 h-4" />
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
                    </div>

                    <div v-else-if="item.action === 'more'" class="flex-1 min-w-0 flex justify-center">
                        <BaseDropdown side="top" align="end" :side-offset="12" content-class="p-1.5 min-w-44">
                            <template #trigger>
                                <button class="flex flex-col items-center justify-center p-1.5" :aria-label="item.name">
                                    <MoreHorizontal class="w-5 h-5 text-text-secondary" />
                                    <span class="text-[10px] mt-0.5 text-text-muted">{{ item.name }}</span>
                                </button>
                            </template>

                            <DropdownMenuItem v-for="more in moreItems" :key="more.name" as-child>
                                <Link :href="more.href" class="nav-item cursor-pointer">
                                    <component :is="more.icon" class="w-4 h-4" />
                                    <span>{{ more.name }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </BaseDropdown>
                    </div>

                    <component
                        v-else
                        :is="item.href ? Link : 'button'"
                        :href="item.href || undefined"
                        class="flex-1 min-w-0 flex flex-col items-center justify-center p-1.5"
                        :aria-label="item.name"
                        @click="!item.href && item.action === 'search' ? emit('open-search') : null"
                    >
                        <component :is="item.icon" class="w-5 h-5 text-text-secondary" />
                        <span class="text-[10px] mt-0.5 truncate max-w-full text-text-muted">{{ item.name }}</span>
                    </component>
                </template>
            </div>
        </nav>
    </Transition>
</template>

<style scoped>
.mobile-nav-enter-active {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease-out;
}
.mobile-nav-leave-active {
    transition: transform 0.25s ease-in, opacity 0.25s ease-in;
}
.mobile-nav-enter-from,
.mobile-nav-leave-to {
    transform: translateY(120%);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .mobile-nav-enter-active,
    .mobile-nav-leave-active {
        transition: opacity 0.15s ease;
    }
    .mobile-nav-enter-from,
    .mobile-nav-leave-to {
        transform: none;
    }
}
</style>
