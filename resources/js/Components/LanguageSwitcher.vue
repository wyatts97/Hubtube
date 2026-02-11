<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import { Globe, Check, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    compact: { type: Boolean, default: false },
    direction: { type: String, default: 'down' },
});

const { locale, setLocale, supportedLocales, isTranslationEnabled } = useI18n();
const showDropdown = ref(false);
const dropdownRef = ref(null);

// Map locale codes to ISO 3166-1 alpha-2 country codes for flag images
const localeToCountry = {
    en: 'us', es: 'es', fr: 'fr', de: 'de', pt: 'br', it: 'it',
    nl: 'nl', ru: 'ru', ja: 'jp', ko: 'kr', zh: 'cn', ar: 'sa',
    hi: 'in', tr: 'tr', pl: 'pl', sv: 'se', da: 'dk', no: 'no',
    fi: 'fi', cs: 'cz', th: 'th', vi: 'vn', id: 'id', ms: 'my',
    ro: 'ro', uk: 'ua', el: 'gr', hu: 'hu', he: 'il', bg: 'bg',
    hr: 'hr', sk: 'sk', sr: 'rs', lt: 'lt', lv: 'lv', et: 'ee',
    fil: 'ph',
};

const getFlagUrl = (code) => {
    const country = localeToCountry[code] || code;
    return `https://flagcdn.com/w40/${country}.png`;
};

const selectLocale = async (code) => {
    showDropdown.value = false;
    await setLocale(code);
};

const currentLocaleData = computed(() => {
    return supportedLocales.value?.find(l => l.code === locale.value) || { label: 'English', code: 'en' };
});

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
        <!-- Compact mode: flag icon only (collapsed sidebar) -->
        <button
            v-if="compact"
            @click.stop="showDropdown = !showDropdown"
            class="flex items-center justify-center w-full py-2 rounded-lg transition-all"
            :class="showDropdown ? 'opacity-100' : 'opacity-70 hover:opacity-100'"
            :title="currentLocaleData.label"
        >
            <img
                :src="getFlagUrl(locale)"
                :alt="currentLocaleData.label"
                class="w-5 h-4 rounded-sm object-cover"
            />
        </button>

        <!-- Full mode: flag + label + chevron -->
        <button
            v-else
            @click.stop="showDropdown = !showDropdown"
            class="flex items-center gap-2 w-full px-2.5 py-2 rounded-lg transition-all text-xs"
            :class="showDropdown ? 'opacity-100' : 'opacity-70 hover:opacity-100'"
            style="color: var(--color-text-secondary);"
        >
            <img
                :src="getFlagUrl(locale)"
                :alt="currentLocaleData.label"
                class="w-5 h-4 rounded-sm object-cover shrink-0"
            />
            <span class="truncate min-w-0 font-medium">{{ currentLocaleData.label }}</span>
            <ChevronDown
                class="w-3 h-3 ml-auto shrink-0 transition-transform"
                :class="{ 'rotate-180': showDropdown }"
            />
        </button>

        <!-- Dropdown -->
        <div
            v-if="showDropdown"
            class="absolute z-50 w-48 rounded-lg shadow-2xl overflow-hidden"
            :class="[
                direction === 'up' ? 'bottom-full mb-1' : 'top-full mt-1',
                compact ? 'left-full ml-2' : 'left-0'
            ]"
            style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
        >
            <div class="max-h-72 overflow-y-auto scrollbar-hide">
                <button
                    v-for="loc in supportedLocales"
                    :key="loc.code"
                    @click="selectLocale(loc.code)"
                    class="flex items-center gap-2.5 w-full px-3 py-2 text-left text-[13px] transition-colors"
                    :style="loc.code === locale
                        ? { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }
                        : { color: 'var(--color-text-secondary)' }"
                    @mouseenter="$event.target.style.backgroundColor = 'var(--color-bg-secondary)'"
                    @mouseleave="loc.code !== locale && ($event.target.style.backgroundColor = 'transparent')"
                >
                    <img
                        :src="getFlagUrl(loc.code)"
                        :alt="loc.label"
                        class="w-5 h-4 rounded-sm object-cover shrink-0"
                    />
                    <span class="flex-1 truncate">{{ loc.label }}</span>
                    <Check v-if="loc.code === locale" class="w-3.5 h-3.5 shrink-0" style="color: var(--color-accent);" />
                </button>
            </div>
        </div>
    </div>
</template>
