<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Keyboard, X } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

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

const handleKeydown = (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
    if (e.key === '?' || (e.key === '/' && e.shiftKey)) {
        e.preventDefault();
        show.value = !show.value;
    }
    if (e.key === 'Escape' && show.value) {
        show.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <!-- Trigger button -->
    <button
        @click="show = true"
        class="btn btn-secondary gap-2"
        :title="t('video.shortcuts.title') || 'Keyboard shortcuts (?)'"
    >
        <Keyboard class="w-5 h-5" />
        <span class="hidden sm:inline">{{ t('video.shortcuts') || 'Shortcuts' }}</span>
    </button>

    <!-- Overlay -->
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background-color: rgba(0,0,0,0.6);"
            @click.self="show = false"
        >
            <div class="w-full max-w-lg card p-6 shadow-xl" style="background-color: var(--color-bg-card);">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold flex items-center gap-2" style="color: var(--color-text-primary);">
                        <Keyboard class="w-5 h-5" />
                        {{ t('video.keyboard_shortcuts') || 'Keyboard Shortcuts' }}
                    </h3>
                    <button @click="show = false" class="p-1 rounded hover:opacity-70">
                        <X class="w-5 h-5" style="color: var(--color-text-secondary);" />
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                    <div
                        v-for="shortcut in shortcuts"
                        :key="shortcut.key"
                        class="flex items-center justify-between py-2"
                        style="border-bottom: 1px solid var(--color-border);"
                    >
                        <span class="text-sm" style="color: var(--color-text-secondary);">{{ shortcut.description }}</span>
                        <kbd
                            class="px-2 py-0.5 rounded text-xs font-mono font-medium ml-3"
                            style="background-color: var(--color-bg-secondary); color: var(--color-text-primary); border: 1px solid var(--color-border);"
                        >
                            {{ shortcut.key }}
                        </kbd>
                    </div>
                </div>
                <p class="text-xs mt-4 text-center" style="color: var(--color-text-muted);">
                    Press <kbd class="px-1.5 py-0.5 rounded text-xs font-mono" style="background-color: var(--color-bg-secondary); border: 1px solid var(--color-border);">?</kbd> anytime to toggle this guide
                </p>
            </div>
        </div>
    </Teleport>
</template>
