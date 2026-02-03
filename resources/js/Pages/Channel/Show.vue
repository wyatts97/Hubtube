<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VideoCard from '@/Components/VideoCard.vue';
import { Bell, BellOff, Share2, Flag } from 'lucide-vue-next';

const props = defineProps({
    channel: Object,
    videos: Object,
    isSubscribed: Boolean,
    subscriberCount: Number,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const subscribed = ref(props.isSubscribed);
const subCount = ref(props.subscriberCount);

const handleSubscribe = async () => {
    if (!user.value) {
        router.visit('/login');
        return;
    }
    
    const method = subscribed.value ? 'DELETE' : 'POST';
    await fetch(`/channel/${props.channel.username}/subscribe`, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
    });
    
    if (subscribed.value) {
        subCount.value--;
    } else {
        subCount.value++;
    }
    subscribed.value = !subscribed.value;
};

const tabs = [
    { name: 'Videos', href: `/channel/${props.channel.username}` },
    { name: 'Shorts', href: `/channel/${props.channel.username}/shorts` },
    { name: 'Playlists', href: `/channel/${props.channel.username}/playlists` },
    { name: 'About', href: `/channel/${props.channel.username}/about` },
];
</script>

<template>
    <Head :title="channel.username" />

    <AppLayout>
        <!-- Channel Banner -->
        <div class="relative h-48 md:h-64 bg-gradient-to-r from-primary-600 to-primary-800 rounded-xl overflow-hidden mb-6">
            <img 
                v-if="channel.channel?.banner" 
                :src="channel.channel.banner" 
                :alt="channel.username"
                class="w-full h-full object-cover"
            />
        </div>

        <!-- Channel Info -->
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6 mb-8">
            <div class="w-24 h-24 md:w-32 md:h-32 avatar flex-shrink-0">
                <img 
                    v-if="channel.avatar" 
                    :src="channel.avatar" 
                    :alt="channel.username"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-4xl font-bold">
                    {{ channel.username?.charAt(0)?.toUpperCase() }}
                </div>
            </div>
            
            <div class="flex-1">
                <h1 class="text-2xl md:text-3xl font-bold text-white">
                    {{ channel.username }}
                    <span v-if="channel.is_verified" class="text-primary-500 ml-2">✓</span>
                </h1>
                <p class="text-dark-400 mt-1">
                    @{{ channel.username }} • {{ subCount.toLocaleString() }} subscribers • {{ videos.total }} videos
                </p>
                <p v-if="channel.channel?.description" class="text-dark-300 mt-2 line-clamp-2">
                    {{ channel.channel.description }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    v-if="user && user.id !== channel.id"
                    @click="handleSubscribe"
                    :class="[
                        'btn',
                        subscribed ? 'btn-secondary' : 'btn-primary'
                    ]"
                >
                    {{ subscribed ? 'Subscribed' : 'Subscribe' }}
                </button>
                <button class="btn btn-secondary">
                    <Share2 class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-dark-800 mb-6">
            <nav class="flex gap-6">
                <Link
                    v-for="tab in tabs"
                    :key="tab.name"
                    :href="tab.href"
                    class="pb-3 px-1 text-dark-400 hover:text-white border-b-2 border-transparent hover:border-primary-500 transition-colors"
                >
                    {{ tab.name }}
                </Link>
            </nav>
        </div>

        <!-- Videos Grid -->
        <div v-if="videos.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <VideoCard
                v-for="video in videos.data"
                :key="video.id"
                :video="video"
            />
        </div>

        <div v-else class="text-center py-12">
            <p class="text-dark-400 text-lg">No videos yet</p>
            <p class="text-dark-500 mt-2">This channel hasn't uploaded any videos</p>
        </div>

        <!-- Pagination -->
        <div v-if="videos.links && videos.links.length > 3" class="mt-8 flex justify-center gap-2">
            <template v-for="link in videos.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm',
                        link.active 
                            ? 'bg-primary-600 text-white' 
                            : 'bg-dark-800 text-dark-300 hover:bg-dark-700'
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
            </template>
        </div>
    </AppLayout>
</template>
