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
                :src="stream.thumbnail || '/images/live-placeholder.jpg'"
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
                    <img v-if="stream.user.avatar" :src="stream.user.avatar" :alt="stream.user.username" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center bg-primary-600 text-white text-sm font-medium">
                        {{ stream.user.username.charAt(0).toUpperCase() }}
                    </div>
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
