<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    shorts: {
        type: Array,
        required: true,
    },
});

const { localizedUrl } = useI18n();
</script>

<template>
    <section v-if="shorts?.length" class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-text-primary">{{ $t('home.shorts') || 'Shorts' }}</h2>
            <Link :href="localizedUrl('/shorts')" class="text-sm font-medium text-accent hover:opacity-80">
                {{ $t('common.view_all') || 'View All' }}
            </Link>
        </div>

        <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-2 -mx-3 px-3 sm:-mx-4 sm:px-4 lg:-mx-6 lg:px-6">
            <Link
                v-for="video in shorts"
                :key="video.id"
                :href="localizedUrl(`/shorts/${video.uuid}`)"
                class="shrink-0 relative group"
            >
                <div class="w-[140px] h-[250px] sm:w-[160px] sm:h-[285px] rounded-xl overflow-hidden bg-black border border-border">
                    <img
                        :src="video.thumbnail_url || '/images/default_avatar.webp'"
                        :alt="video.title"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                    <div class="absolute bottom-2 left-2 right-2">
                        <p class="text-xs font-medium text-white line-clamp-2">{{ video.title }}</p>
                        <p class="text-[11px] text-white/80 mt-0.5">{{ video.user?.username }}</p>
                    </div>
                </div>
            </Link>
        </div>
    </section>
</template>
