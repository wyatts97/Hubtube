<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';

const confirm = () => {
    router.post('/age-verify');
};

const decline = () => {
    window.location.href = '/age-verify/decline';
};
</script>

<template>
    <Head title="Age Verification" />

    <div class="min-h-screen flex items-center justify-center bg-dark-950 px-4">
        <div class="w-full max-w-lg text-center">
            <div class="mb-8">
                <div class="w-20 h-20 bg-primary-600/20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <ShieldAlert class="w-10 h-10 text-primary-500" />
                </div>
                <h1 class="text-3xl font-bold text-white mb-4">Age Verification Required</h1>
                <p class="text-dark-400 text-lg">
                    This website contains age-restricted content. You must be at least 18 years old to enter.
                </p>
            </div>

            <div class="card p-8">
                <p class="text-dark-300 mb-6">
                    By clicking "I am 18 or older", you confirm that you are at least 18 years of age and consent to viewing adult content.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <button @click="confirm" class="btn btn-primary flex-1 py-3">
                        I am 18 or older
                    </button>
                    <button @click="decline" class="btn btn-secondary flex-1 py-3">
                        Exit
                    </button>
                </div>

                <p class="text-dark-500 text-sm mt-6">
                    By entering this site, you agree to our
                    <a href="/terms" class="text-primary-500 hover:text-primary-400">Terms of Service</a>
                    and
                    <a href="/privacy" class="text-primary-500 hover:text-primary-400">Privacy Policy</a>.
                </p>
            </div>
        </div>
    </div>
</template>
