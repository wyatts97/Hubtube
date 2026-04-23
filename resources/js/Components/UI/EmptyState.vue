<script setup>
/**
 * Standardized empty-state. Usage:
 *   <EmptyState :icon="Inbox" title="No videos yet" description="Upload your first video">
 *     <template #action>
 *       <Link href="/upload" class="btn btn-primary">Upload</Link>
 *     </template>
 *   </EmptyState>
 */
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="text-center py-16 px-4">
        <component
            v-if="icon"
            :is="icon"
            class="w-14 h-14 mx-auto mb-4 text-text-muted"
        />
        <p v-if="title" class="text-lg font-semibold text-text-secondary">{{ title }}</p>
        <p v-if="description" class="mt-2 text-sm text-text-muted">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5 flex items-center justify-center gap-3">
            <slot name="action" />
        </div>
    </div>
</template>
