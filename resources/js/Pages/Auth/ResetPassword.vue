<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

const { t } = useI18n();

const props = defineProps({
    email: String,
    token: String,
});

const showPassword = ref(false);
const showConfirm = ref(false);

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const onSubmit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <SeoHead title="Reset Password" />

    <div class="min-h-screen flex items-center justify-center px-4 bg-bg-primary">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-accent">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold mt-4 text-text-primary">{{ t('auth.reset_password') }}</h1>
                <p class="mt-2 text-text-secondary">{{ t('auth.reset_password_desc') }}</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="onSubmit" class="space-y-4">
                    <input type="hidden" v-model="form.token" :aria-invalid="!!form.errors.token" :aria-describedby="form.errors.token ? 'token-error' : undefined" />
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.email') }}</label>
                        <input id="email" v-model="form.email" type="email" class="input" required autocomplete="email" :aria-invalid="!!form.errors.email" :aria-describedby="form.errors.email ? 'email-error' : undefined" />
                        <p v-if="form.errors.email" id="email-error" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.new_password') }}</label>
                        <div class="relative">
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pe-10" required autocomplete="new-password" :aria-invalid="!!form.errors.password" :aria-describedby="form.errors.password ? 'password-error' : undefined" />
                            <button type="button" @click="showPassword = !showPassword" class="absolute end-3 top-1/2 -translate-y-1/2 text-text-secondary" :aria-label="showPassword ? t('auth.hide_password') : t('auth.show_password')" :aria-pressed="showPassword">
                                <EyeOff v-if="showPassword" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" id="password-error" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.confirm_password') }}</label>
                        <div class="relative">
                            <input id="password_confirmation" v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" class="input pe-10" required autocomplete="new-password" :aria-invalid="!!form.errors.password_confirmation" :aria-describedby="form.errors.password_confirmation ? 'password_confirmation-error' : undefined" />
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute end-3 top-1/2 -translate-y-1/2 text-text-secondary" :aria-label="showConfirm ? t('auth.hide_password') : t('auth.show_password')" :aria-pressed="showConfirm">
                                <EyeOff v-if="showConfirm" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="form.errors.password_confirmation" id="password_confirmation-error" class="text-red-500 text-sm mt-1">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                        <span v-if="form.processing">{{ t('common.loading') }}</span>
                        <span v-else>{{ t('auth.reset_password') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
