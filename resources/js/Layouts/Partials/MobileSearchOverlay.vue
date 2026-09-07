<script setup>
/**
 * Full-screen search sheet for phones, where the header has no room for an
 * inline search field. Runs its own useSearchSuggestions instance — the
 * composable is a factory, so this does not fight the header's state.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ComboboxAnchor, ComboboxContent, ComboboxInput, ComboboxRoot } from 'reka-ui';
import { Search, X } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { useSearchSuggestions } from '@/Composables/useSearchSuggestions';
import SearchSuggestionList from '@/Components/SearchSuggestionList.vue';

const open = defineModel({ type: Boolean, default: false });

const { localizedUrl, t } = useI18n();

const query = ref('');
const {
    suggestions,
    hasResults,
    isLoading,
    isOpen: showSuggestions,
    search,
    clear,
    urlFor,
} = useSearchSuggestions();

watch(open, (isOpen) => {
    if (!isOpen) {
        query.value = '';
        clear();
    }
});

const submit = () => {
    if (!query.value.trim()) return;
    open.value = false;
    router.visit(`${localizedUrl('/search')}?q=${encodeURIComponent(query.value)}`);
};

const onSelect = (suggestion) => {
    if (!suggestion) return;
    open.value = false;
    router.visit(urlFor(suggestion));
};
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-start justify-center pt-3 px-3"
        :style="{ backgroundColor: 'var(--color-overlay)' }"
        @click.self="open = false"
    >
        <div class="w-full max-w-lg card p-3 shadow-xl">
            <ComboboxRoot
                v-model:open="showSuggestions"
                ignore-filter
                :reset-search-term-on-blur="false"
                @update:model-value="onSelect"
            >
                <ComboboxAnchor as-child>
                    <form class="flex items-center gap-2" role="search" @submit.prevent="submit">
                        <div class="relative flex-1">
                            <Search class="absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none text-text-muted" />
                            <ComboboxInput
                                v-model="query"
                                :placeholder="t('common.search_placeholder')"
                                class="input ps-9"
                                aria-label="Search videos"
                                auto-focus
                                autocomplete="off"
                                autocapitalize="off"
                                @input="search($event.target.value)"
                            />
                        </div>
                        <button
                            type="button"
                            class="p-2 text-text-secondary hover:text-text-primary"
                            :aria-label="t('common.close')"
                            @click="open = false"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </form>
                </ComboboxAnchor>

                <!-- Rendered inline rather than portalled so it stays inside the sheet. -->
                <ComboboxContent
                    v-if="hasResults || isLoading"
                    class="mt-2 max-h-72 overflow-y-auto scrollbar-hide"
                >
                    <SearchSuggestionList :suggestions="suggestions" :loading="isLoading" />
                </ComboboxContent>
            </ComboboxRoot>
        </div>
    </div>
</template>
