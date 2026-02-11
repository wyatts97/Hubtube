<script setup>
import { computed } from 'vue';
import { useToast } from '@/Composables/useToast';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next';

const { toasts, remove } = useToast();

const getIcon = (type) => {
    switch (type) {
        case 'success': return CheckCircle;
        case 'error': return XCircle;
        case 'warning': return AlertTriangle;
        default: return Info;
    }
};

const getStyles = (type) => {
    switch (type) {
        case 'success':
            return {
                bg: 'rgba(34, 197, 94, 0.1)',
                border: 'rgba(34, 197, 94, 0.3)',
                icon: '#22c55e',
            };
        case 'error':
            return {
                bg: 'rgba(239, 68, 68, 0.1)',
                border: 'rgba(239, 68, 68, 0.3)',
                icon: '#ef4444',
            };
        case 'warning':
            return {
                bg: 'rgba(245, 158, 11, 0.1)',
                border: 'rgba(245, 158, 11, 0.3)',
                icon: '#f59e0b',
            };
        default:
            return {
                bg: 'rgba(59, 130, 246, 0.1)',
                border: 'rgba(59, 130, 246, 0.3)',
                icon: '#3b82f6',
            };
    }
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] flex flex-col items-center gap-3 pointer-events-none">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto rounded-lg px-4 py-3 shadow-lg backdrop-blur-sm flex items-center gap-3 max-w-sm w-auto"
                    :style="{
                        backgroundColor: getStyles(toast.type).bg,
                        border: `1px solid ${getStyles(toast.type).border}`,
                    }"
                >
                    <component 
                        :is="getIcon(toast.type)" 
                        class="w-5 h-5 flex-shrink-0 mt-0.5"
                        :style="{ color: getStyles(toast.type).icon }"
                    />
                    <p class="flex-1 text-sm" style="color: var(--color-text-primary);">
                        {{ toast.message }}
                    </p>
                    <button 
                        @click="remove(toast.id)"
                        class="flex-shrink-0 p-1 rounded hover:opacity-70 transition-opacity"
                        style="color: var(--color-text-secondary);"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active {
    animation: toast-in 0.3s ease-out;
}

.toast-leave-active {
    animation: toast-out 0.3s ease-in;
}

@keyframes toast-in {
    from {
        opacity: 0;
        transform: translateY(-100%);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes toast-out {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-100%);
    }
}
</style>
