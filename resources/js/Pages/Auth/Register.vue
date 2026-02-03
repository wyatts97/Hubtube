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

    <div class="min-h-screen flex items-center justify-center bg-dark-950 px-4 py-8">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 bg-primary-600 rounded-xl flex items-center justify-center">
                        <span class="text-2xl font-bold text-white">H</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-bold text-white mt-4">Create your account</h1>
                <p class="text-dark-400 mt-2">Join the community today</p>
            </div>

            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-dark-300 mb-1">
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
                        <label for="email" class="block text-sm font-medium text-dark-300 mb-1">
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

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-dark-300 mb-1">
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

                    <div class="text-sm text-dark-400">
                        By signing up, you confirm that you are at least 18 years old and agree to our
                        <a href="/terms" class="text-primary-500 hover:text-primary-400">Terms of Service</a>
                        and
                        <a href="/privacy" class="text-primary-500 hover:text-primary-400">Privacy Policy</a>.
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
                    <p class="text-dark-400">
                        Already have an account?
                        <Link href="/login" class="text-primary-500 hover:text-primary-400 font-medium">
                            Sign in
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
