import { ref, reactive } from 'vue';

const toasts = reactive([]);
let toastId = 0;

export function useToast() {
    const add = (message, type = 'info', duration = 5000) => {
        const id = ++toastId;
        
        toasts.push({
            id,
            message,
            type, // 'success', 'error', 'warning', 'info'
            duration,
        });

        if (duration > 0) {
            setTimeout(() => {
                remove(id);
            }, duration);
        }

        return id;
    };

    const remove = (id) => {
        const index = toasts.findIndex(t => t.id === id);
        if (index > -1) {
            toasts.splice(index, 1);
        }
    };

    const success = (message, duration = 5000) => add(message, 'success', duration);
    const error = (message, duration = 5000) => add(message, 'error', duration);
    const warning = (message, duration = 5000) => add(message, 'warning', duration);
    const info = (message, duration = 5000) => add(message, 'info', duration);

    const clear = () => {
        toasts.splice(0, toasts.length);
    };

    return {
        toasts,
        add,
        remove,
        success,
        error,
        warning,
        info,
        clear,
    };
}
