<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Crown, CheckCircle } from 'lucide-vue-next';

const goToSettings = () => router.visit('/settings');
const goHome = () => router.visit('/');
</script>

<template>
    <Head title="Welcome to Pro" />

    <AppLayout>
        <div class="max-w-2xl mx-auto px-4 py-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-500/10 mb-6">
                <CheckCircle class="w-10 h-10 text-green-500" />
            </div>
            <h1 class="text-2xl sm:text-4xl font-bold mb-3 text-text-primary">You're now Pro!</h1>
            <p class="text-base sm:text-lg text-text-secondary mb-8">
                Welcome to HubTube Pro. Enjoy ad-free viewing, bigger uploads, video downloads, and more.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button @click="goToSettings" class="btn btn-primary">
                    <Crown class="w-4 h-4 mr-2" />
                    Manage Subscription
                </button>
                <button @click="goHome" class="btn btn-secondary">
                    Start Watching
                </button>
            </div>
        </div>
    </AppLayout>
</template>
