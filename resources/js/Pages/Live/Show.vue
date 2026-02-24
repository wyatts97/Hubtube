<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Users, Heart, Send, Gift } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    stream: Object,
    agoraAppId: String,
    agoraToken: String,
    isSubscribed: Boolean,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const messages = ref([]);
const messageInput = ref('');
const showGiftPanel = ref(false);
const gifts = ref([]);
const viewerCount = ref(props.stream.viewer_count);

let agoraClient = null;
let rtmClient = null;
let rtmChannel = null;

onMounted(async () => {
    await loadGifts();
    await initAgora();
});

onUnmounted(() => {
    cleanup();
});

const loadGifts = async () => {
    try {
        const response = await fetch('/gifts');
        if (!response.ok) {
            console.warn('[Live] Failed to load gifts:', response.status);
            return;
        }
        const data = await response.json();
        gifts.value = Array.isArray(data) ? data : (data?.data ?? []);
    } catch (error) {
        console.error('[Live] Failed to load gifts:', error);
        gifts.value = [];
    }
};

const initAgora = async () => {
    if (!props.agoraAppId || !props.agoraToken) return;

    try {
        const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;
        
        agoraClient = AgoraRTC.createClient({ mode: 'live', codec: 'vp8' });
        agoraClient.setClientRole('audience');

        agoraClient.on('user-published', async (remoteUser, mediaType) => {
            await agoraClient.subscribe(remoteUser, mediaType);
            
            if (mediaType === 'video') {
                const videoTrack = remoteUser.videoTrack;
                videoTrack.play('video-player');
            }
            
            if (mediaType === 'audio') {
                const audioTrack = remoteUser.audioTrack;
                audioTrack.play();
            }
        });

        await agoraClient.join(props.agoraAppId, props.stream.channel_name, props.agoraToken, user.value?.id || 0);

        await initRTM();
    } catch (error) {
        console.error('Failed to initialize Agora:', error);
    }
};

const initRTM = async () => {
    try {
        const AgoraRTM = (await import('agora-rtm-sdk')).default;
        
        rtmClient = AgoraRTM.createInstance(props.agoraAppId);
        await rtmClient.login({ uid: String(user.value?.id || Math.random().toString(36).substr(2, 9)) });
        
        rtmChannel = rtmClient.createChannel(props.stream.channel_name);
        await rtmChannel.join();

        rtmChannel.on('ChannelMessage', ({ text }, senderId) => {
            let message;
            try {
                message = JSON.parse(text);
            } catch {
                console.warn('[Live] Invalid RTM message:', text?.slice?.(0, 100));
                return;
            }
            handleRTMMessage(message, senderId);
        });
    } catch (error) {
        console.error('Failed to initialize RTM:', error);
    }
};

const handleRTMMessage = (message, senderId) => {
    if (message.type === 'chat') {
        messages.value.push({
            id: Date.now(),
            username: message.username,
            text: message.text,
        });
        
        if (messages.value.length > 100) {
            messages.value.shift();
        }
    } else if (message.type === 'gift') {
        showGiftAnimation(message);
    }
};

const showGiftAnimation = (giftMessage) => {
    console.log('Gift received:', giftMessage);
};

const sendMessage = async () => {
    if (!messageInput.value.trim() || !rtmChannel || !user.value) return;

    const message = {
        type: 'chat',
        username: user.value.username,
        text: messageInput.value.trim(),
    };

    await rtmChannel.sendMessage({ text: JSON.stringify(message) });
    messages.value.push({
        id: Date.now(),
        username: user.value.username,
        text: message.text,
    });
    messageInput.value = '';
};

const sendGift = async (gift) => {
    if (!user.value) return;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/live/${props.stream.id}/gift`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({ gift_id: gift.id }),
        });

        const data = await response.json();
        
        if (data.success && rtmChannel) {
            const giftMessage = {
                type: 'gift',
                username: user.value.username,
                gift: gift,
            };
            await rtmChannel.sendMessage({ text: JSON.stringify(giftMessage) });
        }
        
        showGiftPanel.value = false;
    } catch (error) {
        console.error('Failed to send gift:', error);
    }
};

const cleanup = async () => {
    if (rtmChannel) {
        await rtmChannel.leave();
    }
    if (rtmClient) {
        await rtmClient.logout();
    }
    if (agoraClient) {
        await agoraClient.leave();
    }
};
</script>

<template>
    <Head :title="stream.title" />

    <AppLayout>
        <div class="flex flex-col lg:flex-row gap-4 lg:h-[calc(100vh-8rem)]">
            <!-- Video Player -->
            <div class="flex-1 flex flex-col">
                <div class="relative aspect-video bg-black rounded-xl overflow-hidden">
                    <div id="video-player" class="w-full h-full"></div>
                    
                    <!-- Overlay Info -->
                    <div class="absolute top-4 left-4 flex items-center gap-2">
                        <span class="badge badge-live">LIVE</span>
                        <span class="flex items-center gap-1 px-2 py-1 bg-black/60 rounded text-sm">
                            <Users class="w-4 h-4" />
                            {{ viewerCount }}
                        </span>
                    </div>
                </div>

                <!-- Stream Info -->
                <div class="mt-4">
                    <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">{{ stream.title }}</h1>
                    <div class="flex items-center gap-4 mt-3">
                        <Link :href="`/channel/${stream.user.username}`" class="flex items-center gap-3">
                            <div class="w-10 h-10 avatar ring-2 ring-red-500">
                                <img :src="stream.user.avatar_url || stream.user.avatar || '/images/default_avatar.webp'" :alt="stream.user.username" class="w-full h-full object-cover" />
                            </div>
                            <div>
                                <p class="font-medium" style="color: var(--color-text-primary);">{{ stream.user.username }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="w-full lg:w-96 flex flex-col card max-h-[60vh] lg:max-h-none">
                <div class="p-3 sm:p-4" style="border-bottom: 1px solid var(--color-border);">
                    <h3 class="font-medium" style="color: var(--color-text-primary);">{{ t('live.live_chat') || 'Live Chat' }}</h3>
                </div>

                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2 min-h-[200px]">
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="text-sm"
                    >
                        <span class="font-medium" style="color: var(--color-accent);">{{ msg.username }}:</span>
                        <span class="ml-1" style="color: var(--color-text-secondary);">{{ msg.text }}</span>
                    </div>
                    <p v-if="messages.length === 0" class="text-center py-8" style="color: var(--color-text-muted);">
                        No messages yet. Be the first to chat!
                    </p>
                </div>

                <!-- Gift Panel -->
                <div v-if="showGiftPanel" class="p-4" style="border-top: 1px solid var(--color-border); background-color: var(--color-bg-secondary);">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium" style="color: var(--color-text-primary);">{{ t('live.send_gift') || 'Send a Gift' }}</h4>
                        <button @click="showGiftPanel = false" class="hover:opacity-80" style="color: var(--color-text-secondary);">×</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="gift in gifts"
                            :key="gift.id"
                            @click="sendGift(gift)"
                            class="flex flex-col items-center p-2 rounded-lg transition-colors hover:opacity-80"
                        >
                            <span class="text-2xl">{{ gift.icon }}</span>
                            <span class="text-xs mt-1" style="color: var(--color-text-muted);">${{ gift.price }}</span>
                        </button>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="p-4" style="border-top: 1px solid var(--color-border);">
                    <div class="flex items-center gap-2">
                        <button
                            v-if="user"
                            @click="showGiftPanel = !showGiftPanel"
                            class="p-2 rounded-full text-amber-400 hover:opacity-80"
                        >
                            <Gift class="w-5 h-5" />
                        </button>
                        <input
                            v-model="messageInput"
                            type="text"
                            placeholder="Send a message..."
                            class="input flex-1"
                            :disabled="!user"
                            @keydown.enter="sendMessage"
                        />
                        <button
                            @click="sendMessage"
                            :disabled="!user || !messageInput.trim()"
                            class="btn btn-primary p-2"
                        >
                            <Send class="w-5 h-5" />
                        </button>
                    </div>
                    <p v-if="!user" class="text-xs mt-2 text-center" style="color: var(--color-text-muted);">
                        <Link href="/login" style="color: var(--color-accent);">Sign in</Link> to chat
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
