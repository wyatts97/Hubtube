import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';
import { sentryVitePlugin } from '@sentry/vite-plugin';

export default defineConfig({
    build: {
        sourcemap: true, // Required for Sentry source map uploads
        rollupOptions: {
            output: {
                // Split heavy, rarely-co-loaded vendor deps into their own chunks so a
                // page that never touches video/ads/error-tracking doesn't pay for
                // Vidstack/hls.js/Sentry in its initial JS payload. hls.js is also
                // dynamically imported in VideoPlayer.vue, which puts it in its own
                // chunk automatically — this manualChunks entry is a safety net in
                // case it's ever pulled in by a static import elsewhere too.
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;
                    // Only carve out the few large, rarely-co-loaded deps we actually care
                    // about splitting. Leaving everything else undefined lets Rollup's
                    // default heuristics group the rest — forcing every remaining
                    // node_modules package into one generic catch-all bucket previously
                    // produced a circular chunk (vidstack's own small dependencies like
                    // @floating-ui/dom and lit-html landed in that bucket while vidstack's
                    // chunk imported them back).
                    if (id.includes('vidstack') || id.includes('@floating-ui') || id.includes('lit-html') || id.includes('media-captions')) {
                        return 'vendor-vidstack';
                    }
                    if (id.includes('hls.js')) return 'vendor-hlsjs';
                    if (id.includes('@sentry')) return 'vendor-sentry';
                    if (id.includes('lucide-vue-next')) return 'vendor-icons';
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
                compilerOptions: {
                    isCustomElement: (tag) => tag.startsWith('media-'),
                },
            },
        }),
        tailwindcss(),
        ViteImageOptimizer({
            png: { quality: 75 },
            jpeg: { quality: 80 },
            jpg: { quality: 80 },
            webp: { quality: 80 },
            avif: { quality: 60 },
        }),
        // Upload source maps to Sentry on production builds (only when auth token is set)
        sentryVitePlugin({
            org: process.env.SENTRY_ORG,
            project: process.env.SENTRY_PROJECT,
            authToken: process.env.SENTRY_AUTH_TOKEN,
            disable: !process.env.SENTRY_AUTH_TOKEN,
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
