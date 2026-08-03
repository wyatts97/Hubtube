<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ShieldCheck } from 'lucide-vue-next';

const useRecoveryCode = ref(false);

const form = useForm({
    code: '',
});

const label = computed(() => useRecoveryCode.value ? 'Recovery Code' : 'Authentication Code');
const placeholder = computed(() => useRecoveryCode.value ? 'xxxxxxxxxx-xxxxxxxxxx' : '123456');

const onSubmit = () => {
    form.post('/two-factor-challenge', {
        onFinish: () => form.reset('code'),
    });
};

const toggleMode = () => {
    useRecoveryCode.value = !useRecoveryCode.value;
    form.reset('code');
    form.clearErrors();
};
</script>

<template>
    <Head title="Two-Factor Authentication" />

    <div class="min-h-screen flex items-center justify-center px-4 bg-bg-primary">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto bg-accent">
                    <ShieldCheck class="w-6 h-6 text-white" />
                </div>
                <h1 class="text-2xl font-bold mt-4 text-text-primary">Two-Factor Authentication</h1>
                <p class="mt-2 text-text-secondary">
                    {{ useRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.' }}
                </p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="onSubmit" class="space-y-4">
                    <div>
                        <label for="code" class="block text-sm font-medium mb-1 text-text-secondary">{{ label }}</label>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="input text-center tracking-widest"
                            :placeholder="placeholder"
                            autofocus
                            autocomplete="one-time-code"
                            required
                        />
                        <p v-if="form.errors.code" class="text-red-500 text-sm mt-1">{{ form.errors.code }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                        <span v-if="form.processing">Verifying...</span>
                        <span v-else>Verify</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <button type="button" @click="toggleMode" class="text-sm text-accent">
                        {{ useRecoveryCode ? 'Use an authentication code instead' : 'Use a recovery code instead' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
