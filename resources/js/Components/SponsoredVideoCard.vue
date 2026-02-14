<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ExternalLink } from 'lucide-vue-next';

const page = usePage();

const props = defineProps({
    card: {
        type: Object,
        required: true,
    },
});

const vc = computed(() => page.props.theme?.videoCard || {});

const placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23181818' width='640' height='360'/%3E%3Ctext x='50%25' y='50%25' fill='%23555' font-size='24' text-anchor='middle' dominant-baseline='middle'%3EAd%3C/text%3E%3C/svg%3E";

const titleStyle = computed(() => ({
    color: vc.value.titleColor || 'var(--color-text-primary)',
    fontFamily: vc.value.titleFont || undefined,
    fontSize: vc.value.titleSize ? `${vc.value.titleSize}px` : undefined,
    '-webkit-line-clamp': vc.value.titleLines || 2,
}));

const metaStyle = computed(() => ({
    fontFamily: vc.value.metaFont || undefined,
    fontSize: vc.value.metaSize ? `${vc.value.metaSize}px` : undefined,
}));

const metaColor = computed(() => vc.value.metaColor || 'var(--color-text-muted)');

const thumbRadius = computed(() => {
    const r = vc.value.borderRadius;
    return r !== undefined && r !== null ? `${r}px` : '12px';
});
</script>

<template>
    <a
        :href="card.click_url"
        target="_blank"
        rel="noopener noreferrer sponsored"
        class="video-card"
    >
        <div class="thumbnail relative overflow-hidden" :style="{ borderRadius: thumbRadius }">
            <img
                :src="card.thumbnail_url || placeholderImg"
                :alt="card.title"
                class="w-full h-full object-cover"
                loading="lazy"
                decoding="async"
                @error="(e) => e.target.src = placeholderImg"
            />

            <!-- Sponsored Badge -->
            <span class="absolute top-2 left-2 bg-black/70 text-white text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded flex items-center gap-1">
                <ExternalLink class="w-3 h-3" />
                Sponsored
            </span>
        </div>
        <div class="flex gap-3 mt-3">
            <div class="flex-1 min-w-0">
                <h3 class="font-medium leading-tight" :class="`line-clamp-${vc.titleLines || 2}`" :style="titleStyle">{{ card.title }}</h3>
                <p v-if="card.description" class="mt-1" :style="{ ...metaStyle, color: metaColor }">
                    {{ card.description }}
                </p>
                <p v-else class="mt-1" :style="{ ...metaStyle, color: metaColor }">
                    Ad
                </p>
            </div>
        </div>
    </a>
</template>
