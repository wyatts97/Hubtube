<script setup>
/**
 * Like-ratio bar — the single most recognisable piece of tube-site furniture,
 * and the one this frontend was missing.
 *
 * Renders nothing when `percent` is null. The server returns null below
 * Video::RATING_MIN_VOTES so a video with one like doesn't advertise "100%".
 */
import { computed } from 'vue';
import { useI18n } from '@/Composables/useI18n';

const props = defineProps({
    /** 0-100, or null when there aren't enough votes to be meaningful. */
    percent: { type: Number, default: null },
    /** Show the numeric value beside the bar. */
    showValue: { type: Boolean, default: false },
});

const { t } = useI18n();

const clamped = computed(() =>
    props.percent === null ? null : Math.max(0, Math.min(100, props.percent))
);

const label = computed(() =>
    clamped.value === null ? '' : t('video.rating_percent', { percent: clamped.value })
);
</script>

<template>
    <div
        v-if="clamped !== null"
        class="flex items-center gap-1.5"
        role="img"
        :aria-label="label"
        :title="label"
    >
        <div class="rating-bar flex-1">
            <span class="rating-bar-fill" :style="{ width: clamped + '%' }"></span>
        </div>
        <span v-if="showValue" class="text-[11px] font-semibold tabular-nums text-text-muted">
            {{ clamped }}%
        </span>
    </div>
</template>
