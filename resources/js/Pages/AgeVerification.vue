<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ShieldAlert, Loader2 } from 'lucide-vue-next';

// Use Inertia form for proper CSRF handling
const form = useForm({});
const isSubmitting = ref(false);
const errorMessage = ref('');

const confirm = () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    
    form.post('/age-verify', {
        preserveScroll: true,
        onSuccess: () => {
            // Successfully verified
        },
        onError: (errors) => {
            isSubmitting.value = false;
            // If CSRF error or session expired, show message and offer refresh
            if (errors.message?.includes('CSRF') || errors.message?.includes('session') || Object.keys(errors).length === 0) {
                errorMessage.value = 'Your session has expired. The page will refresh automatically.';
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                errorMessage.value = 'An error occurred. Please try again.';
            }
        },
        onFinish: () => {
            if (!errorMessage.value) {
                isSubmitting.value = false;
            }
        },
    });
};

const decline = () => {
    window.location.href = '/age-verify/decline';
};
</script>

<template>
    <Head title="Age Verification" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-lg text-center">
            <div class="mb-8">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style="background-color: color-mix(in srgb, var(--color-accent) 20%, transparent);">
                    <ShieldAlert class="w-10 h-10" style="color: var(--color-accent);" />
                </div>
                <h1 class="text-3xl font-bold mb-4" style="color: var(--color-text-primary);">Age Verification Required</h1>
                <p class="text-lg" style="color: var(--color-text-muted);">
                    This website contains age-restricted content. You must be at least 18 years old to enter.
                </p>
            </div>

            <div class="card p-8">
                <p class="mb-6" style="color: var(--color-text-secondary);">
                    By clicking "I am 18 or older", you confirm that you are at least 18 years of age and consent to viewing adult content.
                </p>

                <!-- Error Message -->
                <div v-if="errorMessage" class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-500 text-sm">
                    {{ errorMessage }}
                </div>

                <div class="flex flex-col sm:flex-row gap-4">
                    <button 
                        @click="confirm" 
                        :disabled="isSubmitting"
                        class="btn btn-primary flex-1 py-3 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Loader2 v-if="isSubmitting" class="w-5 h-5 animate-spin mr-2" />
                        {{ isSubmitting ? 'Verifying...' : 'I am 18 or older' }}
                    </button>
                    <button @click="decline" :disabled="isSubmitting" class="btn btn-secondary flex-1 py-3">
                        Exit
                    </button>
                </div>

                <p class="text-sm mt-6" style="color: var(--color-text-muted);">
                    By entering this site, you agree to our
                    <a href="/terms" style="color: var(--color-accent);" class="hover:opacity-80">Terms of Service</a>
                    and
                    <a href="/privacy" style="color: var(--color-accent);" class="hover:opacity-80">Privacy Policy</a>.
                </p>
            </div>
        </div>
    </div>
</template>
