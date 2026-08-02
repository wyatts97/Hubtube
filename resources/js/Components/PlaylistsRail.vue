<script setup>
import { Link } from '@inertiajs/vue3';
import { ListVideo } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    playlists: {
        type: Array,
        required: true,
    },
});

const { t, localizedUrl } = useI18n();
</script>

<template>
    <section v-if="playlists?.length" class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-text-primary">{{ t('home.playlists') || 'Playlists' }}</h2>
            <Link :href="localizedUrl('/public-playlists')" class="text-sm font-medium text-accent hover:opacity-80">
                {{ t('common.view_all') || 'View All' }}
            </Link>
        </div>

        <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-2 -mx-3 px-3 sm:-mx-4 sm:px-4 lg:-mx-6 lg:px-6">
            <Link
                v-for="playlist in playlists"
                :key="playlist.id"
                :href="localizedUrl(`/playlist/${playlist.slug}`)"
                class="shrink-0 w-[240px] sm:w-[280px] group"
            >
                <div class="relative aspect-video rounded-xl overflow-hidden bg-bg-secondary border border-border">
                    <img
                        v-if="playlist.thumbnail"
                        :src="playlist.thumbnail"
                        :alt="playlist.title"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <ListVideo class="w-12 h-12 text-text-muted" />
                    </div>
                    <div class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-medium bg-black/80 text-white">
                        {{ playlist.video_count }} {{ playlist.video_count === 1 ? 'video' : 'videos' }}
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                    <div class="absolute bottom-2 left-2 right-2">
                        <p class="text-sm font-medium text-white line-clamp-2">{{ playlist.title }}</p>
                        <p v-if="playlist.user" class="text-xs text-white/80 mt-0.5">{{ playlist.user.username }}</p>
                    </div>
                </div>
            </Link>
        </div>
    </section>
</template>
