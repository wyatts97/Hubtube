<script setup>
/**
 * Rendering only. `Composables/useToast.js` is untouched: its module-level
 * `toasts` array stays the single source of truth (40+ call sites depend on
 * `success/error/warning/info`), and its own `setTimeout` remains the only
 * auto-dismiss clock.
 *
 * That is why every toast passes `:duration="0"` — Reka's `startTimer` bails
 * out on a duration of 0, so it never runs a competing timer that could close
 * a toast while the composable still has it in the array.
 *
 * What Reka adds on top: an aria-live region so toasts are announced, the F8
 * hotkey to jump to them, swipe-to-dismiss, and pause on hover / window blur.
 */
import { ToastProvider, ToastRoot, ToastViewport } from 'reka-ui';
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
    <ToastProvider :duration="0" swipe-direction="up">
        <ToastRoot
            v-for="toast in toasts"
            :key="toast.id"
            :duration="0"
            as-child
            @update:open="(open) => !open && remove(toast.id)"
        >
            <div
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
                <p class="flex-1 text-sm text-text-primary">
                    {{ toast.message }}
                </p>
                <button
                    @click="remove(toast.id)"
                    class="flex-shrink-0 p-1 rounded hover:opacity-70 transition-opacity text-text-secondary"
                    aria-label="Dismiss notification"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </ToastRoot>

        <ToastViewport
            class="fixed top-4 start-1/2 -translate-x-1/2 z-[9999] flex flex-col items-center gap-3 pointer-events-none m-0 p-0 list-none outline-none"
        />
    </ToastProvider>
</template>

<style scoped>
/*
 * Reka stamps data-state on the toast element (we use `as-child`, so it lands
 * on our own div), which replaces the TransitionGroup enter class.
 *
 * Note: the leave animation is gone. Removal is driven by the composable
 * splicing its array, so the element unmounts outright rather than passing
 * through data-state="closed". Restoring it would mean moving the auto-dismiss
 * clock into Reka, which means editing useToast.js — deliberately out of scope.
 */
div[data-state='open'] {
    animation: toast-in 0.3s ease-out;
}

div[data-state='closed'] {
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
