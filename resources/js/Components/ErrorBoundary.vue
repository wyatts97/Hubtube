<script setup>
import { ref, onErrorCaptured } from 'vue';
import { AlertTriangle, RefreshCw } from 'lucide-vue-next';

const props = defineProps({
    fallbackTitle: { type: String, default: 'Something went wrong' },
    fallbackDescription: { type: String, default: 'An error occurred while rendering this section.' },
});

const hasError = ref(false);
const errorMessage = ref('');

onErrorCaptured((err, instance, info) => {
    hasError.value = true;
    errorMessage.value = err?.message || 'Unknown error';
    console.error('[ErrorBoundary]', err, info);
    return false; // prevent propagation
});

const retry = () => {
    hasError.value = false;
    errorMessage.value = '';
};
</script>

<template>
    <div v-if="hasError" class="card p-6 text-center">
        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background-color: rgba(239, 68, 68, 0.1);">
            <AlertTriangle class="w-6 h-6" style="color: #ef4444;" />
        </div>
        <h3 class="font-medium mb-1" style="color: var(--color-text-primary);">{{ fallbackTitle }}</h3>
        <p class="text-sm mb-4" style="color: var(--color-text-muted);">{{ fallbackDescription }}</p>
        <button @click="retry" class="btn btn-secondary gap-2 text-sm mx-auto">
            <RefreshCw class="w-4 h-4" />
            Try Again
        </button>
    </div>
    <slot v-else />
</template>
