<script setup>
/**
 * A comment's body, with @mentions linked and timestamps made clickable.
 *
 * The text is rendered as segments rather than through v-html: comment bodies
 * are untrusted input, and this keeps them escaped by Vue while still allowing
 * real elements for the two things that should be interactive.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/Composables/useI18n';
import { parseCommentText } from '@/Composables/useCommentText';

const props = defineProps({
    content: { type: String, default: '' },
    small: { type: Boolean, default: false },
});

const emit = defineEmits(['seek']);

const { t, localizedUrl } = useI18n();

const segments = computed(() => parseCommentText(props.content));
</script>

<template>
    <p class="whitespace-pre-wrap break-words text-text-secondary" :class="small ? 'text-sm' : ''">
        <template v-for="(segment, index) in segments" :key="index">
            <Link
                v-if="segment.type === 'mention'"
                :href="localizedUrl(`/channel/${segment.username}`)"
                class="hover:underline text-accent-text"
            >{{ segment.text }}</Link>
            <button
                v-else-if="segment.type === 'timestamp'"
                type="button"
                class="hover:underline text-accent-text"
                :aria-label="t('video.jump_to', { time: segment.text })"
                @click="emit('seek', segment.seconds)"
            >{{ segment.text }}</button>
            <template v-else>{{ segment.text }}</template>
        </template>
    </p>
</template>
