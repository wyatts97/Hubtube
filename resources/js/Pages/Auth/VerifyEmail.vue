<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const form = useForm({});
const sent = ref(false);

const resend = () => {
    form.post('/email/verification-notification', {
        onSuccess: () => {
            sent.value = true;
        },
    });
};
</script>

<template>
    <Head title="Verify Email" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto" style="background-color: var(--color-accent); opacity: 0.9;">
                    <Mail class="w-8 h-8 text-white" />
                </div>
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">{{ t('auth.verify_email') || 'Verify Your Email' }}</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">
                    We've sent a verification link to your email address. Please check your inbox and click the link to verify your account.
                </p>
            </div>

            <div class="card p-6 text-center">
                <p v-if="sent" class="text-green-500 mb-4 text-sm">A new verification link has been sent to your email.</p>

                <p class="text-sm mb-4" style="color: var(--color-text-muted);">
                    Didn't receive the email? Check your spam folder or request a new one.
                </p>

                <button
                    @click="resend"
                    :disabled="form.processing"
                    class="btn btn-primary w-full gap-2"
                >
                    <RefreshCw v-if="!form.processing" class="w-4 h-4" />
                    <span v-if="form.processing">{{ t('common.loading') || 'Sending...' }}</span>
                    <span v-else>{{ t('auth.resend_verification') || 'Resend Verification Email' }}</span>
                </button>

                <div class="mt-4">
                    <form @submit.prevent="$inertia.post('/logout')">
                        <button type="submit" class="text-sm hover:opacity-80" style="color: var(--color-text-secondary);">
                            {{ t('nav.logout') || 'Log Out' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
