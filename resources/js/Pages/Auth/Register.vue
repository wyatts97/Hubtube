<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
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
                <h1 class="text-2xl font-bold mt-4" style="color: var(--color-text-primary);">Create your account</h1>
                <p class="mt-2" style="color: var(--color-text-secondary);">Join the community today</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            Username
                        </label>
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            class="input"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.username" class="text-red-500 text-sm mt-1">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            Email
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="input"
                            required
                        />
                        <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            Password
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
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
                        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">
                            Confirm Password
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="input"
                            required
                        />
                    </div>

                    <div class="text-sm" style="color: var(--color-text-secondary);">
                        By signing up, you confirm that you are at least 18 years old and agree to our
                        <a href="/terms" style="color: var(--color-accent);">Terms of Service</a>
                        and
                        <a href="/privacy" style="color: var(--color-accent);">Privacy Policy</a>.
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn btn-primary w-full"
                    >
                        <span v-if="form.processing">Creating account...</span>
                        <span v-else>Create Account</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p style="color: var(--color-text-secondary);">
                        Already have an account?
                        <Link href="/login" class="font-medium" style="color: var(--color-accent);">
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
