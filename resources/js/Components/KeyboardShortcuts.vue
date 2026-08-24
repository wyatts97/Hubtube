<script setup>
import { ref } from 'vue';
import { useEventListener } from '@vueuse/core';
import { Keyboard, X } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import BaseDialog from '@/Components/UI/BaseDialog.vue';

const { t } = useI18n();

const show = ref(false);

const shortcuts = [
    { key: 'Space', description: 'Play / Pause' },
    { key: 'K', description: 'Play / Pause' },
    { key: '←', description: 'Rewind 5 seconds' },
    { key: '→', description: 'Forward 5 seconds' },
    { key: 'J', description: 'Rewind 10 seconds' },
    { key: 'L', description: 'Forward 10 seconds' },
    { key: '↑', description: 'Volume up' },
    { key: '↓', description: 'Volume down' },
    { key: 'M', description: 'Mute / Unmute' },
    { key: 'F', description: 'Toggle fullscreen' },
    { key: '0-9', description: 'Seek to 0%-90%' },
    { key: '?', description: 'Show this guide' },
];

// Escape is no longer handled here — BaseDialog's Dialog closes on Escape
// itself. This listener is only the `?` toggle now.
const handleKeydown = (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
    if (e.key === '?' || (e.key === '/' && e.shiftKey)) {
        e.preventDefault();
        show.value = !show.value;
    }
};

useEventListener(document, 'keydown', handleKeydown);
</script>

<template>
    <!-- Trigger button -->
    <button
        @click="show = true"
        class="btn btn-secondary gap-2"
        :title="t('video.shortcuts.title')"
    >
        <Keyboard class="w-5 h-5" />
        <span class="hidden sm:inline">{{ t('video.shortcuts.label') }}</span>
    </button>

    <!-- Overlay -->
    <BaseDialog v-model="show" max-width="max-w-lg" :aria-label="t('video.keyboard_shortcuts')">
        <template #header="{ close }">
            <div class="flex items-center justify-between p-6 pb-5">
                <h3 class="text-lg font-bold flex items-center gap-2 text-text-primary">
                    <Keyboard class="w-5 h-5" />
                    {{ t('video.keyboard_shortcuts') }}
                </h3>
                <button @click="close" class="p-1 rounded hover:opacity-70" aria-label="Close keyboard shortcuts">
                    <X class="w-5 h-5 text-text-secondary" />
                </button>
            </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
            <div
                v-for="shortcut in shortcuts"
                :key="shortcut.key"
                class="flex items-center justify-between py-2 border-b border-border"
            >
                <span class="text-sm text-text-secondary">{{ shortcut.description }}</span>
                <kbd
                    class="px-2 py-0.5 rounded text-xs font-mono font-medium ms-3 bg-bg-secondary text-text-primary border border-border"
                >
                    {{ shortcut.key }}
                </kbd>
            </div>
        </div>
        <p class="text-xs mt-4 text-center text-text-muted">
            Press <kbd class="px-1.5 py-0.5 rounded text-xs font-mono bg-bg-secondary border border-border">?</kbd> anytime to toggle this guide
        </p>
    </BaseDialog>
</template>
