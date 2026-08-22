<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Client-side twin of the server-rendered SEO block in app.blade.php.
 *
 * Both render from the SAME payload — the array a SeoService::for*() builder
 * produced. Every tag carries a `head-key` matching the `inertia` attribute on
 * its Blade counterpart, so Inertia's head manager REPLACES the server tag on
 * hydration rather than appending a duplicate.
 *
 * That matching is load-bearing in both directions: a server tag whose key the
 * client never re-emits is removed from the DOM. Only add a keyed tag here if
 * app.blade.php emits the same key, and vice versa.
 *
 * The payload falls back to the shared `seo` page prop, so most pages can just
 * render <SeoHead /> with no props at all.
 */
const props = defineProps({
    seo: {
        type: Object,
        default: null,
    },
    // Overrides the payload title — for pages (settings, wallet, auth) whose
    // server payload is a generic noindex stub but which still want a useful
    // browser tab label.
    title: {
        type: String,
        default: null,
    },
});

const page = usePage();

const meta = computed(() => props.seo ?? page.props.seo ?? {});

// The payload title is already complete (SeoService composes it with the site
// name and separator). An override is a bare label, so compose it the same way.
const pageTitle = computed(() => {
    if (!props.title) return meta.value.title ?? '';
    const siteName = meta.value.og?.site_name;
    if (!siteName) return props.title;
    return `${props.title} ${meta.value.separator || '|'} ${siteName}`;
});

const schemaJson = computed(() => {
    const schema = meta.value.schema;
    if (!schema?.length) return '';
    return JSON.stringify(schema.length === 1 ? schema[0] : schema);
});

/**
 * Flatten the og and twitter objects into keyed tags. Arrays expand to one
 * tag per entry with an index-suffixed key (og:video:tag:0, …), matching the
 * @foreach loops in app.blade.php.
 */
function flatten(source, prefix) {
    if (!source) return [];
    const tags = [];
    for (const [key, value] of Object.entries(source)) {
        if (!value) continue;
        if (Array.isArray(value)) {
            value.forEach((entry, i) => {
                if (entry) {
                    tags.push({ key: `${prefix}:${key}:${i}`, name: `${prefix}:${key}`, content: String(entry) });
                }
            });
        } else {
            tags.push({ key: `${prefix}:${key}`, name: `${prefix}:${key}`, content: String(value) });
        }
    }
    return tags;
}

const ogTags = computed(() => flatten(meta.value.og, 'og'));
const twitterTags = computed(() => flatten(meta.value.twitter, 'twitter'));
</script>

<template>
    <Head :title="pageTitle">
        <!-- Meta Description -->
        <meta v-if="meta.description" head-key="description" name="description" :content="meta.description" />

        <!-- Keywords (used by Yandex) -->
        <meta v-if="meta.keywords" head-key="keywords" name="keywords" :content="meta.keywords" />

        <!-- Robots -->
        <meta v-if="meta.robots" head-key="robots" name="robots" :content="meta.robots" />

        <!-- Canonical -->
        <link v-if="meta.canonical" head-key="canonical" rel="canonical" :href="meta.canonical" />

        <!-- Open Graph -->
        <meta
            v-for="tag in ogTags"
            :key="tag.key"
            :head-key="tag.key"
            :property="tag.name"
            :content="tag.content"
        />

        <!-- Twitter Card -->
        <meta
            v-for="tag in twitterTags"
            :key="tag.key"
            :head-key="tag.key"
            :name="tag.name"
            :content="tag.content"
        />

        <!-- JSON-LD Structured Data -->
        <script v-if="schemaJson" head-key="schema" type="application/ld+json" v-text="schemaJson"></script>
    </Head>
</template>
