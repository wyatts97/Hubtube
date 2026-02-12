<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { z } from 'zod';
import { Send, CheckCircle, Mail, User, MessageSquare } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import { useFormValidation } from '@/Composables/useFormValidation';

const { t } = useI18n();

const page = usePage();
const success = computed(() => page.props.flash?.success);

const schema = z.object({
    name: z.string().min(2, 'Name is required.').max(80, 'Name must be 80 characters or less.'),
    email: z.string().email('Enter a valid email address.'),
    subject: z.string().max(120, 'Subject must be 120 characters or less.').optional().or(z.literal('')),
    message: z.string().min(10, 'Message must be at least 10 characters.').max(2000, 'Message must be 2000 characters or less.'),
});

const { defineField, errors, submit, resetForm, isSubmitting } = useFormValidation(schema, {
    name: '',
    email: '',
    subject: '',
    message: '',
});

const [name, nameAttrs] = defineField('name');
const [email, emailAttrs] = defineField('email');
const [subject, subjectAttrs] = defineField('subject');
const [message, messageAttrs] = defineField('message');

const onSubmit = submit('post', '/contact', {
    preserveScroll: true,
    onSuccess: () => resetForm(),
});
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
                                v-model="name"
                                v-bind="nameAttrs"
                                type="text"
                                class="input pl-14 w-full"
                                placeholder="Your name"
                                required
                            />
                        </div>
                        <p v-if="errors.name" class="text-red-400 text-xs mt-1">{{ errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                            {{ t('contact.email') || 'Email' }} <span style="color: var(--color-accent);">*</span>
                        </label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--color-text-muted);" />
                            <input
                                v-model="email"
                                v-bind="emailAttrs"
                                type="email"
                                class="input pl-14 w-full"
                                placeholder="your@email.com"
                                required
                            />
                        </div>
                        <p v-if="errors.email" class="text-red-400 text-xs mt-1">{{ errors.email }}</p>
                    </div>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                        {{ t('contact.subject') || 'Subject' }}
                    </label>
                    <input
                        v-model="subject"
                        v-bind="subjectAttrs"
                        type="text"
                        class="input w-full"
                        placeholder="What is this about?"
                    />
                    <p v-if="errors.subject" class="text-red-400 text-xs mt-1">{{ errors.subject }}</p>
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                        {{ t('contact.message') || 'Message' }} <span style="color: var(--color-accent);">*</span>
                    </label>
                    <div class="relative">
                        <MessageSquare class="absolute left-3 top-3 w-4 h-4" style="color: var(--color-text-muted);" />
                        <textarea
                            v-model="message"
                            v-bind="messageAttrs"
                            class="input pl-10 w-full"
                            rows="6"
                            placeholder="Your message..."
                            required
                        ></textarea>
                    </div>
                    <p v-if="errors.message" class="text-red-400 text-xs mt-1">{{ errors.message }}</p>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary inline-flex items-center gap-2"
                    :disabled="isSubmitting"
                >
                    <Send class="w-4 h-4" />
                    {{ isSubmitting ? (t('common.loading') || 'Sending...') : (t('contact.send') || 'Send Message') }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
