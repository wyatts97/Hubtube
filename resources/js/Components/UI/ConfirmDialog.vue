<script setup>
/**
 * Yes/no confirmation for a destructive action, in place of the browser's
 * confirm(), which can't be themed or translated.
 */
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import { useI18n } from '@/Composables/useI18n';

const open = defineModel({ type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
});

const emit = defineEmits(['confirm']);
const { t } = useI18n();

const confirm = () => {
    open.value = false;
    emit('confirm');
};
</script>

<template>
    <BaseDialog v-model="open" variant="alert" :title="title">
        <p v-if="message" class="text-sm text-text-secondary">{{ message }}</p>
        <div class="mt-5 flex justify-end gap-2">
            <button class="btn btn-secondary" @click="open = false">{{ t('common.cancel') }}</button>
            <button class="btn btn-primary" @click="confirm">{{ confirmLabel || t('common.delete') }}</button>
        </div>
    </BaseDialog>
</template>
