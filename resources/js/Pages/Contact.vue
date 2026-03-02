<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';
import { Send, CheckCircle, Mail, User, MessageSquare } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const page = usePage();
const success = computed(() => page.props.flash?.success);

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

const onSubmit = () => {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="t('contact.title') || 'Contact Us'" />

    <AppLayout>
        <div class="max-w-2xl mx-auto py-8">
            <h1 class="text-2xl font-bold mb-2" style="color: var(--color-text-primary);">{{ t('contact.title') || 'Contact Us' }}</h1>
            <p class="mb-8" style="color: var(--color-text-secondary);">
                Have a question, concern, or feedback? Send us a message and we'll get back to you.
            </p>

            <!-- Success Message -->
            <div
                v-if="success"
                class="mb-6 p-4 rounded-lg flex items-center gap-3"
                style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);"
            >
                <CheckCircle class="w-5 h-5 text-green-500 shrink-0" />
                <p class="text-green-400 text-sm">{{ success }}</p>
            </div>

            <form @submit.prevent="onSubmit" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                            {{ t('contact.name') || 'Name' }} <span style="color: var(--color-accent);">*</span>
                        </label>
                        <div class="relative">
                            <User class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--color-text-muted);" />
                            <input
                                v-model="form.name"
                                type="text"
                                class="input pl-14 w-full"
                                placeholder="Your name"
                                required
                            />
                        </div>
                        <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                            {{ t('contact.email') || 'Email' }} <span style="color: var(--color-accent);">*</span>
                        </label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--color-text-muted);" />
                            <input
                                v-model="form.email"
                                type="email"
                                class="input pl-14 w-full"
                                placeholder="your@email.com"
                                required
                            />
                        </div>
                        <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
                    </div>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                        {{ t('contact.subject') || 'Subject' }}
                    </label>
                    <input
                        v-model="form.subject"
                        type="text"
                        class="input w-full"
                        placeholder="What is this about?"
                    />
                    <p v-if="form.errors.subject" class="text-red-400 text-xs mt-1">{{ form.errors.subject }}</p>
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                        {{ t('contact.message') || 'Message' }} <span style="color: var(--color-accent);">*</span>
                    </label>
                    <div class="relative">
                        <MessageSquare class="absolute left-3 top-3 w-4 h-4" style="color: var(--color-text-muted);" />
                        <textarea
                            v-model="form.message"
                            class="input pl-10 w-full"
                            rows="6"
                            placeholder="Your message..."
                            required
                        ></textarea>
                    </div>
                    <p v-if="form.errors.message" class="text-red-400 text-xs mt-1">{{ form.errors.message }}</p>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary inline-flex items-center gap-2"
                    :disabled="form.processing"
                >
                    <Send class="w-4 h-4" />
                    {{ form.processing ? (t('common.loading') || 'Sending...') : (t('contact.send') || 'Send Message') }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
