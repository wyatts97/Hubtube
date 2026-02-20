<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    video: {
        type: Object,
        required: true
    },
    autoplay: {
        type: Boolean,
        default: false
    },
    showInfo: {
        type: Boolean,
        default: true
    }
});

const iframeLoaded = ref(false);

const isDirectVideo = computed(() => {
    const url = props.video.embed_url || '';
    return /\.(mp4|webm|ogg|mov)(\?|$)/i.test(url);
});

const embedUrl = computed(() => {
    let url = props.video.embed_url;
    if (!url) return null;
    if (props.autoplay && !isDirectVideo.value) {
        const separator = url.includes('?') ? '&' : '?';
        url += `${separator}autoplay=1`;
    }
    return url;
});
</script>

<template>
    <div class="embedded-video-player" :class="showInfo ? '' : 'absolute inset-0'">
        <div class="relative bg-black overflow-hidden" :class="showInfo ? 'aspect-video rounded-lg' : 'w-full h-full'">
            <!-- Loading placeholder -->
            <div 
                v-if="!iframeLoaded && !isDirectVideo" 
                class="absolute inset-0 flex items-center justify-center bg-gray-900"
            >
                <div class="animate-pulse flex flex-col items-center gap-2">
                    <svg class="w-12 h-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-gray-500 text-sm">Loading video...</span>
                </div>
            </div>
            
            <!-- Direct video (mp4 etc.) -->
            <video
                v-if="isDirectVideo"
                :src="embedUrl"
                :poster="video.thumbnail_url || video.original_thumbnail_url"
                class="absolute inset-0 w-full h-full"
                controls
                :autoplay="autoplay"
                preload="metadata"
            ></video>

            <!-- Embedded iframe -->
            <iframe
                v-else-if="embedUrl"
                :src="embedUrl"
                :title="video.title || 'Embedded video'"
                class="absolute inset-0 w-full h-full"
                frameborder="0"
                allowfullscreen
                allow="autoplay; encrypted-media"
                @load="iframeLoaded = true"
            ></iframe>
        </div>
        
        <!-- Video info (can be hidden when parent renders its own info) -->
        <div v-if="showInfo" class="mt-4">
            <h1 class="text-xl font-bold" style="color: var(--color-text-primary);">
                {{ video.title }}
            </h1>
            
            <div class="flex items-center gap-4 mt-2 text-sm" style="color: var(--color-text-muted);">
                <span>{{ video.formatted_views || video.views_count?.toLocaleString() }} views</span>
                <span v-if="video.duration_formatted">{{ video.duration_formatted }}</span>
                <span class="capitalize">{{ video.source_site }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.embedded-video-player iframe {
    border: none;
}
</style>
