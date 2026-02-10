<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { User, Lock, Bell, Shield, CreditCard, ExternalLink, Loader2, Camera, ImageIcon } from 'lucide-vue-next';
import { usePushNotifications } from '@/Composables/usePushNotifications';

const page = usePage();
const user = computed(() => page.props.auth.user);
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

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    email_notifications: user.value?.email_notifications ?? true,
    push_notifications: user.value?.push_notifications ?? true,
    subscription_notifications: user.value?.subscription_notifications ?? true,
});

const { isSupported: pushSupported, isSubscribed: pushSubscribed, isLoading: pushLoading, checkSubscription, toggle: togglePush } = usePushNotifications();

onMounted(() => {
    checkSubscription();
});

const handlePushToggle = async () => {
    await togglePush();
};

const privacyForm = useForm({
    private_profile: user.value?.settings?.private_profile ?? false,
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
    router.visit('/wallet/deposit', {
        data: { upgrade: true },
    });
};

const tabs = [
    { id: 'profile', name: 'Profile', icon: User },
    { id: 'password', name: 'Password', icon: Lock },
    { id: 'notifications', name: 'Notifications', icon: Bell },
    { id: 'privacy', name: 'Privacy', icon: Shield },
    { id: 'billing', name: 'Billing', icon: CreditCard },
];
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <h1 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6" style="color: var(--color-text-primary);">Settings</h1>

            <div class="flex flex-col md:flex-row gap-4 sm:gap-6">
                <!-- Sidebar / Horizontal tabs on mobile -->
                <div class="md:w-48 shrink-0">
                    <nav class="flex md:flex-col gap-1 overflow-x-auto scrollbar-hide -mx-1 px-1 md:mx-0 md:px-0 pb-2 md:pb-0">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="['flex items-center gap-2 sm:gap-3 px-3 py-2 rounded-lg text-left transition-colors whitespace-nowrap shrink-0 md:w-full text-sm sm:text-base']"
                            :style="activeTab === tab.id 
                                ? { backgroundColor: 'var(--color-accent)', color: 'white' } 
                                : { color: 'var(--color-text-secondary)' }"
                        >
                            <component :is="tab.icon" class="w-4 h-4 sm:w-5 sm:h-5" />
                            <span>{{ tab.name }}</span>
                        </button>
                    </nav>
                </div>

                <!-- Content -->
                <div class="flex-1">
                    <!-- Profile Tab -->
                    <div v-if="activeTab === 'profile'" class="space-y-6">
                        <!-- Avatar & Banner Upload -->
                        <div class="card p-6">
                            <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">Profile Images</h2>
                            
                            <!-- Banner Upload -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">Channel Banner</label>
                                <div class="relative h-32 md:h-40 rounded-xl overflow-hidden" style="background-color: var(--color-bg-secondary);">
                                    <img
                                        v-if="bannerPreview || user?.channel?.banner_image"
                                        :src="bannerPreview || user.channel.banner_image"
                                        alt="Channel banner"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <ImageIcon class="w-10 h-10" style="color: var(--color-text-muted);" />
                                    </div>
                                    <label class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 hover:opacity-100 transition-opacity cursor-pointer">
                                        <div class="flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium" style="background-color: rgba(0,0,0,0.6);">
                                            <Camera class="w-4 h-4" />
                                            Change Banner
                                        </div>
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleBannerSelect" />
                                    </label>
                                </div>
                                <p v-if="bannerForm.errors.banner" class="text-red-500 text-sm mt-1">{{ bannerForm.errors.banner }}</p>
                                <div v-if="bannerPreview" class="flex items-center gap-2 mt-2">
                                    <button @click="uploadBanner" :disabled="bannerForm.processing" class="btn btn-primary text-sm">
                                        <Loader2 v-if="bannerForm.processing" class="w-4 h-4 animate-spin mr-1" />
                                        Save Banner
                                    </button>
                                    <button @click="bannerPreview = null; bannerForm.reset()" class="btn btn-ghost text-sm">Cancel</button>
                                </div>
                                <p class="text-xs mt-1" style="color: var(--color-text-muted);">Recommended: 1280×320px, max 5MB (JPG, PNG, WebP)</p>
                            </div>

                            <!-- Avatar Upload -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">Avatar</label>
                                <div class="flex items-center gap-4">
                                    <div class="relative w-20 h-20 rounded-full overflow-hidden shrink-0" style="background-color: var(--color-bg-secondary);">
                                        <img
                                            v-if="avatarPreview || user?.avatar"
                                            :src="avatarPreview || user.avatar"
                                            alt="Avatar"
                                            class="w-full h-full object-cover"
                                        />
                                        <div v-else class="w-full h-full flex items-center justify-center text-white text-2xl font-bold" style="background-color: var(--color-accent);">
                                            {{ user?.username?.charAt(0)?.toUpperCase() }}
                                        </div>
                                        <label class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 hover:opacity-100 transition-opacity cursor-pointer rounded-full">
                                            <Camera class="w-5 h-5 text-white" />
                                            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="handleAvatarSelect" />
                                        </label>
                                    </div>
                                    <div>
                                        <p class="text-sm" style="color: var(--color-text-secondary);">Click to change your avatar</p>
                                        <p class="text-xs" style="color: var(--color-text-muted);">Max 2MB (JPG, PNG, WebP, GIF)</p>
                                        <p v-if="avatarForm.errors.avatar" class="text-red-500 text-sm mt-1">{{ avatarForm.errors.avatar }}</p>
                                        <div v-if="avatarPreview" class="flex items-center gap-2 mt-2">
                                            <button @click="uploadAvatar" :disabled="avatarForm.processing" class="btn btn-primary text-sm">
                                                <Loader2 v-if="avatarForm.processing" class="w-4 h-4 animate-spin mr-1" />
                                                Save Avatar
                                            </button>
                                            <button @click="avatarPreview = null; avatarForm.reset()" class="btn btn-ghost text-sm">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card p-6">
                        <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">Profile Settings</h2>
                        <form @submit.prevent="updateProfile" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Username</label>
                                <input v-model="profileForm.username" type="text" class="input" />
                                <p v-if="profileForm.errors.username" class="text-red-500 text-sm mt-1">
                                    {{ profileForm.errors.username }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Email</label>
                                <input v-model="profileForm.email" type="email" class="input" />
                                <p v-if="profileForm.errors.email" class="text-red-500 text-sm mt-1">
                                    {{ profileForm.errors.email }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Bio</label>
                                <textarea v-model="profileForm.bio" rows="4" class="input resize-none"></textarea>
                            </div>
                            <button type="submit" :disabled="profileForm.processing" class="btn btn-primary">
                                Save Changes
                            </button>
                        </form>
                        </div>
                    </div>

                    <!-- Password Tab -->
                    <div v-if="activeTab === 'password'" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">Change Password</h2>
                        <form @submit.prevent="updatePassword" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Current Password</label>
                                <input v-model="passwordForm.current_password" type="password" class="input" />
                                <p v-if="passwordForm.errors.current_password" class="text-red-500 text-sm mt-1">
                                    {{ passwordForm.errors.current_password }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">New Password</label>
                                <input v-model="passwordForm.password" type="password" class="input" />
                                <p v-if="passwordForm.errors.password" class="text-red-500 text-sm mt-1">
                                    {{ passwordForm.errors.password }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--color-text-secondary);">Confirm New Password</label>
                                <input v-model="passwordForm.password_confirmation" type="password" class="input" />
                            </div>
                            <button type="submit" :disabled="passwordForm.processing" class="btn btn-primary">
                                Update Password
                            </button>
                        </form>
                    </div>

                    <!-- Notifications Tab -->
                    <div v-if="activeTab === 'notifications'" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">Notification Preferences</h2>
                        <form @submit.prevent="updateNotifications" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Email Notifications</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Receive notifications via email</p>
                                </div>
                                <input 
                                    v-model="notificationForm.email_notifications" 
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600"
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Push Notifications</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Receive push notifications in browser</p>
                                </div>
                                <input 
                                    v-model="notificationForm.push_notifications" 
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600"
                                />
                            </div>

                            <!-- Browser Push Subscription -->
                            <div v-if="pushSupported" class="flex items-center justify-between p-3 rounded-lg" style="background-color: var(--color-bg-secondary);">
                                <div>
                                    <p style="color: var(--color-text-primary);">Browser Push</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">
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
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Subscription Updates</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Get notified when channels you subscribe to upload</p>
                                </div>
                                <input 
                                    v-model="notificationForm.subscription_notifications" 
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600"
                                />
                            </div>
                            <button type="submit" :disabled="notificationForm.processing" class="btn btn-primary">
                                Save Preferences
                            </button>
                        </form>
                    </div>

                    <!-- Privacy Tab -->
                    <div v-if="activeTab === 'privacy'" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">Privacy Settings</h2>
                        <form @submit.prevent="updatePrivacy" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Private Profile</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Only approved followers can see your content</p>
                                </div>
                                <input 
                                    v-model="privacyForm.private_profile"
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600" 
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Show Watch History</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Allow others to see what you've watched</p>
                                </div>
                                <input 
                                    v-model="privacyForm.show_watch_history"
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600" 
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Show Liked Videos</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Allow others to see videos you've liked</p>
                                </div>
                                <input 
                                    v-model="privacyForm.show_liked_videos"
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600" 
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p style="color: var(--color-text-primary);">Allow Comments</p>
                                    <p class="text-sm" style="color: var(--color-text-secondary);">Allow others to comment on your videos by default</p>
                                </div>
                                <input 
                                    v-model="privacyForm.allow_comments"
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600" 
                                />
                            </div>
                            <button type="submit" :disabled="privacyForm.processing" class="btn btn-primary">
                                Save Privacy Settings
                            </button>
                        </form>
                    </div>

                    <!-- Billing Tab -->
                    <div v-if="activeTab === 'billing'" class="card p-6">
                        <h2 class="text-lg font-semibold mb-4" style="color: var(--color-text-primary);">Billing & Subscription</h2>
                        <div class="space-y-4">
                            <div class="p-4 rounded-lg" style="background-color: var(--color-bg-secondary);">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium" style="color: var(--color-text-primary);">Current Plan</p>
                                        <p style="color: var(--color-text-secondary);">{{ user?.is_pro ? 'Pro' : 'Free' }}</p>
                                    </div>
                                    <button v-if="!user?.is_pro" @click="upgradeToPro" class="btn btn-primary">
                                        Upgrade to Pro
                                    </button>
                                    <span v-else class="px-3 py-1 rounded-full text-sm font-medium" style="background-color: var(--color-accent); color: white;">
                                        Active
                                    </span>
                                </div>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: var(--color-bg-secondary);">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium mb-2" style="color: var(--color-text-primary);">Wallet Balance</p>
                                        <p class="text-2xl font-bold" style="color: var(--color-accent);">${{ user?.wallet_balance || '0.00' }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="/wallet/deposit" class="btn btn-primary">Deposit</a>
                                        <a href="/wallet/withdraw" class="btn btn-secondary">Withdraw</a>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: var(--color-bg-secondary);">
                                <p class="font-medium mb-3" style="color: var(--color-text-primary);">Pro Benefits</p>
                                <ul class="space-y-2 text-sm" style="color: var(--color-text-secondary);">
                                    <li class="flex items-center gap-2">
                                        <span style="color: var(--color-accent);">✓</span>
                                        Upload up to 50 videos per day
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span style="color: var(--color-accent);">✓</span>
                                        Upload videos up to 5GB
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span style="color: var(--color-accent);">✓</span>
                                        Edit videos after upload (thumbnails, title, tags)
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span style="color: var(--color-accent);">✓</span>
                                        Go Live streaming access
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span style="color: var(--color-accent);">✓</span>
                                        Priority video processing
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span style="color: var(--color-accent);">✓</span>
                                        Advanced analytics
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
