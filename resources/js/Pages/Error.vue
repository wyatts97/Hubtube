<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { AlertTriangle, RefreshCw, Home, Clock } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    status: Number,
    message: String,
});

const title = {
    419: 'Session Expired',
    403: 'Forbidden',
    404: 'Page Not Found',
    500: 'Server Error',
    503: 'Service Unavailable',
}[props.status] || 'Error';

const description = props.message || {
    419: 'Your session has expired. Please refresh the page to continue.',
    403: 'You do not have permission to access this resource.',
    404: 'The page you are looking for could not be found.',
    500: 'Something went wrong on our end. Please try again later.',
    503: 'We are currently undergoing maintenance. Please check back soon.',
}[props.status] || 'An unexpected error occurred.';

const countdown = ref(10);
let timer = null;

const is404 = computed(() => props.status === 404);

onMounted(() => {
    if (is404.value) {
        timer = setInterval(() => {
            countdown.value--;
            if (countdown.value <= 0) {
                clearInterval(timer);
                window.location.href = '/';
            }
        }, 1000);
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const refresh = () => {
    window.location.reload();
};
</script>

<template>
    <Head :title="title" />

    <AppLayout>
        <div class="flex items-center justify-center py-20 px-4">
            <div class="w-full max-w-md text-center">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style="background-color: color-mix(in srgb, var(--color-accent) 20%, transparent);">
                    <AlertTriangle class="w-10 h-10" style="color: var(--color-accent);" />
                </div>

                <h1 class="text-5xl font-bold mb-2" style="color: var(--color-text-primary);">{{ status }}</h1>
                <h2 class="text-xl font-semibold mb-4" style="color: var(--color-text-primary);">{{ title }}</h2>
                <p class="mb-6" style="color: var(--color-text-secondary);">{{ description }}</p>

                <!-- 404 Countdown -->
                <div v-if="is404" class="mb-6 inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm" style="background-color: var(--color-bg-secondary); color: var(--color-text-muted); border: 1px solid var(--color-border);">
                    <Clock class="w-4 h-4" />
                    Redirecting to homepage in <span class="font-bold" style="color: var(--color-accent);">{{ countdown }}</span>s
                </div>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="/" class="btn btn-primary inline-flex items-center gap-2">
                        <Home class="w-4 h-4" />
                        {{ t('errors.go_home') !== 'errors.go_home' ? t('errors.go_home') : 'Go Home' }}
                    </a>
                    <button v-if="!is404" @click="refresh" class="btn btn-secondary inline-flex items-center gap-2">
                        <RefreshCw class="w-4 h-4" />
                        {{ t('errors.refresh') !== 'errors.refresh' ? t('errors.refresh') : 'Refresh Page' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
