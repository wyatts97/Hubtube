<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { timeAgo as timeAgoFn, formatViews } from '@/Composables/useFormatters';
import { useOptimizedImage } from '@/Composables/useOptimizedImage';

const { thumbnailProps, avatarProps } = useOptimizedImage();

const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
});

const isHovering = ref(false);
const previewLoaded = ref(false);

// Check if this is an embedded video
const isEmbedded = computed(() => props.video.is_embedded === true);

// Get the correct URL for the video
const videoUrl = computed(() => {
    if (isEmbedded.value) {
        return `/embedded/${props.video.id}`;
    }
    if (props.video.is_short) {
        return `/shorts/${props.video.id}`;
    }
    return `/${props.video.slug}`;
});

const formattedViews = computed(() => formatViews(props.video.views_count));

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

const timeAgo = computed(() => timeAgoFn(props.video.published_at || props.video.created_at));

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
        :href="videoUrl" 
        class="video-card"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <div class="thumbnail relative overflow-hidden rounded-xl">
            <!-- Static Thumbnail -->
            <img
                v-bind="thumbnailProps(video.thumbnail_url || video.thumbnail || '/images/placeholder.jpg', video.title)"
                class="w-full h-full object-cover transition-opacity duration-200"
                :class="{ 'opacity-0': isHovering && video.preview_url && previewLoaded }"
                @error="(e) => e.target.src = '/images/placeholder.jpg'"
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
                {{ video.duration_formatted || video.formatted_duration || formattedDuration }}
            </span>
            
        </div>
        <div class="flex gap-3 mt-3">
            <Link v-if="video.user" :href="isEmbedded ? '#' : `/channel/${video.user.username}`" class="flex-shrink-0" @click.prevent="isEmbedded ? null : undefined">
                <div class="w-9 h-9 avatar">
                    <img v-if="video.user.avatar" v-bind="avatarProps(video.user.avatar, 36)" :alt="video.user.username || video.user.name" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-sm font-medium">
                        {{ (video.user.username || video.user.name)?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                </div>
            </Link>
            <div v-else class="flex-shrink-0">
                <div class="w-9 h-9 avatar">
                    <div class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-sm font-medium">
                        ?
                    </div>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-medium line-clamp-2 leading-tight" style="color: var(--color-text-primary);">{{ video.title }}</h3>
                <Link v-if="video.user && !isEmbedded" :href="`/channel/${video.user.username}`" class="text-sm mt-1 block" style="color: var(--color-text-secondary);">
                    {{ video.user.username }}
                    <span v-if="video.user.is_verified" class="inline-block ml-1">✓</span>
                </Link>
                <span v-else-if="video.user" class="text-sm mt-1 block" style="color: var(--color-text-secondary);">
                    {{ video.user.username || video.user.name }}
                </span>
                <p class="text-sm" style="color: var(--color-text-muted);">
                    {{ formattedViews }} views • {{ timeAgo }}
                </p>
            </div>
        </div>
    </Link>
</template>
