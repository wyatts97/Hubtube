<script setup>
import { computed } from 'vue';
import VideoCard from '@/Components/VideoCard.vue';

const props = defineProps({
    videos: {
        type: Array,
        required: true,
    },
    adSettings: {
        type: Object,
        default: () => ({
            videoGridEnabled: false,
            videoGridCode: '',
            videoGridFrequency: 8,
        }),
    },
    keyPrefix: {
        type: String,
        default: 'video',
    },
});

// Compute items with ads inserted at the right frequency
const itemsWithAds = computed(() => {
    if (!props.adSettings?.videoGridEnabled || !props.adSettings?.videoGridCode) {
        return props.videos.map((video, index) => ({
            type: 'video',
            data: video,
            key: `${props.keyPrefix}-${video.id || index}`,
        }));
    }

    const frequency = props.adSettings.videoGridFrequency || 8;
    const items = [];
    let adCount = 0;

    props.videos.forEach((video, index) => {
        items.push({
            type: 'video',
            data: video,
            key: `${props.keyPrefix}-${video.id || index}`,
        });

        // Insert ad after every X videos
        if ((index + 1) % frequency === 0 && index < props.videos.length - 1) {
            items.push({
                type: 'ad',
                key: `ad-${adCount++}`,
            });
        }
    });

    return items;
});
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <template v-for="item in itemsWithAds" :key="item.key">
            <!-- Video Card -->
            <VideoCard v-if="item.type === 'video'" :video="item.data" />
            
            <!-- Ad Slot -->
            <div 
                v-else-if="item.type === 'ad'" 
                class="ad-slot flex items-center justify-center rounded-xl overflow-hidden"
                style="min-height: 250px; background-color: var(--color-bg-card);"
            >
                <div v-html="adSettings.videoGridCode" class="w-full h-full flex items-center justify-center"></div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.ad-slot {
    aspect-ratio: auto;
}

.ad-slot :deep(img),
.ad-slot :deep(iframe) {
    max-width: 100%;
    height: auto;
}
</style>
