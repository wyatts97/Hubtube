<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    seo: {
        type: Object,
        default: () => ({}),
    },
});

const schemaScripts = computed(() => {
    if (!props.seo?.schema?.length) return [];
    return props.seo.schema;
});

const schemaJson = computed(() => {
    if (schemaScripts.value.length === 0) return '';
    if (schemaScripts.value.length === 1) {
        return JSON.stringify(schemaScripts.value[0]);
    }
    return JSON.stringify(schemaScripts.value);
});

const ogTags = computed(() => {
    if (!props.seo?.og) return [];
    const tags = [];
    for (const [key, value] of Object.entries(props.seo.og)) {
        if (!value) continue;
        if (Array.isArray(value)) {
            value.forEach(v => {
                if (v) tags.push({ property: `og:${key}`, content: String(v) });
            });
        } else {
            tags.push({ property: `og:${key}`, content: String(value) });
        }
    }
    return tags;
});

const twitterTags = computed(() => {
    if (!props.seo?.twitter) return [];
    const tags = [];
    for (const [key, value] of Object.entries(props.seo.twitter)) {
        if (value) {
            tags.push({ name: `twitter:${key}`, content: String(value) });
        }
    }
    return tags;
});
</script>

<template>
    <Head :title="seo?.title || ''">
        <!-- Meta Description -->
        <meta v-if="seo?.description" name="description" :content="seo.description" />

        <!-- Keywords (used by Yandex) -->
        <meta v-if="seo?.keywords" name="keywords" :content="seo.keywords" />

        <!-- Robots -->
        <meta v-if="seo?.robots" name="robots" :content="seo.robots" />

        <!-- Canonical -->
        <link v-if="seo?.canonical" rel="canonical" :href="seo.canonical" />

        <!-- Open Graph -->
        <meta
            v-for="(tag, i) in ogTags"
            :key="'og-' + i"
            :property="tag.property"
            :content="tag.content"
        />

        <!-- Twitter Card -->
        <meta
            v-for="(tag, i) in twitterTags"
            :key="'tw-' + i"
            :name="tag.name"
            :content="tag.content"
        />

        <!-- JSON-LD Structured Data -->
        <script v-if="schemaJson" type="application/ld+json" v-text="schemaJson"></script>
    </Head>
</template>
