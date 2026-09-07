<script setup>
/**
 * Browse / search filter bar.
 *
 * Replaces a sort-button group plus a category dropdown on /videos, and fills a
 * complete gap on /search, which previously offered no filtering whatsoever.
 *
 * All state lives in the URL query string, so a filtered view is shareable, the
 * back button behaves, and the server renders the same thing on a cold load.
 * The component never fetches: it emits the new query and the page decides how
 * to apply it.
 */
import { computed } from 'vue';
import { DropdownMenuItem } from 'reka-ui';
import { Check, ChevronDown, SlidersHorizontal, X } from 'lucide-vue-next';
import BaseDropdown from '@/Components/UI/BaseDropdown.vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    /** Current filter values, normally read straight from the query string. */
    modelValue: {
        type: Object,
        default: () => ({ sort: '', duration: '', quality: '', date: '', category: '' }),
    },
    /** [{ id, name }] — omit to hide the category control (e.g. on a category page). */
    categories: { type: Array, default: () => [] },
    showCategory: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const sortOptions = computed(() => [
    { value: '', label: t('filters.sort_latest') },
    { value: 'popular', label: t('filters.sort_popular') },
    { value: 'rating', label: t('filters.sort_rating') },
    { value: 'longest', label: t('filters.sort_longest') },
    { value: 'oldest', label: t('filters.sort_oldest') },
]);

const durationOptions = computed(() => [
    { value: '', label: t('filters.any') },
    { value: 'short', label: t('filters.duration_short') },
    { value: 'medium', label: t('filters.duration_medium') },
    { value: 'long', label: t('filters.duration_long') },
]);

const qualityOptions = computed(() => [
    { value: '', label: t('filters.any') },
    { value: 'hd', label: t('filters.quality_hd') },
    { value: 'fhd', label: t('filters.quality_fhd') },
    { value: 'uhd', label: t('filters.quality_uhd') },
]);

const dateOptions = computed(() => [
    { value: '', label: t('filters.any') },
    { value: 'today', label: t('filters.date_today') },
    { value: 'week', label: t('filters.date_week') },
    { value: 'month', label: t('filters.date_month') },
    { value: 'year', label: t('filters.date_year') },
]);

const categoryOptions = computed(() => [
    { value: '', label: t('categories.all') },
    ...props.categories.map((c) => ({ value: String(c.id), label: c.name })),
]);

/** Controls in display order, so the template stays a single loop. */
const controls = computed(() => [
    { key: 'sort', label: t('filters.sort'), options: sortOptions.value, always: true },
    { key: 'duration', label: t('filters.duration'), options: durationOptions.value },
    { key: 'quality', label: t('filters.quality'), options: qualityOptions.value },
    { key: 'date', label: t('filters.date'), options: dateOptions.value },
    ...(props.showCategory && props.categories.length
        ? [{ key: 'category', label: t('filters.category'), options: categoryOptions.value }]
        : []),
]);

const valueOf = (key) => String(props.modelValue?.[key] ?? '');

/** Label to show on a closed control: the chosen option, or the control name. */
const displayLabel = (control) => {
    const current = valueOf(control.key);
    if (!current) return control.label;

    return control.options.find((o) => o.value === current)?.label ?? control.label;
};

/** Sort always has a value, so it shouldn't render as an "active filter". */
const isActive = (control) => !control.always && valueOf(control.key) !== '';

const activeCount = computed(() => controls.value.filter(isActive).length);

const select = (key, value) => {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
};

const clearAll = () => {
    const cleared = {};
    for (const control of controls.value) {
        cleared[control.key] = '';
    }
    emit('update:modelValue', { ...props.modelValue, ...cleared });
};
</script>

<template>
    <div class="flex items-center gap-2 flex-wrap">
        <SlidersHorizontal class="w-4 h-4 shrink-0 text-text-muted" aria-hidden="true" />

        <BaseDropdown
            v-for="control in controls"
            :key="control.key"
            align="start"
            :side-offset="6"
            content-class="p-1 min-w-52"
        >
            <template #trigger="{ open }">
                <button
                    class="chip"
                    :class="{ 'chip-active': isActive(control) }"
                    :aria-label="control.label"
                >
                    <span>{{ displayLabel(control) }}</span>
                    <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" />
                </button>
            </template>

            <DropdownMenuItem
                v-for="option in control.options"
                :key="option.value"
                class="nav-item w-full cursor-pointer"
                :class="{ 'nav-item-active': valueOf(control.key) === option.value }"
                @select="select(control.key, option.value)"
            >
                <Check
                    class="w-4 h-4 shrink-0"
                    :class="valueOf(control.key) === option.value ? 'opacity-100' : 'opacity-0'"
                    aria-hidden="true"
                />
                <span class="truncate">{{ option.label }}</span>
            </DropdownMenuItem>
        </BaseDropdown>

        <button v-if="activeCount > 0" class="chip gap-1" @click="clearAll">
            <X class="w-3.5 h-3.5" />
            <span>{{ t('filters.clear_all') }}</span>
        </button>
    </div>
</template>
