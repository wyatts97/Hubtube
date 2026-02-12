<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Users } from 'lucide-vue-next';

const props = defineProps({
    stream: {
        type: Object,
        required: true,
    },
});

const formattedViewers = computed(() => {
    const viewers = props.stream.viewer_count;
    if (viewers >= 1000) {
        return (viewers / 1000).toFixed(1) + 'K';
    }
    return viewers.toString();
});
</script>

<template>
    <Link :href="`/live/${stream.id}`" class="video-card">
        <div class="thumbnail">
            <img
                :src="stream.thumbnail || `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23181818' width='640' height='360'/%3E%3Ctext x='320' y='185' text-anchor='middle' fill='%23444' font-size='18'%3ELIVE%3C/text%3E%3C/svg%3E`"
                :alt="stream.title"
                loading="lazy"
            />
            <span class="absolute top-2 left-2 badge badge-live">LIVE</span>
            <div class="absolute bottom-2 left-2 flex items-center gap-1 px-2 py-1 bg-black/80 rounded text-xs">
                <Users class="w-3 h-3" />
                <span>{{ formattedViewers }}</span>
            </div>
        </div>
        <div class="flex gap-3 mt-3">
            <Link :href="`/channel/${stream.user.username}`" class="flex-shrink-0">
                <div class="w-9 h-9 avatar ring-2 ring-red-500">
                    <img :src="stream.user.avatar_url || stream.user.avatar || '/images/default_avatar.webp'" :alt="stream.user.username" class="w-full h-full object-cover" />
                </div>
            </Link>
            <div class="flex-1 min-w-0">
                <h3 class="font-medium text-white line-clamp-2 leading-tight">{{ stream.title }}</h3>
                <Link :href="`/channel/${stream.user.username}`" class="text-sm text-dark-400 hover:text-dark-300 mt-1 block">
                    {{ stream.user.username }}
                </Link>
            </div>
        </div>
    </Link>
</template>
