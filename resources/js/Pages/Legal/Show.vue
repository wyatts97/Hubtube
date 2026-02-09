<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    page: Object,
});
</script>

<template>
    <Head :title="page.title" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="card p-6 md:p-10">
                <h1 class="text-2xl md:text-3xl font-bold mb-2" style="color: var(--color-text-primary);">
                    {{ page.title }}
                </h1>
                <p class="text-sm mb-8" style="color: var(--color-text-muted);">
                    Last updated: {{ page.updated_at }}
                </p>
                <div
                    class="prose prose-invert max-w-none legal-content"
                    style="color: var(--color-text-secondary);"
                    v-html="page.content"
                ></div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.legal-content :deep(h2) {
    font-size: 1.375rem;
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    color: var(--color-text-primary);
}

.legal-content :deep(h3) {
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--color-text-primary);
}

.legal-content :deep(p) {
    margin-bottom: 1rem;
    line-height: 1.7;
}

.legal-content :deep(ul),
.legal-content :deep(ol) {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.legal-content :deep(li) {
    margin-bottom: 0.375rem;
    line-height: 1.6;
}

.legal-content :deep(ul) {
    list-style-type: disc;
}

.legal-content :deep(ol) {
    list-style-type: decimal;
}

.legal-content :deep(a) {
    color: var(--color-accent);
    text-decoration: underline;
}

.legal-content :deep(strong) {
    color: var(--color-text-primary);
    font-weight: 600;
}

.legal-content :deep(hr) {
    border-color: var(--color-border);
    margin: 2rem 0;
}
</style>
