<script setup>
import { Crown } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    size: { type: String, default: 'sm' },
});

const page = usePage();
const text = computed(() => page.props.app?.pro_badge_text || 'PRO');

const sizeClasses = {
    sm: 'px-1.5 py-0.5 text-[10px]',
    md: 'px-2 py-0.5 text-xs',
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 rounded-full font-semibold uppercase tracking-wide text-yellow-950 bg-yellow-400 border border-yellow-500/50',
            sizeClasses[props.size] || sizeClasses.sm,
        ]"
    >
        <Crown class="w-3 h-3" />
        {{ text }}
    </span>
</template>
