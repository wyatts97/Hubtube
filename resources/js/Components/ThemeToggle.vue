<script setup>
/**
 * Light/dark switch.
 *
 * Renders nothing when the admin has pinned the site to a single theme — an
 * inert toggle is worse than no toggle. See useTheme.js for how the mode is
 * resolved and why prefers-color-scheme is deliberately ignored.
 */
import { Moon, Sun } from 'lucide-vue-next';
import { useTheme } from '@/Composables/useTheme';
import { useI18n } from '@/Composables/useI18n';

defineProps({
    /** Show a text label beside the icon — used in menus, not in the header bar. */
    withLabel: { type: Boolean, default: false },
});

const { isLight, canToggle, toggleTheme } = useTheme();
const { t } = useI18n();
</script>

<template>
    <button
        v-if="canToggle"
        type="button"
        class="flex items-center gap-2 p-2 transition-colors text-text-secondary hover:text-text-primary hover:bg-bg-hover"
        :style="{ borderRadius: 'var(--radius-card)' }"
        :title="isLight ? t('theme.switch_to_dark') : t('theme.switch_to_light')"
        :aria-label="isLight ? t('theme.switch_to_dark') : t('theme.switch_to_light')"
        @click="toggleTheme"
    >
        <Moon v-if="isLight" class="w-5 h-5" />
        <Sun v-else class="w-5 h-5" />
        <span v-if="withLabel" class="text-sm">
            {{ isLight ? t('theme.switch_to_dark') : t('theme.switch_to_light') }}
        </span>
    </button>
</template>
