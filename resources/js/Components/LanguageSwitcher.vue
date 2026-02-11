<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import { Globe, Check, ChevronUp } from 'lucide-vue-next';

const props = defineProps({
    compact: { type: Boolean, default: false },
    direction: { type: String, default: 'up' }, // 'up' or 'down'
});

const { locale, setLocale, supportedLocales, isTranslationEnabled } = useI18n();
const showDropdown = ref(false);
const dropdownRef = ref(null);

const selectLocale = async (code) => {
    showDropdown.value = false;
    await setLocale(code);
};

const currentLocaleData = () => {
    return supportedLocales.value?.find(l => l.code === locale.value) || { label: 'English', flag: '' };
};

const handleClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div v-if="isTranslationEnabled" ref="dropdownRef" class="relative">
        <!-- Compact mode: icon only (for collapsed sidebar) -->
        <button
            v-if="compact"
            @click.stop="showDropdown = !showDropdown"
            class="flex items-center justify-center w-full px-3 py-2 rounded-lg hover:opacity-80 transition-opacity"
            style="color: var(--color-text-secondary);"
            title="Language"
        >
            <Globe class="w-5 h-5" />
        </button>

        <!-- Full mode: flag + label -->
        <button
            v-else
            @click.stop="showDropdown = !showDropdown"
            class="flex items-center gap-1.5 w-full px-2 py-1.5 rounded-lg hover:opacity-80 transition-opacity text-xs"
            style="color: var(--color-text-secondary);"
        >
            <Globe class="w-4 h-4 shrink-0" />
            <span class="truncate min-w-0">{{ currentLocaleData().flag }} {{ currentLocaleData().label }}</span>
            <ChevronUp 
                class="w-3 h-3 ml-auto shrink-0 transition-transform" 
                :class="{ 'rotate-180': showDropdown }" 
            />
        </button>

        <!-- Dropdown -->
        <div
            v-if="showDropdown"
            class="absolute left-0 w-52 rounded-xl shadow-xl z-50 overflow-hidden"
            :class="direction === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'"
            style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
        >
            <div class="px-3 py-2 border-b" style="border-color: var(--color-border);">
                <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-muted);">Language</p>
            </div>
            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="loc in supportedLocales"
                    :key="loc.code"
                    @click="selectLocale(loc.code)"
                    class="flex items-center gap-2.5 w-full px-3 py-2 text-left text-sm transition-colors hover:opacity-80"
                    :style="loc.code === locale
                        ? { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }
                        : { color: 'var(--color-text-secondary)' }"
                >
                    <span class="text-base leading-none">{{ loc.flag }}</span>
                    <span class="flex-1 truncate">{{ loc.label }}</span>
                    <Check v-if="loc.code === locale" class="w-4 h-4 shrink-0" style="color: var(--color-accent);" />
                </button>
            </div>
        </div>
    </div>
</template>
