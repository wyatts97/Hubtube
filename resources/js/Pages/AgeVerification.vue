<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ShieldAlert, Loader2 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const page = usePage();
const isSubmitting = ref(false);

// Set cookie using JavaScript (most reliable method)
const setCookie = (name, value, days) => {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
};

const confirm = () => {
    isSubmitting.value = true;
    
    // Set the cookie BEFORE redirecting
    setCookie('age_verified', 'true', 1); // 1 day
    
    // Small delay to ensure cookie is set, then redirect
    setTimeout(() => {
        window.location.href = '/';
    }, 100);
};

const decline = () => {
    window.location.href = '/age-verify/decline';
};
</script>

<template>
    <Head title="Age Verification" />

    <div class="min-h-screen flex items-center justify-center px-4" style="background-color: var(--color-bg-primary);">
        <div class="w-full max-w-lg text-center">
            <div class="mb-8">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" style="background-color: color-mix(in srgb, var(--color-accent) 20%, transparent);">
                    <ShieldAlert class="w-10 h-10" style="color: var(--color-accent);" />
                </div>
                <h1 class="text-3xl font-bold mb-4" style="color: var(--color-text-primary);">{{ t('age.title') || 'Age Verification Required' }}</h1>
                <p class="text-lg" style="color: var(--color-text-muted);">
                    This website contains age-restricted content. You must be at least 18 years old to enter.
                </p>
            </div>

            <div class="card p-8">
                <p class="mb-6" style="color: var(--color-text-secondary);">
                    By clicking "I am 18 or older", you confirm that you are at least 18 years of age and consent to viewing adult content.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <button 
                        @click="confirm"
                        :disabled="isSubmitting"
                        class="btn btn-primary flex-1 py-3 inline-flex items-center justify-center gap-2"
                    >
                        <Loader2 v-if="isSubmitting" class="w-5 h-5 animate-spin" />
                        {{ isSubmitting ? (t('common.loading') || 'Entering...') : (t('age.confirm') || 'I am 18 or older') }}
                    </button>
                    <button type="button" @click="decline" :disabled="isSubmitting" class="btn btn-secondary flex-1 py-3">
                        {{ t('age.exit') || 'Exit' }}
                    </button>
                </div>

                <p class="text-sm mt-6" style="color: var(--color-text-muted);">
                    By entering this site, you agree to our
                    <a href="/terms" style="color: var(--color-accent);" class="hover:opacity-80">Terms of Service</a>
                    and
                    <a href="/privacy" style="color: var(--color-accent);" class="hover:opacity-80">Privacy Policy</a>.
                </p>
            </div>
        </div>
    </div>
</template>
