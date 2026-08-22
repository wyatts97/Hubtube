<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';
import { Send, CheckCircle, Mail, User, Building2, FileText, Link as LinkIcon, PenLine } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';

const { t } = useI18n();

const page = usePage();
const success = computed(() => page.props.flash?.success);

const form = useForm({
    complainant_name: '',
    complainant_email: '',
    complainant_company: '',
    copyrighted_work_description: '',
    infringing_urls: '',
    good_faith_statement: false,
    accuracy_statement: false,
    signature: '',
});

const onSubmit = () => {
    form.post('/dmca-request', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <SeoHead :title="t('dmca.title') || 'DMCA Takedown Request'" />

    <AppLayout>
        <div class="max-w-2xl mx-auto py-8">
            <h1 class="text-2xl font-bold mb-2 text-text-primary">{{ t('dmca.title') || 'DMCA Takedown Request' }}</h1>
            <p class="mb-8 text-text-secondary">
                If you believe content on this site infringes your copyright, submit a takedown request below. See our
                <a href="/pages/dmca" class="text-accent hover:opacity-80">DMCA policy</a> for details on the process.
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
                        <label class="block text-sm font-medium mb-1.5 text-text-secondary">
                            {{ t('dmca.name') || 'Full Name' }} <span class="text-accent">*</span>
                        </label>
                        <div class="relative">
                            <User class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" />
                            <input v-model="form.complainant_name" type="text" class="input pl-14 w-full" placeholder="Your full name" required />
                        </div>
                        <p v-if="form.errors.complainant_name" class="text-red-400 text-xs mt-1">{{ form.errors.complainant_name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium mb-1.5 text-text-secondary">
                            {{ t('dmca.email') || 'Email' }} <span class="text-accent">*</span>
                        </label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" />
                            <input v-model="form.complainant_email" type="email" class="input pl-14 w-full" placeholder="your@email.com" required />
                        </div>
                        <p v-if="form.errors.complainant_email" class="text-red-400 text-xs mt-1">{{ form.errors.complainant_email }}</p>
                    </div>
                </div>

                <!-- Company -->
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-text-secondary">
                        {{ t('dmca.company') || 'Company / Agency (optional)' }}
                    </label>
                    <div class="relative">
                        <Building2 class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" />
                        <input v-model="form.complainant_company" type="text" class="input pl-14 w-full" placeholder="Representing a company or agency?" />
                    </div>
                </div>

                <!-- Copyrighted work description -->
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-text-secondary">
                        {{ t('dmca.work_description') || 'Description of the copyrighted work' }} <span class="text-accent">*</span>
                    </label>
                    <div class="relative">
                        <FileText class="absolute left-3 top-3 w-4 h-4 text-text-muted" />
                        <textarea
                            v-model="form.copyrighted_work_description"
                            class="input pl-10 w-full"
                            rows="4"
                            placeholder="Describe the copyrighted work you believe is being infringed..."
                            required
                        ></textarea>
                    </div>
                    <p v-if="form.errors.copyrighted_work_description" class="text-red-400 text-xs mt-1">{{ form.errors.copyrighted_work_description }}</p>
                </div>

                <!-- Infringing URLs -->
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-text-secondary">
                        {{ t('dmca.infringing_urls') || 'URL(s) of the infringing content' }} <span class="text-accent">*</span>
                    </label>
                    <div class="relative">
                        <LinkIcon class="absolute left-3 top-3 w-4 h-4 text-text-muted" />
                        <textarea
                            v-model="form.infringing_urls"
                            class="input pl-10 w-full"
                            rows="3"
                            placeholder="One URL per line"
                            required
                        ></textarea>
                    </div>
                    <p v-if="form.errors.infringing_urls" class="text-red-400 text-xs mt-1">{{ form.errors.infringing_urls }}</p>
                </div>

                <!-- Statements -->
                <div class="space-y-3 p-4 rounded-lg border border-border">
                    <label class="flex items-start gap-3 text-sm text-text-secondary cursor-pointer">
                        <input v-model="form.good_faith_statement" type="checkbox" class="mt-0.5" required />
                        <span>{{ t('dmca.good_faith') || 'I have a good faith belief that use of the copyrighted material described above is not authorized by the copyright owner, its agent, or the law.' }}</span>
                    </label>
                    <p v-if="form.errors.good_faith_statement" class="text-red-400 text-xs">{{ form.errors.good_faith_statement }}</p>

                    <label class="flex items-start gap-3 text-sm text-text-secondary cursor-pointer">
                        <input v-model="form.accuracy_statement" type="checkbox" class="mt-0.5" required />
                        <span>{{ t('dmca.accuracy') || 'I swear, under penalty of perjury, that the information in this notification is accurate and that I am the copyright owner or authorized to act on the copyright owner\'s behalf.' }}</span>
                    </label>
                    <p v-if="form.errors.accuracy_statement" class="text-red-400 text-xs">{{ form.errors.accuracy_statement }}</p>
                </div>

                <!-- Signature -->
                <div>
                    <label class="block text-sm font-medium mb-1.5 text-text-secondary">
                        {{ t('dmca.signature') || 'Electronic Signature (type your full legal name)' }} <span class="text-accent">*</span>
                    </label>
                    <div class="relative">
                        <PenLine class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" />
                        <input v-model="form.signature" type="text" class="input pl-14 w-full" placeholder="Full legal name" required />
                    </div>
                    <p v-if="form.errors.signature" class="text-red-400 text-xs mt-1">{{ form.errors.signature }}</p>
                </div>

                <button type="submit" class="btn btn-primary inline-flex items-center gap-2" :disabled="form.processing">
                    <Send class="w-4 h-4" />
                    {{ form.processing ? (t('common.loading') || 'Submitting...') : (t('dmca.submit') || 'Submit Request') }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
