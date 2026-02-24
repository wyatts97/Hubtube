<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ShieldAlert, Loader2 } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const page = usePage();
const ageSettings = computed(() => page.props.theme?.ageVerification || {});

const showModal = ref(false);
const isSubmitting = ref(false);

// Check if cookie exists
const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
};

// Set cookie
const setCookie = (name, value, days) => {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
};

onMounted(() => {
    // Check if age verification is required and not already verified
    const ageVerificationRequired = page.props.app?.age_verification_required;
    const isLoggedIn = !!page.props.auth?.user;
    const hasVerifiedCookie = getCookie('age_verified') === 'true';
    
    // Show modal if: verification required AND not logged in AND no cookie
    if (ageVerificationRequired && !isLoggedIn && !hasVerifiedCookie) {
        showModal.value = true;
    }
});

const confirm = () => {
    isSubmitting.value = true;
    setCookie('age_verified', 'true', 1); // 1 day
    
    setTimeout(() => {
        showModal.value = false;
        isSubmitting.value = false;

        // Re-initialize popunder/ad scripts after the age gate is dismissed.
        // ExoClick's popMagic + hosted popunder1000.js bind click handlers to
        // <a> tags at window.load time. If the age gate was showing, some links
        // may not have had handlers attached. Dispatch a synthetic load event
        // and also try calling preparePop directly so the script re-queries
        // all <a> tags now that the page is fully interactive.
        setTimeout(() => {
            // Try the hosted ExoClick object first
            if (typeof exoJsPop101 !== 'undefined' && exoJsPop101.add) {
                try { exoJsPop101.add(); } catch (e) { /* silent */ }
            }
            if (typeof popMagic !== 'undefined' && popMagic.preparePop) {
                try { popMagic.preparePop(); } catch (e) { /* silent */ }
            }
        }, 500);
    }, 300);
};

const decline = () => {
    window.location.href = 'https://www.google.com';
};

// Customization settings with defaults
const overlayColor = computed(() => ageSettings.value.overlayColor || 'rgba(0, 0, 0, 0.85)');
const overlayBlur = computed(() => ageSettings.value.overlayBlur || 8);
const headerText = computed(() => ageSettings.value.headerText || 'Age Verification Required');
const descriptionText = computed(() => ageSettings.value.descriptionText || 'This website contains age-restricted content. You must be at least 18 years old to enter.');
const confirmText = computed(() => ageSettings.value.confirmText || 'I am 18 or older');
const declineText = computed(() => ageSettings.value.declineText || 'Exit');
const disclaimerText = computed(() => ageSettings.value.disclaimerText || 'By clicking "{confirm}", you confirm that you are at least 18 years of age and consent to viewing adult content.');
const termsText = computed(() => ageSettings.value.termsText || 'By entering this site, you agree to our');
const showLogo = computed(() => ageSettings.value.showLogo !== false);
const logoUrl = computed(() => ageSettings.value.logoUrl || '');
const fontFamily = computed(() => ageSettings.value.fontFamily || 'inherit');
const headerSize = computed(() => ageSettings.value.headerSize || 28);
const headerColor = computed(() => ageSettings.value.headerColor || 'var(--color-text-primary)');
const textColor = computed(() => ageSettings.value.textColor || 'var(--color-text-secondary)');
const buttonColor = computed(() => ageSettings.value.buttonColor || 'var(--color-accent)');
</script>

<template>
    <Teleport to="body">
        <div 
            v-if="showModal"
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            :style="{
                backgroundColor: overlayColor,
                backdropFilter: `blur(${overlayBlur}px)`,
                fontFamily: fontFamily
            }"
        >
            <div 
                class="w-full max-w-lg text-center"
                @click.stop
            >
                <!-- Logo/Icon -->
                <div class="mb-8">
                    <template v-if="showLogo && logoUrl">
                        <img :src="logoUrl" alt="Site Logo" class="h-16 mx-auto mb-6" />
                    </template>
                    <template v-else>
                        <div 
                            class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6" 
                            :style="{ backgroundColor: `color-mix(in srgb, ${buttonColor} 20%, transparent)` }"
                        >
                            <ShieldAlert class="w-10 h-10" :style="{ color: buttonColor }" />
                        </div>
                    </template>
                    
                    <h1 
                        class="font-bold mb-4" 
                        :style="{ 
                            color: headerColor,
                            fontSize: headerSize + 'px'
                        }"
                    >
                        {{ headerText }}
                    </h1>
                    <p class="text-lg" :style="{ color: textColor }">
                        {{ descriptionText }}
                    </p>
                </div>

                <div 
                    class="rounded-xl p-8"
                    style="background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
                >
                    <p class="mb-6" :style="{ color: textColor }">
                        {{ disclaimerText.replace('{confirm}', confirmText) }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <button 
                            @click="confirm"
                            :disabled="isSubmitting"
                            class="flex-1 py-3 px-6 rounded-lg font-medium text-white inline-flex items-center justify-center gap-2 transition-opacity hover:opacity-90 disabled:opacity-50"
                            :style="{ backgroundColor: buttonColor }"
                        >
                            <Loader2 v-if="isSubmitting" class="w-5 h-5 animate-spin" />
                            {{ isSubmitting ? (t('common.loading') || 'Entering...') : confirmText }}
                        </button>
                        <button 
                            @click="decline" 
                            :disabled="isSubmitting" 
                            class="flex-1 py-3 px-6 rounded-lg font-medium transition-opacity hover:opacity-80"
                            style="background-color: var(--color-bg-secondary); color: var(--color-text-primary);"
                        >
                            {{ declineText }}
                        </button>
                    </div>

                    <p class="text-sm mt-6" :style="{ color: 'var(--color-text-muted)' }">
                        {{ termsText }}
                        <a href="/terms" :style="{ color: buttonColor }" class="hover:opacity-80">Terms of Service</a>
                        and
                        <a href="/privacy" :style="{ color: buttonColor }" class="hover:opacity-80">Privacy Policy</a>.
                    </p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
