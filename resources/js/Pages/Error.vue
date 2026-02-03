<script setup>
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, RefreshCw } from 'lucide-vue-next';

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

const refresh = () => {
    window.location.reload();
};
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-md text-center">
            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style="background-color: color-mix(in srgb, var(--color-accent) 20%, transparent);">
                <AlertTriangle class="w-10 h-10" style="color: var(--color-accent);" />
            </div>
            
            <h1 class="text-4xl font-bold mb-2" style="color: var(--color-text-primary);">{{ status }}</h1>
            <h2 class="text-xl font-semibold mb-4" style="color: var(--color-text-primary);">{{ title }}</h2>
            <p class="mb-8" style="color: var(--color-text-secondary);">{{ description }}</p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button @click="refresh" class="btn btn-primary inline-flex items-center gap-2">
                    <RefreshCw class="w-4 h-4" />
                    Refresh Page
                </button>
                <a href="/" class="btn btn-secondary">
                    Go Home
                </a>
            </div>
        </div>
    </div>
</template>
