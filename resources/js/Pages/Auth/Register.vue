<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import { z } from 'zod';
import { useI18n } from '@/Composables/useI18n';
import { useFormValidation } from '@/Composables/useFormValidation';

const { t } = useI18n();

const showPassword = ref(false);

const schema = z.object({
    username: z.string().min(3, 'Username must be at least 3 characters.').max(32, 'Username must be 32 characters or less.'),
    email: z.string().email('Enter a valid email address.'),
    password: z.string().min(8, 'Password must be at least 8 characters.'),
    password_confirmation: z.string().min(8, 'Confirm your password.'),
}).refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
});

const { defineField, errors, submit, setFieldValue, isSubmitting } = useFormValidation(schema, {
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const [username, usernameAttrs] = defineField('username');
const [email, emailAttrs] = defineField('email');
const [password, passwordAttrs] = defineField('password');
const [passwordConfirmation, passwordConfirmationAttrs] = defineField('password_confirmation');

const onSubmit = submit('post', '/register', {
    onFinish: () => {
        setFieldValue('password', '');
        setFieldValue('password_confirmation', '');
    },
});
</script>

<template>
    <Head title="Sign Up" />

    <div class="min-h-screen flex items-center justify-center px-4 py-8" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: var(--color-accent);">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">{{ t('auth.create_account') || 'Create your account' }}</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">{{ t('auth.join_community') || 'Join the community today' }}</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="onSubmit" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            {{ t('settings.username') || 'Username' }}
                        </label>
                        <input
                            id="username"
                            v-model="username"
                            v-bind="usernameAttrs"
                            type="text"
                            class="input"
                            required
                            autofocus
                        />
                        <p v-if="errors.username" class="text-red-500 text-sm mt-1">{{ errors.username }}</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            {{ t('settings.email') || 'Email' }}
                        </label>
                        <input
                            id="email"
                            v-model="email"
                            v-bind="emailAttrs"
                            type="email"
                            class="input"
                            required
                        />
                        <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            {{ t('auth.password') || 'Password' }}
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="password"
                                v-bind="passwordAttrs"
                                :type="showPassword ? 'text' : 'password'"
                                class="input pr-10"
                                required
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-secondary);"
                            >
                                <EyeOff v-if="showPassword" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="errors.password" class="text-red-500 text-sm mt-1">{{ errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            {{ t('settings.confirm_password') || 'Confirm Password' }}
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="passwordConfirmation"
                            v-bind="passwordConfirmationAttrs"
                            type="password"
                            class="input"
                            required
                        />
                        <p v-if="errors.password_confirmation" class="text-red-500 text-sm mt-1">{{ errors.password_confirmation }}</p>
                    </div>

                    <div class="text-sm" style="color: var(--color-text-secondary);">
                        By signing up, you confirm that you are at least 18 years old and agree to our
                        <a href="/terms" style="color: var(--color-accent);">Terms of Service</a>
                        and
                        <a href="/privacy" style="color: var(--color-accent);">Privacy Policy</a>.
                    </div>

                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="btn btn-primary w-full"
                    >
                        <span v-if="isSubmitting">{{ t('auth.creating_account') || 'Creating account...' }}</span>
                        <span v-else>{{ t('auth.create_account') || 'Create Account' }}</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p style="color: var(--color-text-secondary);">
                        {{ t('auth.has_account') || 'Already have an account?' }}
                        <Link href="/login" class="font-medium" style="color: var(--color-accent);">
                            {{ t('auth.login') || 'Sign in' }}
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
