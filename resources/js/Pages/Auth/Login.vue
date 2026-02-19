<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { z } from 'zod';
import { useI18n } from '@/Composables/useI18n';
import { useFormValidation } from '@/Composables/useFormValidation';

const { t } = useI18n();
const page = usePage();

const showPassword = ref(false);
const socialProviders = computed(() => page.props.socialLogin || []);
const siteLogo = computed(() => page.props.theme?.site_logo || '');
const siteTitle = computed(() => page.props.theme?.siteTitle || 'H');

const schema = z.object({
    login: z.string().min(1, 'Email or username is required.'),
    password: z.string().min(6, 'Password must be at least 6 characters.'),
    remember: z.boolean().optional(),
});

const { defineField, errors, submit, setFieldValue, isSubmitting } = useFormValidation(schema, {
    login: '',
    password: '',
    remember: false,
});

const [login, loginAttrs] = defineField('login');
const [password, passwordAttrs] = defineField('password');
const [remember, rememberAttrs] = defineField('remember');

const onSubmit = submit('post', '/login', {
    onFinish: () => setFieldValue('password', ''),
});

const providerMeta = {
    google: {
        label: 'Google',
        color: '#4285F4',
        icon: `<svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>`,
    },
    twitter: {
        label: 'X',
        color: '#000000',
        icon: `<svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>`,
    },
    reddit: {
        label: 'Reddit',
        color: '#FF4500',
        icon: `<svg viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>`,
    },
};
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center justify-center">
                    <img v-if="siteLogo" :src="siteLogo" alt="Logo" class="h-12 w-auto object-contain" />
                    <div v-else class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: var(--color-accent);">
                        <span class="text-2xl font-bold text-white">{{ siteTitle.charAt(0).toUpperCase() }}</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">{{ t('auth.welcome_back') || 'Welcome back' }}</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">{{ t('auth.sign_in_desc') || 'Sign in to your account' }}</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="onSubmit" class="space-y-4">
                    <div>
                        <label for="login" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            {{ t('auth.email_or_username') || 'Email or Username' }}
                        </label>
                        <input
                            id="login"
                            v-model="login"
                            v-bind="loginAttrs"
                            type="text"
                            class="input"
                            required
                            autofocus
                        />
                        <p v-if="errors.login" class="text-red-500 text-sm mt-1">{{ errors.login }}</p>
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

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="remember"
                                v-bind="rememberAttrs"
                                type="checkbox"
                                class="w-4 h-4 rounded border-dark-600 bg-dark-800 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="text-sm" style="color: var(--color-text-secondary);">{{ t('auth.remember_me') || 'Remember me' }}</span>
                        </label>
                        <Link href="/forgot-password" class="text-sm" style="color: var(--color-accent);">
                            {{ t('auth.forgot_password') || 'Forgot password?' }}
                        </Link>
                    </div>

                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="btn btn-primary w-full"
                    >
                        <span v-if="isSubmitting">{{ t('auth.signing_in') || 'Signing in...' }}</span>
                        <span v-else>{{ t('auth.login') || 'Sign In' }}</span>
                    </button>
                </form>

                <!-- Social Login -->
                <template v-if="socialProviders.length > 0">
                    <div class="mt-6 flex items-center gap-3">
                        <div class="flex-1 h-px" style="background-color: var(--color-border);"></div>
                        <span class="text-sm shrink-0" style="color: var(--color-text-muted);">{{ t('auth.or') || 'or' }}</span>
                        <div class="flex-1 h-px" style="background-color: var(--color-border);"></div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3">
                        <a
                            v-for="provider in socialProviders"
                            :key="provider"
                            :href="`/auth/${provider}/redirect`"
                            class="flex items-center justify-center gap-3 w-full rounded-lg px-4 py-2.5 text-sm font-medium transition-colors border"
                            style="border-color: var(--color-border); color: var(--color-text-primary); background-color: var(--color-bg-secondary);"
                            @mouseenter="$event.target.style.backgroundColor = 'var(--color-bg-card)'"
                            @mouseleave="$event.target.style.backgroundColor = 'var(--color-bg-secondary)'"
                        >
                            <span v-html="providerMeta[provider]?.icon" class="shrink-0"></span>
                            <span>{{ t('auth.continue_with', { provider: providerMeta[provider]?.label }) || 'Continue with ' + providerMeta[provider]?.label }}</span>
                        </a>
                    </div>
                </template>

                <div class="mt-6 text-center">
                    <p style="color: var(--color-text-secondary);">
                        {{ t('auth.no_account') || "Don't have an account?" }}
                        <Link href="/register" class="font-medium" style="color: var(--color-accent);">
                            {{ t('auth.sign_up') || 'Sign up' }}
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
