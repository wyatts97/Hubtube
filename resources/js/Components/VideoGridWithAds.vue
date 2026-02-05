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

// Check if ads are enabled (handle both boolean and string values)
const adsEnabled = computed(() => {
    const enabled = props.adSettings?.videoGridEnabled;
    return enabled === true || enabled === 'true' || enabled === 1 || enabled === '1';
});

const adCode = computed(() => props.adSettings?.videoGridCode || '');

// Compute items with ads inserted at the right frequency
const itemsWithAds = computed(() => {
    if (!adsEnabled.value || !adCode.value.trim()) {
        return props.videos.map((video, index) => ({
            type: 'video',
            data: video,
            key: `${props.keyPrefix}-${video.id || index}`,
        }));
    }

    const frequency = parseInt(props.adSettings?.videoGridFrequency) || 8;
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
                key: `${props.keyPrefix}-ad-${adCount++}`,
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
                class="ad-slot col-span-1 flex items-center justify-center rounded-xl overflow-hidden p-4"
                style="min-height: 280px; background-color: var(--color-bg-card); border: 1px solid var(--color-border);"
            >
                <div v-html="adCode" class="flex items-center justify-center"></div>
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
