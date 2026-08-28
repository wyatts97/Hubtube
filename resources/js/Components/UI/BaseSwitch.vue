<script setup>
/**
 * Toggle switch built on Reka's Switch primitive.
 *
 * Replaces the bare `<input type="checkbox">` toggles on the Settings page.
 * Cosmetic rather than an accessibility fix — a labelled checkbox was already
 * accessible — but the switch reads as on/off rather than checked/unchecked,
 * which matches what these settings actually are.
 *
 * Usage:
 *   <BaseSwitch v-model="form.email_notifications" :label="settings.email_notifications" />
 */
import { SwitchRoot, SwitchThumb } from 'reka-ui';

defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    /** Accessible name — these switches sit next to their text, not inside a <label>. */
    label: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <SwitchRoot
        :model-value="modelValue"
        :disabled="disabled"
        :aria-label="label || undefined"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border border-border transition-colors disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-accent data-[state=unchecked]:bg-bg-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <SwitchThumb
            class="pointer-events-none block h-5 w-5 rounded-full bg-white shadow transition-transform will-change-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0"
        />
    </SwitchRoot>
</template>
