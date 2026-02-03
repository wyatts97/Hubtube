<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
});

const formattedViews = computed(() => {
    const views = props.video.views_count;
    if (views >= 1000000) {
        return (views / 1000000).toFixed(1) + 'M';
    }
    if (views >= 1000) {
        return (views / 1000).toFixed(1) + 'K';
    }
    return views.toString();
});

const timeAgo = computed(() => {
    const date = new Date(props.video.published_at || props.video.created_at);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    const intervals = [
        { label: 'year', seconds: 31536000 },
        { label: 'month', seconds: 2592000 },
        { label: 'week', seconds: 604800 },
        { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 },
        { label: 'minute', seconds: 60 },
    ];

    for (const interval of intervals) {
        const count = Math.floor(seconds / interval.seconds);
        if (count >= 1) {
            return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
        }
    }
    return 'Just now';
});
</script>

<template>
    <Link :href="`/watch/${video.slug}`" class="video-card">
        <div class="thumbnail">
            <img
                :src="video.thumbnail_url || '/images/placeholder.jpg'"
                :alt="video.title"
                loading="lazy"
            />
            <span class="duration">{{ video.formatted_duration }}</span>
            <span v-if="video.is_short" class="absolute top-2 left-2 badge badge-pro">Short</span>
        </div>
        <div class="flex gap-3 mt-3">
            <Link v-if="video.user" :href="`/channel/${video.user.username}`" class="flex-shrink-0">
                <div class="w-9 h-9 avatar">
                    <img v-if="video.user.avatar" :src="video.user.avatar" :alt="video.user.username" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-sm font-medium">
                        {{ video.user.username?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                </div>
            </Link>
            <div class="flex-1 min-w-0">
                <h3 class="font-medium text-white line-clamp-2 leading-tight">{{ video.title }}</h3>
                <Link v-if="video.user" :href="`/channel/${video.user.username}`" class="text-sm text-dark-400 hover:text-dark-300 mt-1 block">
                    {{ video.user.username }}
                    <span v-if="video.user.is_verified" class="inline-block ml-1">✓</span>
                </Link>
                <p class="text-sm text-dark-500">
                    {{ formattedViews }} views • {{ timeAgo }}
                </p>
            </div>
        </div>
    </Link>
</template>
