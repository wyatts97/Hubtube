<script setup>
import { ref } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import { Globe, Check } from 'lucide-vue-next';

const { locale, setLocale, supportedLocales, t } = useI18n();
const showDropdown = ref(false);

const selectLocale = async (code) => {
    await setLocale(code);
    showDropdown.value = false;
};

const currentLabel = () => {
    return supportedLocales.find(l => l.code === locale.value)?.label || 'English';
};
</script>

<template>
    <div class="relative">
        <button
            @click.stop="showDropdown = !showDropdown"
            class="btn btn-secondary gap-2 text-sm"
        >
            <Globe class="w-4 h-4" />
            <span>{{ currentLabel() }}</span>
        </button>

        <div
            v-if="showDropdown"
            class="absolute bottom-full mb-2 left-0 w-48 rounded-xl shadow-xl z-50 overflow-hidden"
            style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
        >
            <div class="max-h-64 overflow-y-auto">
                <button
                    v-for="loc in supportedLocales"
                    :key="loc.code"
                    @click="selectLocale(loc.code)"
                    class="flex items-center justify-between w-full px-3 py-2.5 text-left text-sm transition-colors hover:opacity-80"
                    :style="loc.code === locale
                        ? { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }
                        : { color: 'var(--color-text-secondary)' }"
                >
                    <span>{{ loc.label }}</span>
                    <Check v-if="loc.code === locale" class="w-4 h-4" style="color: var(--color-accent);" />
                </button>
            </div>
        </div>
    </div>
</template>
