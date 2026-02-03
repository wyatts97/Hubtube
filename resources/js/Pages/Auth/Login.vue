<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

const showPassword = ref(false);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-screen flex items-center justify-center bg-dark-950 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 bg-primary-600 rounded-xl flex items-center justify-center">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold text-white mt-4">Welcome back</h1>
                <p class="text-dark-400 mt-2">Sign in to your account</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="login" class="block text-sm font-medium text-dark-300 mb-1">
                            Email or Username
                        </label>
                        <input
                            id="login"
                            v-model="form.login"
                            type="text"
                            class="input"
                            required
                            autofocus
                        />
                        <p v-if="form.errors.login" class="text-red-500 text-sm mt-1">{{ form.errors.login }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-dark-300 mb-1">
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
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-dark-400 hover:text-dark-300"
                            >
                                <EyeOff v-if="showPassword" class="w-5 h-5" />
                                <Eye v-else class="w-5 h-5" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-dark-600 bg-dark-800 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="text-sm text-dark-400">Remember me</span>
                        </label>
                        <Link href="/forgot-password" class="text-sm text-primary-500 hover:text-primary-400">
                            Forgot password?
                        </Link>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="btn btn-primary w-full"
                    >
                        <span v-if="form.processing">Signing in...</span>
                        <span v-else>Sign In</span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-dark-400">
                        Don't have an account?
                        <Link href="/register" class="text-primary-500 hover:text-primary-400 font-medium">
                            Sign up
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
