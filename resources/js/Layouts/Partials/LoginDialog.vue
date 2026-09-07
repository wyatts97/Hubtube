<script setup>
/**
 * Inline sign-in dialog, so a visitor who hits a gated action doesn't lose the
 * page they were on.
 */
import { computed, nextTick, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, X } from 'lucide-vue-next';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import { useI18n } from '@/Composables/useI18n';
import { useTheme } from '@/Composables/useTheme';

const open = defineModel({ type: Boolean, default: false });

const page = usePage();
const { t } = useI18n();

const themeSettings = computed(() => page.props.theme || {});

const { isDark } = useTheme();

/** Same dark-variant fallback as the header. */
const logoUrl = computed(() => (isDark.value && themeSettings.value.site_logo_dark)
    ? themeSettings.value.site_logo_dark
    : themeSettings.value.site_logo);
const user = computed(() => page.props.auth?.user);

// Guard kept from the original markup: a session that becomes authenticated
// must never leave the dialog mounted.
const dialogOpen = computed({
    get: () => open.value && !user.value,
    set: (value) => { open.value = value; },
});

const showPassword = ref(false);
const loginFieldRef = ref(null);

// Reka focuses the first tabbable element (the close button); move focus to the
// login field instead, matching the `autofocus` the old markup carried.
const focusLoginField = (event) => {
    event.preventDefault();
    nextTick(() => loginFieldRef.value?.focus());
};

const form = useForm({ login: '', password: '', remember: false });

const submit = () => {
    form.post('/login', {
        onSuccess: () => {
            open.value = false;
            showPassword.value = false;
            form.reset();
        },
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <BaseDialog
        v-model="dialogOpen"
        aria-label="Sign in"
        transition-name="login-modal"
        @open-auto-focus="focusLoginField"
    >
        <button
            class="absolute top-3 end-3 p-1 text-text-muted hover:text-text-primary"
            :aria-label="t('common.close')"
            @click="dialogOpen = false"
        >
            <X class="w-5 h-5" />
        </button>

        <div class="text-center mb-6">
            <Link href="/" class="inline-block">
                <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-12 w-auto mx-auto object-contain" />
                <div
                    v-else
                    class="w-12 h-12 flex items-center justify-center mx-auto bg-accent"
                    :style="{ borderRadius: 'var(--radius-card)' }"
                >
                    <span class="font-display text-2xl font-bold text-accent-contrast">
                        {{ (themeSettings.siteTitle || 'H').charAt(0).toUpperCase() }}
                    </span>
                </div>
            </Link>
            <h2 class="text-xl font-bold mt-3 text-text-primary">{{ t('auth.welcome_back') }}</h2>
            <p class="text-sm mt-1 text-text-secondary">{{ t('auth.sign_in_desc') }}</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="login-dialog-login" class="block text-sm font-medium mb-1 text-text-secondary">
                    {{ t('auth.email_or_username') }}
                </label>
                <input id="login-dialog-login" ref="loginFieldRef" v-model="form.login" type="text" class="input" required />
                <p v-if="form.errors.login" class="text-accent-text text-sm mt-1">{{ form.errors.login }}</p>
            </div>

            <div>
                <label for="login-dialog-password" class="block text-sm font-medium mb-1 text-text-secondary">
                    {{ t('auth.password') }}
                </label>
                <div class="relative">
                    <input
                        id="login-dialog-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="input pe-10"
                        required
                    />
                    <button
                        type="button"
                        class="absolute end-3 top-1/2 -translate-y-1/2 text-text-secondary"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="w-5 h-5" />
                        <Eye v-else class="w-5 h-5" />
                    </button>
                </div>
                <p v-if="form.errors.password" class="text-accent-text text-sm mt-1">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.remember" type="checkbox" class="w-4 h-4" />
                    <span class="text-sm text-text-secondary">{{ t('auth.remember_me') }}</span>
                </label>
                <Link href="/forgot-password" class="text-sm text-accent-text hover:underline" @click="dialogOpen = false">
                    {{ t('auth.forgot_password') }}
                </Link>
            </div>

            <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                {{ form.processing ? t('auth.signing_in') : t('auth.login') }}
            </button>
        </form>

        <p class="mt-6 text-center text-text-secondary">
            {{ t('auth.no_account') }}
            <Link href="/register" class="font-semibold text-accent-text hover:underline" @click="dialogOpen = false">
                {{ t('auth.sign_up') }}
            </Link>
        </p>
    </BaseDialog>
</template>
