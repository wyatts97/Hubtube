<script setup>
/**
 * Accessible dropdown menu built on Reka UI's DropdownMenu primitives.
 * Handles outside-click, Escape, focus return, arrow-key navigation and
 * viewport-aware positioning — all of which the hand-rolled dropdowns lacked.
 *
 * The default slot receives the raw DropdownMenuContent body: children are NOT
 * forced into DropdownMenuItem, so a menu can mix real items with arbitrary
 * markup (e.g. the Save-to-Playlist create-input row, which uses
 * `@select.prevent` on its own item to keep the menu open).
 *
 * Usage:
 *   <BaseDropdown content-class="min-w-34 p-1">
 *     <template #trigger="{ open }">
 *       <button class="p-2 rounded-full"><Upload class="w-5 h-5" /></button>
 *     </template>
 *     <DropdownMenuItem as-child><Link href="/upload">Upload video</Link></DropdownMenuItem>
 *   </BaseDropdown>
 */
import { computed, ref } from 'vue';
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';

const props = defineProps({
    /** Optional controlled open state. Leave unbound to let the menu manage itself. */
    modelValue: { type: Boolean, default: null },
    align: { type: String, default: 'end' },
    side: { type: String, default: 'bottom' },
    sideOffset: { type: Number, default: 8 },
    /** Appended to the panel — width, max-height and padding overrides live here. */
    contentClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const uncontrolledOpen = ref(false);

const isOpen = computed({
    get: () => (props.modelValue === null ? uncontrolledOpen.value : props.modelValue),
    set: (value) => {
        uncontrolledOpen.value = value;
        emit('update:modelValue', value);
    },
});

const close = () => {
    isOpen.value = false;
};
</script>

<template>
    <DropdownMenuRoot v-model:open="isOpen">
        <DropdownMenuTrigger as-child>
            <slot name="trigger" :open="isOpen" />
        </DropdownMenuTrigger>

        <DropdownMenuPortal>
            <DropdownMenuContent
                :align="align"
                :side="side"
                :side-offset="sideOffset"
                :class="contentClass"
                class="card shadow-xl bg-bg-card border border-border z-[9999] focus:outline-none"
            >
                <slot :close="close" />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
