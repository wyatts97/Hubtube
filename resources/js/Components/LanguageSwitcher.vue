<script setup>
import { computed } from 'vue';
import { DropdownMenuItem } from 'reka-ui';
import { useI18n } from '@/Composables/useI18n';
import { Check, ChevronDown, Languages } from 'lucide-vue-next';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';

const props = defineProps({
    compact: { type: Boolean, default: false },
    mobile: { type: Boolean, default: false },
    direction: { type: String, default: 'down' },
    align: { type: String, default: 'left' },
});

const { locale, setLocale, supportedLocales, isTranslationEnabled } = useI18n();

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
    await setLocale(code);
};

const currentLocaleData = computed(() => {
    return supportedLocales.value?.find(l => l.code === locale.value) || { label: 'English', code: 'en' };
});

// The old panel positioned itself with Tailwind offset classes; those map onto
// Reka's side/align. `compact` (collapsed sidebar) opened to the right of the
// rail, `direction="up"` opened above, and `align="right"` right-edge-aligned.
const menuSide = computed(() => {
    if (props.compact) return 'right';
    return props.direction === 'up' ? 'top' : 'bottom';
});

const menuAlign = computed(() => (props.align === 'right' ? 'end' : 'start'));
</script>

<template>
    <!--
        The wrapper div is load-bearing: call sites pass layout classes
        (`md:hidden`, `shrink-0 ms-4`) that fall through to this component's
        root, and BaseDropdown's root is a renderless fragment that cannot
        receive them.
    -->
    <div v-if="isTranslationEnabled">
    <BaseDropdown
        :side="menuSide"
        :align="menuAlign"
        :side-offset="4"
        content-class="w-48 rounded-lg shadow-2xl max-h-72 overflow-y-auto scrollbar-hide"
    >
        <!-- Three trigger variants, one shared panel. -->
        <template #trigger="{ open }">
            <!-- Mobile mode: Languages icon only (for mobile header) -->
            <button
                v-if="mobile"
                class="p-2 rounded-full transition-all text-text-secondary"
                :class="open ? 'opacity-100' : 'opacity-70 hover:opacity-100'"
                :title="currentLocaleData.label"
                aria-label="Change language"
            >
                <Languages class="w-5 h-5" />
            </button>

            <!-- Compact mode: flag icon only (collapsed sidebar) -->
            <button
                v-else-if="compact"
                class="flex items-center justify-center w-full py-2 rounded-lg transition-all"
                :class="open ? 'opacity-100' : 'opacity-70 hover:opacity-100'"
                :title="currentLocaleData.label"
                aria-label="Change language"
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
                class="flex items-center gap-2 w-full px-2.5 py-2 rounded-lg transition-all text-xs text-text-secondary"
                :class="open ? 'opacity-100' : 'opacity-70 hover:opacity-100'"
                aria-label="Change language"
            >
                <img
                    :src="getFlagUrl(locale)"
                    :alt="currentLocaleData.label"
                    class="w-5 h-4 rounded-sm object-cover shrink-0"
                />
                <span class="truncate min-w-0 font-medium">{{ currentLocaleData.label }}</span>
                <ChevronDown
                    class="w-3 h-3 ms-auto shrink-0 transition-transform"
                    :class="{ 'rotate-180': open }"
                />
            </button>
        </template>

        <DropdownMenuItem
            v-for="loc in supportedLocales"
            :key="loc.code"
            class="flex items-center gap-2.5 w-full px-3 py-2 text-start text-[13px] transition-colors cursor-pointer outline-none data-[highlighted]:bg-bg-secondary"
            :style="loc.code === locale
                ? { backgroundColor: 'var(--color-bg-secondary)', color: 'var(--color-text-primary)' }
                : { color: 'var(--color-text-secondary)' }"
            @select="selectLocale(loc.code)"
        >
            <img
                :src="getFlagUrl(loc.code)"
                :alt="loc.label"
                class="w-5 h-4 rounded-sm object-cover shrink-0"
            />
            <span class="flex-1 truncate">{{ loc.label }}</span>
            <Check v-if="loc.code === locale" class="w-3.5 h-3.5 shrink-0 text-accent" />
        </DropdownMenuItem>
    </BaseDropdown>
    </div>
</template>
