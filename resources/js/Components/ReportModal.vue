<script setup>
import { ref, computed, watch } from 'vue';
import { useFetch } from '@/Composables/useFetch';
import { useToast } from '@/Composables/useToast';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    reportableId: { type: [Number, String], required: true },
    reportableType: { type: String, default: 'video' },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();
const toast = useToast();
const { post } = useFetch();

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const reportReason = ref('');
const reportDescription = ref('');
const reportSubmitting = ref(false);
const reportSuccess = ref(false);

watch(show, (visible) => {
    if (visible) {
        reportReason.value = '';
        reportDescription.value = '';
        reportSuccess.value = false;
    }
});

const close = () => {
    show.value = false;
};

const submitReport = async () => {
    if (!reportReason.value) return;
    reportSubmitting.value = true;
    const { ok, data, status } = await post('/reports', {
        reportable_type: props.reportableType,
        reportable_id: props.reportableId,
        reason: reportReason.value,
        description: reportDescription.value,
    });
    reportSubmitting.value = false;
    if (ok) {
        reportSuccess.value = true;
        toast.success(data?.message || t('report.success') || 'Report submitted successfully!');
        setTimeout(() => {
            show.value = false;
            reportSuccess.value = false;
            reportReason.value = '';
            reportDescription.value = '';
        }, 1500);
    } else {
        const msg = data?.error || data?.message || (status === 422 ? 'You have already reported this content' : 'Failed to submit report. Please try again.');
        toast.error(msg);
    }
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background-color: rgba(0,0,0,0.6);"
            @click.self="close"
        >
            <div class="w-full max-w-md card p-6 shadow-xl bg-bg-card">
                <h3 class="text-lg font-bold mb-4 text-text-primary">{{ t('report.title') || 'Report Video' }}</h3>

                <div v-if="reportSuccess" class="text-center py-4">
                    <p class="text-green-500 font-medium">{{ t('report.success') || 'Report submitted successfully!' }}</p>
                </div>

                <form v-else @submit.prevent="submitReport" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2 text-text-secondary">{{ t('report.reason') || 'Reason' }}</label>
                        <select v-model="reportReason" class="input" required>
                            <option value="" disabled>{{ t('report.select_reason') || 'Select a reason' }}</option>
                            <option value="spam">{{ t('report.spam') || 'Spam or misleading' }}</option>
                            <option value="harassment">{{ t('report.harassment') || 'Harassment or bullying' }}</option>
                            <option value="illegal">{{ t('report.illegal') || 'Illegal content' }}</option>
                            <option value="copyright">{{ t('report.copyright') || 'Copyright violation' }}</option>
                            <option value="underage">{{ t('report.underage') || 'Underage content' }}</option>
                            <option value="other">{{ t('report.other') || 'Other' }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('report.details') || 'Details (optional)' }}</label>
                        <textarea v-model="reportDescription" class="input" rows="3" :placeholder="t('report.details_placeholder') || 'Provide additional details...'" maxlength="2000"></textarea>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="close" class="btn btn-secondary">{{ t('common.cancel') || 'Cancel' }}</button>
                        <button type="submit" :disabled="reportSubmitting || !reportReason" class="btn btn-primary">
                            {{ reportSubmitting ? (t('report.submitting') || 'Submitting...') : (t('report.submit') || 'Submit Report') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
