<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { timeAgo as timeAgoFn, formatViews } from '@/Composables/useFormatters';
import { useOptimizedImage } from '@/Composables/useOptimizedImage';
import { useI18n } from '@/Composables/useI18n';
import { useTranslation } from '@/Composables/useTranslation';

const { thumbnailProps, avatarProps } = useOptimizedImage();
const { localizedUrl, locale, t, isTranslated } = useI18n();
const { getTranslated } = useTranslation();
const page = usePage();

const props = defineProps({
    video: {
        type: Object,
        required: true,
    },
});

const vc = computed(() => page.props.theme?.videoCard || {});

const isHovering = ref(false);
const previewLoaded = ref(false);

const placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23181818' width='640' height='360'/%3E%3Cpolygon fill='%23333' points='280,130 280,230 360,180'/%3E%3C/svg%3E";

const videoUrl = computed(() => {
    if (props.video.is_short) {
        return `/shorts/${props.video.id}`;
    }
    // Use translated slug for locale-prefixed URLs (SEO: /pt/apertado instead of /pt/wedgied)
    const slug = (isTranslated.value && props.video.translated_slug) ? props.video.translated_slug : props.video.slug;
    return localizedUrl(`/${slug}`);
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

const timeAgo = computed(() => timeAgoFn(props.video.published_at || props.video.created_at, locale.value));

const showAvatar = computed(() => vc.value.showAvatar !== false);
const showUploader = computed(() => vc.value.showUploader !== false);
const showViews = computed(() => vc.value.showViews !== false);
const showDuration = computed(() => vc.value.showDuration !== false);
const showTimestamp = computed(() => vc.value.showTimestamp !== false);

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
const uploaderColor = computed(() => vc.value.metaColor || 'var(--color-text-secondary)');

const thumbRadius = computed(() => {
    const r = vc.value.borderRadius;
    return r !== undefined && r !== null ? `${r}px` : '12px';
});

const handleMouseEnter = () => { isHovering.value = true; };
const handleMouseLeave = () => { isHovering.value = false; };
const onPreviewLoad = () => { previewLoaded.value = true; };
</script>

<template>
    <Link 
        v-motion
        :initial="{ opacity: 0, y: 8 }"
        :enter="{ opacity: 1, y: 0, transition: { duration: 0.2 } }"
        :href="videoUrl" 
        class="video-card"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <div class="thumbnail relative overflow-hidden" :style="{ borderRadius: thumbRadius }">
            <!-- Static Thumbnail -->
            <img
                v-bind="thumbnailProps(video.thumbnail_url || video.thumbnail || placeholderImg, video.title)"
                class="w-full h-full object-cover transition-opacity duration-200"
                :class="{ 'opacity-0': isHovering && video.preview_url && previewLoaded }"
                @error="(e) => e.target.src = placeholderImg"
            />
            
            <!-- Animated Preview (WebP) -->
            <img
                v-if="video.preview_url"
                :src="isHovering ? video.preview_url : ''"
                :alt="video.title"
                class="absolute inset-0 w-full h-full object-cover transition-opacity duration-200"
                :class="isHovering && previewLoaded ? 'opacity-100' : 'opacity-0'"
                loading="lazy"
                decoding="async"
                @load="onPreviewLoad"
            />
            
            <!-- Duration Badge -->
            <span v-if="showDuration" class="duration absolute bottom-2 right-2 bg-black/80 text-white text-xs font-medium px-1.5 py-0.5 rounded">
                {{ video.duration_formatted || video.formatted_duration || formattedDuration }}
            </span>
            
        </div>
        <div class="flex gap-3 mt-3">
            <Link v-if="showAvatar && video.user" :href="localizedUrl(`/channel/${video.user.username}`)" class="shrink-0">
                <div class="w-9 h-9 avatar">
                    <img v-bind="avatarProps(video.user.avatar_url || video.user.avatar || '/images/default_avatar.webp', 36)" :alt="video.user.username || video.user.name" class="w-full h-full object-cover" />
                </div>
            </Link>
            <div v-else-if="showAvatar" class="shrink-0">
                <div class="w-9 h-9 avatar">
                    <img src="/images/default_avatar.webp" alt="User" class="w-full h-full object-cover" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="font-medium leading-tight" :class="`line-clamp-${vc.titleLines || 2}`" :style="titleStyle">{{ isTranslated ? getTranslated('video', video.id, 'title', video.title) : video.title }}</h3>
                <Link v-if="showUploader && video.user" :href="localizedUrl(`/channel/${video.user.username}`)" class="mt-1 block" :style="{ ...metaStyle, color: uploaderColor }">
                    {{ video.user.username }}
                    <span v-if="video.user.is_verified" class="inline-block ml-1">✓</span>
                </Link>
                <p v-if="showViews || showTimestamp" :style="{ ...metaStyle, color: metaColor }">
                    <template v-if="showViews">{{ t('video.views', { count: formattedViews }) }}</template>
                    <template v-if="showViews && showTimestamp"> • </template>
                    <template v-if="showTimestamp">{{ timeAgo }}</template>
                </p>
            </div>
        </div>
    </Link>
</template>
