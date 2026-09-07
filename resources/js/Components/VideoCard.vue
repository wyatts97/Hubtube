<script setup>
/**
 * Grid card.
 *
 * Dense-tube variant: the old layout was YouTube's (large 16:9 thumb, channel
 * avatar to the left of a two-line title, "views • time ago"). This one trades
 * the avatar column for metadata density — rating bar, quality badge and tag
 * chips — because that is what people scanning a catalogue actually use.
 *
 * Every added element stays switchable from Filament Theme Settings via
 * page.props.theme.videoCard, following the pattern the original card
 * established. Operators who prefer the sparse layout can turn them all off.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { timeAgo as timeAgoFn, formatViews } from '@/Composables/useFormatters';
import { useOptimizedImage } from '@/Composables/useOptimizedImage';
import { useI18n } from '@/Composables/useI18n';
import { useTranslation } from '@/Composables/useTranslation';
import ProBadge from '@/Components/ProBadge.vue';
import RatingBar from '@/Components/RatingBar.vue';

const { thumbnailProps, avatarProps } = useOptimizedImage();
const { localizedUrl, locale, t, isTranslated } = useI18n();
const { getTranslated } = useTranslation();
const page = usePage();

const props = defineProps({
    video: { type: Object, required: true },
    href: { type: String, default: '' },
});

const vc = computed(() => page.props.theme?.videoCard || {});

const isHovering = ref(false);
const previewLoaded = ref(false);
const previewIsPortrait = ref(Boolean(props.video.is_portrait));

const placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'%3E%3Crect fill='%23181818' width='640' height='360'/%3E%3Cpolygon fill='%23333' points='280,130 280,230 360,180'/%3E%3C/svg%3E";

const videoUrl = computed(() => {
    // Use translated slug for locale-prefixed URLs (SEO: /pt/apertado instead of /pt/wedgied)
    const slug = (isTranslated.value && props.video.translated_slug) ? props.video.translated_slug : props.video.slug;
    return localizedUrl(`/${slug}`);
});

const cardHref = computed(() => props.href || videoUrl.value);

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

const showAvatar = computed(() => vc.value.showAvatar === true);
const showUploader = computed(() => vc.value.showUploader !== false);
const showViews = computed(() => vc.value.showViews !== false);
const showDuration = computed(() => vc.value.showDuration !== false);
const showTimestamp = computed(() => vc.value.showTimestamp !== false);
const showRating = computed(() => vc.value.showRating !== false);
const showQuality = computed(() => vc.value.showQuality !== false);
const showTags = computed(() => vc.value.showTags !== false);

/** Cap tag chips so one over-tagged video can't blow out its grid row. */
const visibleTags = computed(() => (props.video.tags || []).slice(0, 3));

// Font family and weight arrive pre-resolved from App\Support\Typography — a
// full CSS stack and a weight the family actually publishes — so the card never
// asks for a font file that was not loaded.
const titleStyle = computed(() => ({
    color: vc.value.titleColor || undefined,
    fontFamily: vc.value.titleFont || undefined,
    fontWeight: vc.value.titleFont ? vc.value.titleWeight : undefined,
    fontSize: vc.value.titleSize ? `${vc.value.titleSize}px` : undefined,
    '-webkit-line-clamp': vc.value.titleLines || 2,
}));

const metaStyle = computed(() => ({
    fontFamily: vc.value.metaFont || undefined,
    fontWeight: vc.value.metaFont ? vc.value.metaWeight : undefined,
    fontSize: vc.value.metaSize ? `${vc.value.metaSize}px` : undefined,
    color: vc.value.metaColor || undefined,
}));

const thumbRadius = computed(() => {
    const r = vc.value.borderRadius;
    return r !== undefined && r !== null ? `${r}px` : 'var(--radius-thumb)';
});

const handleMouseEnter = () => { isHovering.value = true; };
const handleMouseLeave = () => {
    isHovering.value = false;
    // Don't reset previewLoaded here — the opacity transition handles the visual
    // switch. Resetting it causes the static thumbnail to briefly show opacity-0
    // while the alt text / broken image icon flashes visibly (bad UX).
};
const onPreviewLoad = (event) => {
    previewLoaded.value = true;

    const img = event?.target;
    if (img?.naturalWidth && img?.naturalHeight) {
        previewIsPortrait.value = img.naturalHeight > img.naturalWidth;
    }
};
</script>

<template>
    <Link
        v-motion
        :initial="{ opacity: 0, y: 6 }"
        :enter="{ opacity: 1, y: 0, transition: { duration: 0.18 } }"
        :href="cardHref"
        class="video-card group"
        :aria-label="`${video.title} — ${video.user?.username || 'Unknown'} — ${formattedViews} views`"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <div class="thumbnail" :style="{ borderRadius: thumbRadius }">
            <!-- Static Thumbnail -->
            <img
                v-bind="thumbnailProps(video.thumbnail_url || video.thumbnail || placeholderImg, video.thumbnail_alt || video.title)"
                class="w-full h-full object-cover transition-[opacity,transform] duration-200 group-hover:scale-[1.03]"
                :class="{ 'opacity-0': isHovering && video.preview_url && previewLoaded }"
                @error="(e) => e.target.src = placeholderImg"
            />

            <!-- Animated Preview (WebP) — portrait videos use object-contain to show full frame.
                 Its alt is intentionally empty: this overlays the poster above, which already
                 carries the description, and a second alt would make a screen reader announce
                 the same video twice. -->
            <div
                v-if="video.preview_url"
                class="absolute inset-0 w-full h-full transition-opacity duration-200 pointer-events-none"
                :class="isHovering && previewLoaded ? 'opacity-100' : 'opacity-0'"
                :style="previewIsPortrait ? { backgroundColor: '#000' } : {}"
            >
                <img
                    :src="(isHovering || previewLoaded) ? video.preview_url : ''"
                    alt=""
                    class="w-full h-full transition-opacity duration-200"
                    :class="previewIsPortrait ? 'object-contain' : 'object-cover'"
                    loading="lazy"
                    decoding="async"
                    @load="onPreviewLoad"
                />
            </div>

            <!-- Quality badge, top-start so it never collides with the duration. -->
            <span v-if="showQuality && video.quality_label" class="badge-thumb top-1.5 start-1.5">
                {{ video.quality_label }}
            </span>

            <span v-if="showDuration" class="duration">
                {{ video.duration_formatted || video.formatted_duration || formattedDuration }}
            </span>
        </div>

        <!-- Rating sits directly under the thumbnail, reading as part of it. -->
        <RatingBar
            v-if="showRating && video.rating_percent !== null && video.rating_percent !== undefined"
            :percent="video.rating_percent"
            class="mt-1.5"
        />

        <div class="flex gap-2 mt-1.5">
            <Link
                v-if="showAvatar && video.user"
                :href="localizedUrl(`/channel/${video.user.username}`)"
                class="shrink-0"
            >
                <div class="w-7 h-7 avatar">
                    <img v-bind="avatarProps(video.user.avatar_url || video.user.avatar || '/assets/default_avatar.webp', 28, video.user.avatar_alt || video.user.username || video.user.name)" class="w-full h-full object-cover" />
                </div>
            </Link>

            <div class="flex-1 min-w-0">
                <h3
                    class="video-card-title transition-colors"
                    :class="`line-clamp-${vc.titleLines || 2}`"
                    :style="titleStyle"
                >{{ isTranslated ? getTranslated('video', video.id, 'title', video.title) : video.title }}</h3>

                <p class="video-card-meta mt-0.5" :style="metaStyle">
                    <template v-if="showViews">{{ t('video.views', { count: formattedViews, n: video.views_count }) }}</template>
                    <template v-if="showViews && showTimestamp"> · </template>
                    <template v-if="showTimestamp">{{ timeAgo }}</template>
                </p>

                <Link
                    v-if="showUploader && video.user"
                    :href="localizedUrl(`/channel/${video.user.username}`)"
                    class="video-card-meta mt-0.5 flex items-center gap-1 hover:text-text-primary transition-colors"
                    :style="metaStyle"
                >
                    <span class="truncate">{{ video.user.username }}</span>
                    <span v-if="video.user.is_verified" class="text-accent-text shrink-0" aria-hidden="true">✓</span>
                    <ProBadge v-if="video.user.is_pro" size="sm" />
                </Link>

                <div v-if="showTags && visibleTags.length" class="flex flex-wrap gap-1 mt-1.5">
                    <span
                        v-for="tag in visibleTags"
                        :key="tag"
                        class="px-1.5 py-0.5 text-[10px] font-medium leading-none truncate max-w-full bg-bg-elevated text-text-muted"
                        :style="{ borderRadius: 'var(--radius-card)' }"
                    >{{ tag }}</span>
                </div>
            </div>
        </div>
    </Link>
</template>
