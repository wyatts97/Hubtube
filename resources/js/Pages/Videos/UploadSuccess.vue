<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircle, Upload, Home } from 'lucide-vue-next';

defineProps({
    videoTitle: String,
});
</script>

<template>
    <Head title="Upload Successful" />

    <AppLayout>
        <div class="max-w-xl mx-auto text-center py-12">
            <div
                class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
                style="background-color: rgba(34, 197, 94, 0.1);"
            >
                <CheckCircle class="w-10 h-10 text-green-500" />
            </div>

            <h1 class="text-2xl font-bold mb-3" style="color: var(--color-text-primary);">
                Thank You for Your Upload!
            </h1>

            <p class="text-lg mb-2" style="color: var(--color-text-secondary);">
                Your video <strong v-if="videoTitle" style="color: var(--color-text-primary);">"{{ videoTitle }}"</strong> has been received.
            </p>

            <p class="mb-8" style="color: var(--color-text-muted);">
                It will be processed and reviewed by a moderator. Once approved, it will be published and visible to everyone. This usually happens within 24 hours.
            </p>

            <div class="flex items-center justify-center gap-4">
                <Link href="/upload" class="btn btn-primary inline-flex items-center gap-2">
                    <Upload class="w-4 h-4" />
                    Upload Another
                </Link>
                <Link href="/" class="btn inline-flex items-center gap-2" style="background-color: var(--color-bg-secondary); color: var(--color-text-primary);">
                    <Home class="w-4 h-4" />
                    Go Home
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
