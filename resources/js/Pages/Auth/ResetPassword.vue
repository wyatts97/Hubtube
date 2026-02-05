<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

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

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
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
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">Reset Password</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">Enter your new password</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Email</label>
                        <input id="email" v-model="form.email" type="email" class="input" required />
                        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">New Password</label>
                        <div class="relative">
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input pr-10" required />
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-secondary);">
                                <EyeOff v-if="showPassword" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Confirm Password</label>
                        <div class="relative">
                            <input id="password_confirmation" v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" class="input pr-10" required />
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-secondary);">
                                <EyeOff v-if="showConfirm" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full">
                        <span v-if="form.processing">Resetting...</span>
                        <span v-else>Reset Password</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
