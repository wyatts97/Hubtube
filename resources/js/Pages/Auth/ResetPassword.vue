<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import { z } from 'zod';
import { useI18n } from '@/Composables/useI18n';
import { useFormValidation } from '@/Composables/useFormValidation';

const { t } = useI18n();

const props = defineProps({
    email: String,
    token: String,
});

const showPassword = ref(false);
const showConfirm = ref(false);

const schema = z.object({
    token: z.string().min(1),
    email: z.string().email('Enter a valid email address.'),
    password: z.string().min(8, 'Password must be at least 8 characters.'),
    password_confirmation: z.string().min(8, 'Confirm your password.'),
}).refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
});

const { defineField, errors, submit, setFieldValue, isSubmitting } = useFormValidation(schema, {
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const [token, tokenAttrs] = defineField('token');
const [email, emailAttrs] = defineField('email');
const [password, passwordAttrs] = defineField('password');
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation');

const onSubmit = submit('post', '/reset-password', {
    onFinish: () => {
        setFieldValue('password', '');
        setFieldValue('password_confirmation', '');
    },
});
</script>

<template>
    <Head title="Reset Password" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: var(--color-accent);">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">{{ t('auth.reset_password') || 'Reset Password' }}</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">{{ t('auth.reset_password_desc') || 'Enter your new password' }}</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="onSubmit" class="space-y-4">
                    <input type="hidden" v-model="token" v-bind="tokenAttrs" />
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('settings.email') || 'Email' }}</label>
                        <input id="email" v-model="email" v-bind="emailAttrs" type="email" class="input" required />
                        <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('settings.new_password') || 'New Password' }}</label>
                        <div class="relative">
                            <input id="password" v-model="password" v-bind="passwordAttrs" :type="showPassword ? 'text' : 'password'" class="input pr-10" required />
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-secondary);">
                                <EyeOff v-if="showPassword" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="errors.password" class="text-red-500 text-sm mt-1">{{ errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">{{ t('settings.confirm_password') || 'Confirm Password' }}</label>
                        <div class="relative">
                            <input id="password_confirmation" v-model="passwordConfirmation" v-bind="passwordConfirmationAttrs" :type="showConfirm ? 'text' : 'password'" class="input pr-10" required />
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-secondary);">
                                <EyeOff v-if="showConfirm" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="errors.password_confirmation" class="text-red-500 text-sm mt-1">{{ errors.password_confirmation }}</p>
                    </div>

                    <button type="submit" :disabled="isSubmitting" class="btn btn-primary w-full">
                        <span v-if="isSubmitting">{{ t('common.loading') || 'Resetting...' }}</span>
                        <span v-else>{{ t('auth.reset_password') || 'Reset Password' }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
