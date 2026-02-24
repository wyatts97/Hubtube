<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Video, Mic, MicOff, VideoOff, Settings, Users, DollarSign } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

const page = usePage();
const user = computed(() => page.props.auth.user);

const title = ref('');
const description = ref('');
const isLive = ref(false);
const isPreviewing = ref(false);
const viewerCount = ref(0);
const totalGifts = ref(0);

const videoEnabled = ref(true);
const audioEnabled = ref(true);

let agoraClient = null;
let localVideoTrack = null;
let localAudioTrack = null;
let streamData = null;

const startPreview = async () => {
    try {
        const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;
        
        localVideoTrack = await AgoraRTC.createCameraVideoTrack();
        localAudioTrack = await AgoraRTC.createMicrophoneAudioTrack();
        
        localVideoTrack.play('local-video');
        isPreviewing.value = true;
    } catch (error) {
        console.error('Failed to start preview:', error);
        alert('Failed to access camera/microphone. Please check permissions.');
    }
};

const goLive = async () => {
    if (!title.value.trim()) {
        alert('Please enter a title for your stream');
        return;
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/go-live', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                title: title.value,
                description: description.value,
            }),
        });

        streamData = await response.json();

        const AgoraRTC = (await import('agora-rtc-sdk-ng')).default;
        
        agoraClient = AgoraRTC.createClient({ mode: 'live', codec: 'vp8' });
        agoraClient.setClientRole('host');

        await agoraClient.join(
            streamData.agoraAppId,
            streamData.channelName,
            streamData.agoraToken,
            user.value.id
        );

        await agoraClient.publish([localVideoTrack, localAudioTrack]);

        await fetch(`/live/${streamData.stream.id}/start`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken || '',
            },
        });

        isLive.value = true;
    } catch (error) {
        console.error('Failed to go live:', error);
        alert('Failed to start stream. Please try again.');
    }
};

const endStream = async () => {
    if (!confirm('Are you sure you want to end the stream?')) return;

    try {
        if (streamData) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(`/live/${streamData.stream.id}/end`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                },
            });
        }

        await cleanup();
        router.visit('/');
    } catch (error) {
        console.error('Failed to end stream:', error);
    }
};

const toggleVideo = () => {
    if (localVideoTrack) {
        videoEnabled.value = !videoEnabled.value;
        localVideoTrack.setEnabled(videoEnabled.value);
    }
};

const toggleAudio = () => {
    if (localAudioTrack) {
        audioEnabled.value = !audioEnabled.value;
        localAudioTrack.setEnabled(audioEnabled.value);
    }
};

const cleanup = async () => {
    if (localVideoTrack) {
        localVideoTrack.stop();
        localVideoTrack.close();
    }
    if (localAudioTrack) {
        localAudioTrack.stop();
        localAudioTrack.close();
    }
    if (agoraClient) {
        await agoraClient.leave();
    }
};

onUnmounted(() => {
    cleanup();
});
</script>

<template>
    <Head title="Go Live" />

    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <h1 class="text-2xl font-bold text-white mb-6">
                {{ isLive ? (t('live.you_are_live') || 'You are Live!') : (t('nav.go_live') || 'Go Live') }}
            </h1>

            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Video Preview -->
                <div class="flex-1">
                    <div class="aspect-video bg-dark-900 rounded-xl overflow-hidden relative">
                        <div id="local-video" class="w-full h-full"></div>
                        
                        <div v-if="!isPreviewing" class="absolute inset-0 flex items-center justify-center">
                            <button @click="startPreview" class="btn btn-primary gap-2">
                                <Video class="w-5 h-5" />
                                {{ t('live.start_preview') || 'Start Camera Preview' }}
                            </button>
                        </div>

                        <!-- Live Indicator -->
                        <div v-if="isLive" class="absolute top-4 left-4 flex items-center gap-3">
                            <span class="badge badge-live">LIVE</span>
                            <span class="flex items-center gap-1 px-2 py-1 bg-black/60 rounded text-sm">
                                <Users class="w-4 h-4" />
                                {{ viewerCount }}
                            </span>
                            <span class="flex items-center gap-1 px-2 py-1 bg-black/60 rounded text-sm text-amber-400">
                                <DollarSign class="w-4 h-4" />
                                {{ totalGifts.toFixed(2) }}
                            </span>
                        </div>

                        <!-- Controls -->
                        <div v-if="isPreviewing" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2">
                            <button
                                @click="toggleAudio"
                                :class="[
                                    'p-3 rounded-full transition-colors',
                                    audioEnabled ? 'bg-dark-700 hover:bg-dark-600' : 'bg-red-600 hover:bg-red-700'
                                ]"
                            >
                                <Mic v-if="audioEnabled" class="w-5 h-5" />
                                <MicOff v-else class="w-5 h-5" />
                            </button>
                            <button
                                @click="toggleVideo"
                                :class="[
                                    'p-3 rounded-full transition-colors',
                                    videoEnabled ? 'bg-dark-700 hover:bg-dark-600' : 'bg-red-600 hover:bg-red-700'
                                ]"
                            >
                                <Video v-if="videoEnabled" class="w-5 h-5" />
                                <VideoOff v-else class="w-5 h-5" />
                            </button>
                            <button class="p-3 rounded-full bg-dark-700 hover:bg-dark-600">
                                <Settings class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stream Settings -->
                <div class="lg:w-96">
                    <div class="card p-6 space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-dark-300 mb-1">
                                {{ t('live.stream_title') || 'Stream Title' }}
                            </label>
                            <input
                                id="title"
                                v-model="title"
                                type="text"
                                class="input"
                                placeholder="Enter stream title..."
                                :disabled="isLive"
                                maxlength="200"
                            />
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-dark-300 mb-1">
                                {{ t('live.description_optional') || 'Description (optional)' }}
                            </label>
                            <textarea
                                id="description"
                                v-model="description"
                                rows="3"
                                class="input resize-none"
                                placeholder="Tell viewers about your stream..."
                                :disabled="isLive"
                            ></textarea>
                        </div>

                        <div v-if="!isLive" class="pt-4">
                            <button
                                @click="goLive"
                                :disabled="!isPreviewing || !title.trim()"
                                class="btn btn-primary w-full py-3"
                            >
                                {{ t('nav.go_live') || 'Go Live' }}
                            </button>
                        </div>

                        <div v-else class="pt-4">
                            <button
                                @click="endStream"
                                class="btn bg-red-600 hover:bg-red-700 text-white w-full py-3"
                            >
                                {{ t('live.end_stream') || 'End Stream' }}
                            </button>
                        </div>
                    </div>

                    <!-- Tips -->
                    <div v-if="!isLive" class="card p-6 mt-4">
                        <h3 class="font-medium text-white mb-3">Tips for a great stream</h3>
                        <ul class="space-y-2 text-sm text-dark-400">
                            <li>• Ensure good lighting on your face</li>
                            <li>• Use a stable internet connection</li>
                            <li>• Engage with your viewers in chat</li>
                            <li>• Set up a clear and descriptive title</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
