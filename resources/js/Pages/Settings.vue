<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { User, Lock, Bell, Shield, CreditCard } from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);
const activeTab = ref('profile');

const profileForm = useForm({
    username: user.value?.username || '',
    email: user.value?.email || '',
    bio: user.value?.bio || '',
});

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

const updateProfile = () => {
    profileForm.put('/settings/profile', {
        preserveScroll: true,
        onSuccess: () => {
            // Show success message
        },
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
            <h1 class="text-2xl font-bold text-white mb-6">Settings</h1>

            <div class="flex flex-col md:flex-row gap-6">
                <!-- Sidebar -->
                <div class="md:w-48 flex-shrink-0">
                    <nav class="space-y-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                'flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left transition-colors',
                                activeTab === tab.id
                                    ? 'bg-primary-600 text-white'
                                    : 'text-dark-400 hover:bg-dark-800 hover:text-white'
                            ]"
                        >
                            <component :is="tab.icon" class="w-5 h-5" />
                            <span>{{ tab.name }}</span>
                        </button>
                    </nav>
                </div>

                <!-- Content -->
                <div class="flex-1">
                    <!-- Profile Tab -->
                    <div v-if="activeTab === 'profile'" class="card p-6">
                        <h2 class="text-lg font-semibold text-white mb-4">Profile Settings</h2>
                        <form @submit.prevent="updateProfile" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-dark-300 mb-1">Username</label>
                                <input v-model="profileForm.username" type="text" class="input" />
                                <p v-if="profileForm.errors.username" class="text-red-500 text-sm mt-1">
                                    {{ profileForm.errors.username }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-dark-300 mb-1">Email</label>
                                <input v-model="profileForm.email" type="email" class="input" />
                                <p v-if="profileForm.errors.email" class="text-red-500 text-sm mt-1">
                                    {{ profileForm.errors.email }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-dark-300 mb-1">Bio</label>
                                <textarea v-model="profileForm.bio" rows="4" class="input resize-none"></textarea>
                            </div>
                            <button type="submit" :disabled="profileForm.processing" class="btn btn-primary">
                                Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Password Tab -->
                    <div v-if="activeTab === 'password'" class="card p-6">
                        <h2 class="text-lg font-semibold text-white mb-4">Change Password</h2>
                        <form @submit.prevent="updatePassword" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-dark-300 mb-1">Current Password</label>
                                <input v-model="passwordForm.current_password" type="password" class="input" />
                                <p v-if="passwordForm.errors.current_password" class="text-red-500 text-sm mt-1">
                                    {{ passwordForm.errors.current_password }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-dark-300 mb-1">New Password</label>
                                <input v-model="passwordForm.password" type="password" class="input" />
                                <p v-if="passwordForm.errors.password" class="text-red-500 text-sm mt-1">
                                    {{ passwordForm.errors.password }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-dark-300 mb-1">Confirm New Password</label>
                                <input v-model="passwordForm.password_confirmation" type="password" class="input" />
                            </div>
                            <button type="submit" :disabled="passwordForm.processing" class="btn btn-primary">
                                Update Password
                            </button>
                        </form>
                    </div>

                    <!-- Notifications Tab -->
                    <div v-if="activeTab === 'notifications'" class="card p-6">
                        <h2 class="text-lg font-semibold text-white mb-4">Notification Preferences</h2>
                        <form @submit.prevent="updateNotifications" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white">Email Notifications</p>
                                    <p class="text-dark-400 text-sm">Receive notifications via email</p>
                                </div>
                                <input 
                                    v-model="notificationForm.email_notifications" 
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600"
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white">Push Notifications</p>
                                    <p class="text-dark-400 text-sm">Receive push notifications in browser</p>
                                </div>
                                <input 
                                    v-model="notificationForm.push_notifications" 
                                    type="checkbox" 
                                    class="w-5 h-5 rounded bg-dark-700 border-dark-600"
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white">Subscription Updates</p>
                                    <p class="text-dark-400 text-sm">Get notified when channels you subscribe to upload</p>
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
                        <h2 class="text-lg font-semibold text-white mb-4">Privacy Settings</h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white">Private Profile</p>
                                    <p class="text-dark-400 text-sm">Only approved followers can see your content</p>
                                </div>
                                <input type="checkbox" class="w-5 h-5 rounded bg-dark-700 border-dark-600" />
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white">Show Watch History</p>
                                    <p class="text-dark-400 text-sm">Allow others to see what you've watched</p>
                                </div>
                                <input type="checkbox" class="w-5 h-5 rounded bg-dark-700 border-dark-600" />
                            </div>
                        </div>
                    </div>

                    <!-- Billing Tab -->
                    <div v-if="activeTab === 'billing'" class="card p-6">
                        <h2 class="text-lg font-semibold text-white mb-4">Billing & Subscription</h2>
                        <div class="space-y-4">
                            <div class="p-4 bg-dark-800 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-white font-medium">Current Plan</p>
                                        <p class="text-dark-400">{{ user?.is_pro ? 'Pro' : 'Free' }}</p>
                                    </div>
                                    <button v-if="!user?.is_pro" class="btn btn-primary">
                                        Upgrade to Pro
                                    </button>
                                </div>
                            </div>
                            <div class="p-4 bg-dark-800 rounded-lg">
                                <p class="text-white font-medium mb-2">Wallet Balance</p>
                                <p class="text-2xl font-bold text-primary-500">${{ user?.wallet_balance || '0.00' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
