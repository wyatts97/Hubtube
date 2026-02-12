<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { z } from 'zod';
import { ref } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import { useFormValidation } from '@/Composables/useFormValidation';

const { t } = useI18n();

const schema = z.object({
    email: z.string().email('Enter a valid email address.'),
});

const sent = ref(false);
const { defineField, errors, submit, isSubmitting } = useFormValidation(schema, {
    email: '',
});

const [email, emailAttrs] = defineField('email');
const onSubmit = submit('post', '/forgot-password', {
    onSuccess: () => {
        sent.value = true;
    },
});
</script>

<template>
    <Head title="Forgot Password" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: var(--color-accent);">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">{{ t('auth.forgot_password') || 'Forgot Password' }}</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">{{ t('auth.forgot_password_desc') || "Enter your email and we'll send you a reset link" }}</p>
            </div>

            <div class="card p-6">
                <div v-if="sent" class="mb-4 p-3 rounded-lg text-sm text-green-400" style="background-color: rgba(34,197,94,0.1);">
                    A password reset link has been sent to your email.
                </div>

                <form @submit.prevent="onSubmit" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            {{ t('settings.email') || 'Email Address' }}
                        </label>
                        <input
                            id="email"
                            v-model="email"
                            v-bind="emailAttrs"
                            type="email"
                            class="input"
                            required
                            autofocus
                        />
                        <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
                    </div>

                    <button type="submit" :disabled="isSubmitting" class="btn btn-primary w-full">
                        <span v-if="isSubmitting">{{ t('common.loading') || 'Sending...' }}</span>
                        <span v-else>{{ t('auth.send_reset_link') || 'Send Reset Link' }}</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <Link href="/login" class="flex items-center justify-center gap-2 text-sm" style="color: var(--color-text-secondary);">
                        <ArrowLeft class="w-4 h-4" />
                        {{ t('auth.back_to_login') || 'Back to Sign In' }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
