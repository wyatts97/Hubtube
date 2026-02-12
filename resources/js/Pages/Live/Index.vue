<script setup>
import { Link } from '@inertiajs/vue3';
import SeoHead from '@/Components/SeoHead.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Radio, Users } from 'lucide-vue-next';
import { useI18n } from '@/Composables/useI18n';

const { t } = useI18n();

defineProps({
    streams: Object,
    seo: { type: Object, default: () => ({}) },
});
</script>

<template>
    <SeoHead :seo="seo" />

    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">{{ t('live.title') || 'Live Streams' }}</h1>
            <p class="text-dark-400 mt-1">{{ t('live.watch_now') || 'Watch creators streaming live right now' }}</p>
        </div>

        <div v-if="streams.data?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <Link
                v-for="stream in streams.data"
                :key="stream.id"
                :href="`/live/${stream.id}`"
                class="card overflow-hidden hover:ring-2 hover:ring-primary-500 transition-all"
            >
                <div class="aspect-video bg-dark-800 relative">
                    <img 
                        v-if="stream.thumbnail" 
                        :src="stream.thumbnail" 
                        :alt="stream.title"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <Radio class="w-12 h-12 text-dark-600" />
                    </div>
                    <div class="absolute top-2 left-2 flex items-center gap-1 bg-red-600 text-white text-xs font-medium px-2 py-1 rounded">
                        <Radio class="w-3 h-3" />
                        LIVE
                    </div>
                    <div class="absolute bottom-2 right-2 flex items-center gap-1 bg-black/70 text-white text-xs px-2 py-1 rounded">
                        <Users class="w-3 h-3" />
                        {{ stream.viewer_count?.toLocaleString() || 0 }}
                    </div>
                </div>
                <div class="p-3">
                    <div class="flex gap-3">
                        <div class="w-9 h-9 avatar shrink-0">
                            <img 
                                :src="stream.user?.avatar_url || stream.user?.avatar || '/images/default_avatar.webp'" 
                                :alt="stream.user?.username"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-white truncate">{{ stream.title }}</h3>
                            <p class="text-sm text-dark-400">{{ stream.user?.username }}</p>
                        </div>
                    </div>
                </div>
            </Link>
        </div>

        <div v-else class="text-center py-12">
            <Radio class="w-16 h-16 text-dark-600 mx-auto mb-4" />
            <p class="text-dark-400 text-lg">{{ t('live.no_streams') || 'No live streams right now' }}</p>
            <p class="text-dark-500 mt-2">{{ t('live.check_back') || 'Check back later or start your own stream!' }}</p>
            <Link href="/go-live" class="btn btn-primary mt-4">
                {{ t('nav.go_live') || 'Go Live' }}
            </Link>
        </div>
    </AppLayout>
</template>
