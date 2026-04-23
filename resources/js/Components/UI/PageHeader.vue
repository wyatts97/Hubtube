<script setup>
/**
 * Standardized page header with optional subtitle and action slot.
 * Usage:
 *   <PageHeader title="Browse Videos" subtitle="Latest uploads">
 *     <template #actions>
 *       <button class="btn btn-primary">Upload</button>
 *     </template>
 *   </PageHeader>
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    as: { type: String, default: 'h1' }, // allow h1 / h2
});
</script>

<template>
    <div class="mb-5 flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
            <component :is="as" class="text-xl sm:text-2xl font-bold text-text-primary leading-tight">{{ title }}</component>
            <p v-if="subtitle" class="mt-1 text-sm text-text-secondary">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>
