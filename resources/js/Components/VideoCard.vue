<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
});

const isHovering = ref(false);
const previewLoaded = ref(false);

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

const formattedDuration = computed(() => {
    const duration = props.video.duration || 0;
    const hours = Math.floor(duration / 3600);
    const minutes = Math.floor((duration % 3600) / 60);
    const seconds = duration % 60;
    
    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
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

const handleMouseEnter = () => {
    isHovering.value = true;
};

const handleMouseLeave = () => {
    isHovering.value = false;
};

const onPreviewLoad = () => {
    previewLoaded.value = true;
};
</script>

<template>
    <Link 
        :href="`/watch/${video.slug}`" 
        class="video-card"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <div class="thumbnail relative overflow-hidden rounded-xl">
            <!-- Static Thumbnail -->
            <img
                :src="video.thumbnail_url || '/images/placeholder.jpg'"
                :alt="video.title"
                loading="lazy"
                class="w-full h-full object-cover transition-opacity duration-200"
                :class="{ 'opacity-0': isHovering && video.preview_url && previewLoaded }"
            />
            
            <!-- Animated Preview (WebP) -->
            <img
                v-if="video.preview_url"
                :src="isHovering ? video.preview_url : ''"
                :alt="video.title"
                class="absolute inset-0 w-full h-full object-cover transition-opacity duration-200"
                :class="isHovering && previewLoaded ? 'opacity-100' : 'opacity-0'"
                @load="onPreviewLoad"
            />
            
            <!-- Duration Badge -->
            <span class="duration absolute bottom-2 right-2 bg-black/80 text-white text-xs font-medium px-1.5 py-0.5 rounded">
                {{ video.formatted_duration || formattedDuration }}
            </span>
            
            <!-- Short Badge -->
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
