<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

const { t } = useI18n();

const sent = ref(false);

const form = useForm({
    email: '',
});

const onSubmit = () => {
    form.post('/forgot-password', {
        onSuccess: () => {
            sent.value = true;
        },
    });
};
</script>

<template>
    <SeoHead title="Forgot Password" />

    <div class="min-h-screen flex items-center justify-center px-4 bg-bg-primary">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-accent">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold mt-4 text-text-primary">{{ t('auth.forgot_password') }}</h1>
                <p class="mt-2 text-text-secondary">{{ t('auth.forgot_password_desc') }}</p>
            </div>

            <div class="card p-6">
                <div v-if="sent" class="mb-4 p-3 rounded-lg text-sm text-green-400" style="background-color: rgba(34,197,94,0.1);">
                    A password reset link has been sent to your email.
                </div>

                <form @submit.prevent="onSubmit" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1 text-text-secondary">
                            {{ t('settings.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="input"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                        <span v-if="form.processing">{{ t('common.loading') }}</span>
                        <span v-else>{{ t('auth.send_reset_link') }}</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <Link href="/login" class="flex items-center justify-center gap-2 text-sm text-text-secondary">
                        <ArrowLeft class="w-4 h-4" />
                        {{ t('auth.back_to_login') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
