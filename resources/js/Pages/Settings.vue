<script setup>
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { User, Lock, Bell, Shield, Wallet, ExternalLink, Loader2, Camera, ImageIcon, Trash2, AlertTriangle, Download, ShieldCheck, KeyRound } from 'lucide-vue-next';
import { usePushNotifications } from '@/Composables/usePushNotifications';
import { useI18n } from '@/Composables/useI18n';
import SeoHead from '@/Components/SeoHead.vue';
import BaseDialog from '@/Components/UI/BaseDialog.vue';
import BaseSwitch from '@/Components/UI/BaseSwitch.vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';

const { t } = useI18n();

const page = usePage();
const user = computed(() => page.props.auth.user);
const adminNotifs = computed(() => page.props.adminNotificationSettings ?? {});
const activeTab = ref('profile');

const profileForm = useForm({
    username: user.value?.username || '',
    email: user.value?.email || '',
    bio: user.value?.bio || '',
});

const avatarForm = useForm({ avatar: null });
const bannerForm = useForm({ banner: null });
const avatarPreview = ref(null);
const bannerPreview = ref(null);

const handleAvatarSelect = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    avatarForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const handleBannerSelect = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    bannerForm.banner = file;
    bannerPreview.value = URL.createObjectURL(file);
};

const uploadAvatar = () => {
    avatarForm.post('/settings/avatar', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarPreview.value = null;
            avatarForm.reset();
        },
    });
};

const uploadBanner = () => {
    bannerForm.post('/settings/banner', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            bannerPreview.value = null;
            bannerForm.reset();
        },
    });
};

const removeBanner = () => {
    bannerForm.delete('/settings/banner', {
        preserveScroll: true,
        onSuccess: () => {
            bannerPreview.value = null;
            bannerForm.reset();
        },
    });
};

/*
 * Channel social links.
 *
 * Rows are edited as a plain array and submitted whole — the server replaces
 * the stored list rather than patching it, so ordering is whatever the user
 * left here. Empty rows are stripped server-side, so an unfilled row that the
 * user never got to is not an error.
 */
const socialPlatforms = computed(() => page.props.socialPlatforms ?? []);
const socialLinksEnabled = computed(() => page.props.socialLinksEnabled !== false);
const maxSocialLinks = 8;

const socialForm = useForm({
    social_links: (user.value?.channel?.social_links ?? []).map((link) => ({
        platform: link.platform ?? 'website',
        url: link.url ?? '',
        label: link.label ?? '',
    })),
});

const isFreeformPlatform = (platform) =>
    socialPlatforms.value.find((p) => p.value === platform)?.freeform === true;

const addSocialLink = () => {
    if (socialForm.social_links.length >= maxSocialLinks) return;
    socialForm.social_links.push({ platform: 'website', url: '', label: '' });
};

const removeSocialLink = (index) => {
    socialForm.social_links.splice(index, 1);
};

const updateSocialLinks = () => {
    socialForm.put('/settings/social-links', { preserveScroll: true });
};

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    email_notifications: user.value?.settings?.email_notifications ?? true,
    push_notifications: user.value?.settings?.push_notifications ?? true,
    subscription_notifications: user.value?.settings?.subscription_notifications ?? true,
});

const { isSupported: pushSupported, isSubscribed: pushSubscribed, isLoading: pushLoading, checkSubscription, toggle: togglePush } = usePushNotifications();

onMounted(() => {
    checkSubscription();
});

const handlePushToggle = async () => {
    await togglePush();
};

const privacyForm = useForm({
    show_watch_history: user.value?.settings?.show_watch_history ?? true,
    show_liked_videos: user.value?.settings?.show_liked_videos ?? true,
    allow_comments: user.value?.settings?.allow_comments ?? true,
});

const updateProfile = () => {
    profileForm.put('/settings/profile', {
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.put('/settings/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
};

const updateNotifications = () => {
    notificationForm.put('/settings/notifications', {
        preserveScroll: true,
    });
};

const updatePrivacy = () => {
    privacyForm.put('/settings/privacy', {
        preserveScroll: true,
    });
};

const upgradeToPro = () => {
    router.visit('/pro');
};

const goToProPortal = () => {
    router.visit('/pro/portal', { preserveScroll: true });
};

const exportingData = ref(false);
const downloadMyData = () => {
    exportingData.value = true;
    window.location.href = '/settings/export-data';
    setTimeout(() => { exportingData.value = false; }, 2000);
};

const showDeleteConfirm = ref(false);
const deleteForm = useForm({
    password: '',
});

const confirmDeleteAccount = () => {
    deleteForm.delete('/settings/account', {
        onSuccess: () => {
            showDeleteConfirm.value = false;
        },
        onError: () => {
            // keep modal open so user sees the error
        },
    });
};

// Cancel closes the dialog and clears the typed password — no request is sent.
const cancelDeleteAccount = () => {
    showDeleteConfirm.value = false;
    deleteForm.reset();
};

const monetizationEnabled = computed(() => page.props.app?.monetization_enabled !== false);

// --- Two-Factor Authentication ---
const twoFactorEnabled = ref(page.props.twoFactorEnabled ?? false);
const twoFactorStep = ref('idle'); // idle | setup | confirm | recovery
const twoFactorQrCode = ref('');
const twoFactorSecret = ref('');
const twoFactorCode = ref('');
const twoFactorError = ref('');
const twoFactorProcessing = ref(false);
const recoveryCodes = ref([]);
const showDisable2fa = ref(false);
const disable2faPassword = ref('');
const disable2faError = ref('');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const apiPost = async (url, body = {}) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Something went wrong.');
        throw new Error(message);
    }
    return data;
};

const startTwoFactorSetup = async () => {
    twoFactorError.value = '';
    twoFactorProcessing.value = true;
    try {
        console.log('Starting 2FA setup...');
        const data = await apiPost('/settings/two-factor/enable');
        console.log('2FA setup response:', data);
        twoFactorQrCode.value = data.qr_code_svg;
        twoFactorSecret.value = data.secret;
        twoFactorStep.value = 'setup';
    } catch (e) {
        console.error('2FA setup error:', e);
        twoFactorError.value = e.message;
    } finally {
        twoFactorProcessing.value = false;
    }
};

const confirmTwoFactorSetup = async () => {
    twoFactorError.value = '';
    twoFactorProcessing.value = true;
    try {
        const data = await apiPost('/settings/two-factor/confirm', { code: twoFactorCode.value });
        recoveryCodes.value = data.recovery_codes;
        twoFactorEnabled.value = true;
        twoFactorStep.value = 'recovery';
        twoFactorCode.value = '';
    } catch (e) {
        twoFactorError.value = e.message;
    } finally {
        twoFactorProcessing.value = false;
    }
};

const finishTwoFactorSetup = () => {
    twoFactorStep.value = 'idle';
    recoveryCodes.value = [];
};

const regenerateRecoveryCodes = async () => {
    twoFactorError.value = '';
    twoFactorProcessing.value = true;
    try {
        const data = await apiPost('/settings/two-factor/recovery-codes');
        recoveryCodes.value = data.recovery_codes;
        twoFactorStep.value = 'recovery';
    } catch (e) {
        twoFactorError.value = e.message;
    } finally {
        twoFactorProcessing.value = false;
    }
};

const disableTwoFactor = async () => {
    disable2faError.value = '';
    twoFactorProcessing.value = true;
    try {
        await apiPost('/settings/two-factor/disable', { password: disable2faPassword.value });
        twoFactorEnabled.value = false;
        showDisable2fa.value = false;
        disable2faPassword.value = '';
        twoFactorStep.value = 'idle';
    } catch (e) {
        disable2faError.value = e.message;
    } finally {
        twoFactorProcessing.value = false;
    }
};

// Cancel closes the dialog and clears the typed password — no request is sent.
const cancelDisable2fa = () => {
    showDisable2fa.value = false;
    disable2faPassword.value = '';
    disable2faError.value = '';
};

const tabs = computed(() => {
    const items = [
        { id: 'profile', name: t('settings.profile'), icon: User },
        { id: 'channel', name: t('settings.channel'), icon: ImageIcon },
        { id: 'password', name: t('settings.password'), icon: Lock },
        { id: 'notifications', name: t('settings.notifications'), icon: Bell },
        { id: 'privacy', name: t('settings.privacy'), icon: Shield },
    ];
    if (monetizationEnabled.value) {
        items.push({ id: 'billing', name: t('settings.billing'), icon: Wallet });
    }
    return items;
});
</script>

<template>
    <SeoHead :title="t('settings.title')" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <h1 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6 text-text-primary">{{ t('settings.title') }}</h1>

            <TabsRoot v-model="activeTab" orientation="vertical" class="flex flex-col md:flex-row gap-4 sm:gap-6">
                <!-- Sidebar / Horizontal tabs on mobile -->
                <div class="md:w-48 shrink-0">
                    <TabsList class="flex md:flex-col gap-1 overflow-x-auto scrollbar-hide -mx-1 px-1 md:mx-0 md:px-0 pb-2 md:pb-0">
                        <TabsTrigger
                            v-for="tab in tabs"
                            :key="tab.id"
                            :value="tab.id"
                            :class="['flex items-center gap-2 sm:gap-3 px-3 py-2 rounded-lg text-start transition-colors whitespace-nowrap shrink-0 md:w-full text-sm sm:text-base']"
                            :style="activeTab === tab.id
                                ? { backgroundColor: 'var(--color-accent)', color: 'white' }
                                : { color: 'var(--color-text-secondary)' }"
                        >
                            <component :is="tab.icon" class="w-4 h-4 sm:w-5 sm:h-5" />
                            <span>{{ tab.name }}</span>
                        </TabsTrigger>
                    </TabsList>
                </div>

                <!-- Content -->
                <div class="flex-1">
                    <!-- Profile Tab -->
                    <TabsContent value="profile" class="space-y-6">
                        <!-- Profile Photo -->
                        <div class="card p-6">
                            <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('settings.profile_images') }}</h2>

                            <!-- Avatar Upload -->
                            <div>
                                <label class="block text-sm font-medium mb-2 text-text-secondary">{{ t('settings.avatar') }}</label>
                                <div class="flex items-center gap-4">
                                    <div class="relative w-20 h-20 rounded-full overflow-hidden shrink-0 bg-bg-secondary">
                                        <img
                                            :src="avatarPreview || user?.avatar || '/images/default_avatar.webp'"
                                            alt="Avatar"
                                            class="w-full h-full object-cover"
                                        />
                                        <label class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 hover:opacity-100 transition-opacity cursor-pointer rounded-full">
                                            <Camera class="w-5 h-5 text-white" />
                                            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="handleAvatarSelect" />
                                        </label>
                                    </div>
                                    <div>
                                        <p class="text-sm text-text-secondary">{{ t('settings.change_avatar') }}</p>
                                        <p class="text-xs text-text-muted">Max 2MB (JPG, PNG, WebP, GIF)</p>
                                        <p v-if="avatarForm.errors.avatar" class="text-red-500 text-sm mt-1">{{ avatarForm.errors.avatar }}</p>
                                        <div v-if="avatarPreview" class="flex items-center gap-2 mt-2">
                                            <button @click="uploadAvatar" :disabled="avatarForm.processing" class="btn btn-primary text-sm">
                                                <Loader2 v-if="avatarForm.processing" class="w-4 h-4 animate-spin me-1" />
                                                {{ t('settings.save_avatar') }}
                                            </button>
                                            <button @click="avatarPreview = null; avatarForm.reset()" class="btn btn-ghost text-sm">{{ t('common.cancel') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card p-6">
                        <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('settings.profile_settings') }}</h2>
                        <form @submit.prevent="updateProfile" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.username') }}</label>
                                <input v-model="profileForm.username" type="text" class="input" />
                                <p v-if="profileForm.errors.username" class="text-red-500 text-sm mt-1">
                                    {{ profileForm.errors.username }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.email') }}</label>
                                <input v-model="profileForm.email" type="email" class="input" />
                                <p v-if="profileForm.errors.email" class="text-red-500 text-sm mt-1">
                                    {{ profileForm.errors.email }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.bio') }}</label>
                                <textarea v-model="profileForm.bio" rows="4" class="input resize-none"></textarea>
                            </div>
                            <button type="submit" :disabled="profileForm.processing" class="btn btn-primary">
                                {{ t('settings.save_changes') }}
                            </button>
                        </form>
                        </div>

                        <!-- Delete Account -->
                        <div class="card p-6 border border-red-500/20">
                            <div class="flex items-center gap-3 mb-3">
                                <AlertTriangle class="w-5 h-5 text-red-500" />
                                <h2 class="text-lg font-semibold text-text-primary">Delete Account</h2>
                            </div>
                            <p class="text-sm mb-4 text-text-secondary">
                                This action is irreversible. All your data, videos, comments, playlists, and uploads will be permanently deleted.
                            </p>
                            <button
                                @click="showDeleteConfirm = true"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                            >
                                <Trash2 class="w-4 h-4" />
                                Delete My Account
                            </button>
                        </div>
                    </TabsContent>

                    <!-- Channel Tab -->
                    <TabsContent value="channel" class="space-y-6">
                        <!--
                            Banner upload. bannerForm/handleBannerSelect/uploadBanner
                            and POST /settings/banner all already existed; only this
                            markup was missing, so the feature was unreachable.
                        -->
                        <div class="card p-6">
                            <h2 class="text-lg font-semibold text-text-primary">{{ t('settings.banner') }}</h2>
                            <p class="mt-1 mb-4 text-sm text-text-secondary">{{ t('settings.banner_hint') }}</p>

                            <div class="relative w-full aspect-[6/1] rounded-card overflow-hidden bg-bg-input">
                                <img
                                    v-if="bannerPreview || user?.channel?.banner_image"
                                    :src="bannerPreview || user?.channel?.banner_image"
                                    :alt="t('settings.banner')"
                                    class="w-full h-full object-cover"
                                />
                                <label
                                    class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/40 cursor-pointer transition-opacity"
                                    :class="(bannerPreview || user?.channel?.banner_image) ? 'opacity-0 hover:opacity-100' : 'opacity-100'"
                                >
                                    <ImageIcon class="w-6 h-6 text-white" />
                                    <span class="text-xs text-white">{{ t('settings.change_banner') }}</span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleBannerSelect" />
                                </label>
                            </div>

                            <p v-if="bannerForm.errors.banner" class="text-red-500 text-sm mt-2">{{ bannerForm.errors.banner }}</p>

                            <div class="flex items-center gap-2 mt-3">
                                <template v-if="bannerPreview">
                                    <button @click="uploadBanner" :disabled="bannerForm.processing" class="btn btn-primary text-sm">
                                        <Loader2 v-if="bannerForm.processing" class="w-4 h-4 animate-spin me-1" />
                                        {{ t('settings.save_banner') }}
                                    </button>
                                    <button @click="bannerPreview = null; bannerForm.reset()" class="btn btn-ghost text-sm">
                                        {{ t('common.cancel') }}
                                    </button>
                                </template>
                                <button
                                    v-else-if="user?.channel?.banner_image"
                                    @click="removeBanner"
                                    :disabled="bannerForm.processing"
                                    class="btn btn-ghost text-sm text-red-500"
                                >
                                    <Trash2 class="w-4 h-4 me-1" />
                                    {{ t('settings.remove_banner') }}
                                </button>
                            </div>
                        </div>

                        <div class="card p-6">
                            <h2 class="text-lg font-semibold text-text-primary">{{ t('settings.social_links') }}</h2>
                            <p class="mt-1 text-sm text-text-secondary">{{ t('settings.social_links_desc') }}</p>

                            <p v-if="!socialLinksEnabled" class="mt-3 text-sm text-amber-500">
                                {{ t('settings.social_links_disabled') }}
                            </p>
                            <p v-else-if="!user?.email_verified" class="mt-3 text-sm text-amber-500">
                                {{ t('settings.social_links_unverified') }}
                            </p>

                            <form @submit.prevent="updateSocialLinks" class="mt-4 space-y-3">
                                <div
                                    v-for="(link, index) in socialForm.social_links"
                                    :key="index"
                                    class="flex flex-col sm:flex-row gap-2"
                                >
                                    <select v-model="link.platform" class="input sm:w-44 shrink-0">
                                        <option v-for="p in socialPlatforms" :key="p.value" :value="p.value">
                                            {{ p.label }}
                                        </option>
                                    </select>
                                    <div class="flex-1 min-w-0">
                                        <input
                                            v-model="link.url"
                                            type="url"
                                            inputmode="url"
                                            :placeholder="t('settings.social_links_placeholder')"
                                            class="input"
                                        />
                                        <p v-if="socialForm.errors[`social_links.${index}.url`]" class="text-red-500 text-sm mt-1">
                                            {{ socialForm.errors[`social_links.${index}.url`] }}
                                        </p>
                                        <input
                                            v-if="isFreeformPlatform(link.platform)"
                                            v-model="link.label"
                                            type="text"
                                            maxlength="40"
                                            :placeholder="t('settings.social_links_label')"
                                            class="input mt-2"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeSocialLink(index)"
                                        class="btn btn-ghost text-red-500 shrink-0"
                                        :aria-label="t('common.remove')"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>

                                <p v-if="socialForm.errors.social_links" class="text-red-500 text-sm">
                                    {{ socialForm.errors.social_links }}
                                </p>

                                <div class="flex items-center gap-2 pt-1">
                                    <button
                                        type="button"
                                        @click="addSocialLink"
                                        :disabled="socialForm.social_links.length >= maxSocialLinks"
                                        class="btn btn-secondary text-sm"
                                    >
                                        {{ t('settings.social_links_add') }}
                                    </button>
                                    <button type="submit" :disabled="socialForm.processing" class="btn btn-primary text-sm">
                                        <Loader2 v-if="socialForm.processing" class="w-4 h-4 animate-spin me-1" />
                                        {{ t('settings.save_changes') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </TabsContent>

                    <TabsContent value="password" class="focus:outline-none">
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('settings.change_password') }}</h2>
                        <form @submit.prevent="updatePassword" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.current_password') }}</label>
                                <input v-model="passwordForm.current_password" type="password" autocomplete="current-password" class="input" />
                                <p v-if="passwordForm.errors.current_password" class="text-red-500 text-sm mt-1">
                                    {{ passwordForm.errors.current_password }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.new_password') }}</label>
                                <input v-model="passwordForm.password" type="password" autocomplete="new-password" class="input" />
                                <p v-if="passwordForm.errors.password" class="text-red-500 text-sm mt-1">
                                    {{ passwordForm.errors.password }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-text-secondary">{{ t('settings.confirm_password') }}</label>
                                <input v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" class="input" />
                            </div>
                            <button type="submit" :disabled="passwordForm.processing" class="btn btn-primary">
                                {{ t('settings.update_password') }}
                            </button>
                        </form>
                    </div>

                    <!-- Two-Factor Authentication -->
                    <div class="card p-6 mt-6">
                        <div class="flex items-center gap-3 mb-4">
                            <ShieldCheck class="w-5 h-5 text-accent" />
                            <h2 class="text-lg font-semibold text-text-primary">Two-Factor Authentication</h2>
                        </div>

                        <!-- Idle: show status -->
                        <template v-if="twoFactorStep === 'idle'">
                            <p class="text-sm mb-4 text-text-secondary">
                                Add an extra layer of security to your account by requiring an authentication code from your phone in addition to your password.
                            </p>
                            <div v-if="twoFactorEnabled" class="flex items-center gap-3 flex-wrap">
                                <span class="px-3 py-1 rounded-full text-sm font-medium bg-accent" style="color: white;">Enabled</span>
                                <button @click="regenerateRecoveryCodes" :disabled="twoFactorProcessing" class="btn btn-secondary text-sm">
                                    Regenerate Recovery Codes
                                </button>
                                <button @click="showDisable2fa = true" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors">
                                    Disable 2FA
                                </button>
                            </div>
                            <button v-else @click="startTwoFactorSetup" :disabled="twoFactorProcessing" class="btn btn-primary text-sm">
                                <Loader2 v-if="twoFactorProcessing" class="w-4 h-4 animate-spin me-1" />
                                Enable Two-Factor Authentication
                            </button>
                            <p v-if="twoFactorError" class="text-red-500 text-sm mt-2">{{ twoFactorError }}</p>
                        </template>

                        <!-- Setup: show QR code + confirm form -->
                        <template v-else-if="twoFactorStep === 'setup'">
                            <div class="max-w-md mx-auto">
                                <p class="text-sm mb-4 text-text-secondary text-center">
                                    Scan this QR code with your authenticator app (Google Authenticator, Authy, 1Password, etc.), then enter the 6-digit code below to confirm.
                                </p>
                                
                                <div class="flex flex-col items-center gap-4 mb-4">
                                    <div class="p-3 bg-white rounded-lg inline-block" style="min-width: 180px; min-height: 180px;">
                                        <img v-if="twoFactorQrCode" :src="twoFactorQrCode" alt="QR Code" class="w-44 h-44" />
                                        <div v-else class="flex items-center justify-center text-gray-400 text-sm" style="min-width: 180px; min-height: 180px;">
                                            Loading QR code...
                                        </div>
                                    </div>
                                    <div class="text-center w-full">
                                        <p class="text-xs text-text-muted mb-1">Or enter this key manually:</p>
                                        <code class="text-xs px-2 py-1 rounded bg-bg-secondary text-text-primary break-all inline-block">{{ twoFactorSecret }}</code>
                                    </div>
                                </div>

                                <form @submit.prevent="confirmTwoFactorSetup" class="space-y-3">
                                    <input
                                        v-model="twoFactorCode"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="123456"
                                        class="input text-center tracking-widest text-lg"
                                        maxlength="6"
                                        autofocus
                                        required
                                    />
                                    <p v-if="twoFactorError" class="text-red-500 text-sm text-center">{{ twoFactorError }}</p>
                                    <div class="flex gap-2">
                                        <button type="button" @click="twoFactorStep = 'idle'" class="btn btn-ghost flex-1 text-sm">Cancel</button>
                                        <button type="submit" :disabled="twoFactorProcessing" class="btn btn-primary flex-1 text-sm">
                                            <Loader2 v-if="twoFactorProcessing" class="w-4 h-4 animate-spin" />
                                            <span v-else>Confirm</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </template>

                        <!-- Recovery codes shown once after confirm / regenerate -->
                        <template v-else-if="twoFactorStep === 'recovery'">
                            <div class="flex items-center gap-2 mb-3">
                                <KeyRound class="w-4 h-4 text-accent" />
                                <p class="font-medium text-text-primary">Save your recovery codes</p>
                            </div>
                            <p class="text-sm mb-3 text-text-secondary">
                                Store these codes somewhere safe. Each one can be used once to sign in if you lose access to your authenticator app.
                            </p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-4 rounded-lg bg-bg-secondary font-mono text-sm mb-4">
                                <span v-for="rc in recoveryCodes" :key="rc" class="text-text-primary text-center py-1">{{ rc }}</span>
                            </div>
                            <button @click="finishTwoFactorSetup" class="btn btn-primary text-sm">I've saved these codes</button>
                        </template>
                    </div>
                    </TabsContent>

                    <!-- Notifications Tab -->
                    <TabsContent value="notifications" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('settings.notification_prefs') }}</h2>
                        <form @submit.prevent="updateNotifications" class="space-y-4">
                            <div v-if="adminNotifs.email_notifications !== false" class="flex items-center justify-between">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.email_notifications') }}</p>
                                    <p class="text-sm text-text-secondary">{{ t('settings.email_notifications_desc') }}</p>
                                </div>
                                <BaseSwitch v-model="notificationForm.email_notifications" :label="t('settings.email_notifications')" />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.push_notifications') }}</p>
                                    <p class="text-sm text-text-secondary">{{ t('settings.push_notifications_desc') }}</p>
                                </div>
                                <BaseSwitch v-model="notificationForm.push_notifications" :label="t('settings.push_notifications')" />
                            </div>

                            <!-- Browser Push Subscription -->
                            <div v-if="pushSupported" class="flex items-center justify-between p-3 rounded-lg bg-bg-secondary">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.browser_push') }}</p>
                                    <p class="text-sm text-text-secondary">
                                        {{ pushSubscribed ? 'This browser is receiving push notifications' : 'Enable push notifications for this browser' }}
                                    </p>
                                </div>
                                <button 
                                    @click="handlePushToggle" 
                                    :disabled="pushLoading"
                                    :class="['btn text-sm', pushSubscribed ? 'btn-secondary' : 'btn-primary']"
                                >
                                    <Loader2 v-if="pushLoading" class="w-4 h-4 animate-spin" />
                                    <span v-else>{{ pushSubscribed ? 'Disable' : 'Enable' }}</span>
                                </button>
                            </div>
                            <div v-if="adminNotifs.subscription_notifications !== false" class="flex items-center justify-between">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.subscription_updates') }}</p>
                                    <p class="text-sm text-text-secondary">{{ t('settings.subscription_updates_desc') }}</p>
                                </div>
                                <BaseSwitch v-model="notificationForm.subscription_notifications" :label="t('settings.subscription_updates')" />
                            </div>
                            <button type="submit" :disabled="notificationForm.processing" class="btn btn-primary">
                                {{ t('settings.save_preferences') }}
                            </button>
                        </form>
                    </TabsContent>

                    <!-- Privacy Tab -->
                    <TabsContent value="privacy" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('settings.privacy_settings') }}</h2>
                        <form @submit.prevent="updatePrivacy" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.show_watch_history') }}</p>
                                    <p class="text-sm text-text-secondary">{{ t('settings.show_watch_history_desc') }}</p>
                                </div>
                                <BaseSwitch v-model="privacyForm.show_watch_history" :label="t('settings.show_watch_history')" />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.show_liked_videos') }}</p>
                                    <p class="text-sm text-text-secondary">{{ t('settings.show_liked_videos_desc') }}</p>
                                </div>
                                <BaseSwitch v-model="privacyForm.show_liked_videos" :label="t('settings.show_liked_videos')" />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-text-primary">{{ t('settings.allow_comments') }}</p>
                                    <p class="text-sm text-text-secondary">{{ t('settings.allow_comments_desc') }}</p>
                                </div>
                                <BaseSwitch v-model="privacyForm.allow_comments" :label="t('settings.allow_comments')" />
                            </div>
                            <button type="submit" :disabled="privacyForm.processing" class="btn btn-primary">
                                {{ t('settings.save_changes') }}
                            </button>
                        </form>

                        <div class="mt-6 pt-6 border-t border-border">
                            <h3 class="font-medium mb-1 text-text-primary">Download My Data</h3>
                            <p class="text-sm mb-3 text-text-secondary">
                                Export a copy of your profile, videos, comments, playlists, watch history, and wallet transactions as a JSON file.
                            </p>
                            <button @click="downloadMyData" :disabled="exportingData" class="btn btn-secondary gap-2">
                                <Loader2 v-if="exportingData" class="w-4 h-4 animate-spin" />
                                <Download v-else class="w-4 h-4" />
                                Download My Data
                            </button>
                        </div>
                    </TabsContent>

                    <!-- Billing Tab -->
                    <TabsContent value="billing" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4 text-text-primary">{{ t('settings.billing') }}</h2>
                        <div class="space-y-4">
                            <div class="p-4 rounded-lg bg-bg-secondary">
                                <div class="flex items-center justify-between flex-wrap gap-3">
                                    <div>
                                        <p class="font-medium text-text-primary">{{ t('settings.current_plan') }}</p>
                                        <p class="text-text-secondary">{{ user?.is_pro ? 'Pro' : 'Free' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button v-if="!user?.is_pro" @click="upgradeToPro" class="btn btn-primary">
                                            {{ t('settings.upgrade_pro') }}
                                        </button>
                                        <button v-else @click="goToProPortal" class="btn btn-secondary">
                                            Manage Subscription
                                        </button>
                                        <span v-if="user?.is_pro" class="px-3 py-1 rounded-full text-sm font-medium bg-accent" style="color: white;">
                                            Active
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 rounded-lg bg-bg-secondary">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium mb-2 text-text-primary">{{ t('settings.wallet_balance') }}</p>
                                        <p class="text-2xl font-bold text-accent">${{ user?.wallet_balance || '0.00' }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="/wallet/deposit" class="btn btn-primary">{{ t('settings.deposit') }}</a>
                                        <a href="/wallet/withdraw" class="btn btn-secondary">{{ t('settings.withdraw') }}</a>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 rounded-lg bg-bg-secondary">
                                <p class="font-medium mb-3 text-text-primary">Pro Benefits</p>
                                <ul class="space-y-2 text-sm text-text-secondary">
                                    <li class="flex items-center gap-2">
                                        <span class="text-accent">✓</span>
                                        Ad-free viewing
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="text-accent">✓</span>
                                        Upload videos up to 1 GB
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="text-accent">✓</span>
                                        Higher daily upload cap
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="text-accent">✓</span>
                                        Download videos
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="text-accent">✓</span>
                                        Pro badge on channel and comments
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </TabsContent>

                </div>
            </TabsRoot>
        </div>

        <!-- Disable 2FA Confirmation Modal -->
        <BaseDialog
            v-model="showDisable2fa"
            variant="alert"
            title="Disable Two-Factor Authentication"
        >
            <form @submit.prevent="disableTwoFactor">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1 text-text-secondary">Password</label>
                    <input
                        v-model="disable2faPassword"
                        type="password"
                        autocomplete="current-password"
                        class="w-full px-3 py-2 rounded-lg border text-sm bg-bg-secondary border-border text-text-primary"
                        placeholder="Enter your password"
                        required
                    />
                    <p v-if="disable2faError" class="text-red-500 text-sm mt-1">{{ disable2faError }}</p>
                </div>
                <div class="flex gap-3 justify-end">
                    <button type="button" @click="cancelDisable2fa" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary bg-bg-secondary">
                        Cancel
                    </button>
                    <button type="submit" :disabled="twoFactorProcessing || !disable2faPassword" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors">
                        <Loader2 v-if="twoFactorProcessing" class="w-4 h-4 animate-spin inline" />
                        <span v-else>Disable</span>
                    </button>
                </div>
            </form>
        </BaseDialog>

        <!-- Delete Account Confirmation Modal -->
        <BaseDialog
            v-model="showDeleteConfirm"
            variant="alert"
            aria-label="Delete account"
        >
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center">
                    <AlertTriangle class="w-5 h-5 text-red-500" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text-primary">Are you sure?</h3>
                    <p class="text-sm text-text-secondary">This action is permanent and cannot be undone.</p>
                </div>
            </div>

            <p class="text-sm mb-4 text-text-secondary">
                All your videos, comments, playlists, subscriptions, and wallet balance will be permanently deleted.
                Enter your password to confirm.
            </p>

            <form @submit.prevent="confirmDeleteAccount">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1 text-text-secondary">Password</label>
                    <input
                        v-model="deleteForm.password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full px-3 py-2 rounded-lg border text-sm bg-bg-secondary border-border text-text-primary"
                        placeholder="Enter your password"
                        required
                    />
                    <p v-if="deleteForm.errors.password" class="text-red-500 text-sm mt-1">{{ deleteForm.errors.password }}</p>
                </div>

                <div class="flex gap-3 justify-end">
                    <button
                        type="button"
                        @click="cancelDeleteAccount"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary bg-bg-secondary"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="deleteForm.processing || !deleteForm.password"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                    >
                        <Loader2 v-if="deleteForm.processing" class="w-4 h-4 animate-spin" />
                        <Trash2 v-else class="w-4 h-4" />
                        Delete Permanently
                    </button>
                </div>
            </form>
        </BaseDialog>
    </AppLayout>
</template>
