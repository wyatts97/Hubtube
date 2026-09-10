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
                // Fluid Player/hls.js/Sentry in its initial JS payload.
                //
                // hls.js and dash.js are reached only through Fluid's own dynamic
                // imports (src/modules/streaming.js), so they are already split by
                // Rollup; naming the hls.js chunk here just keeps it stable and
                // recognisable in the build output.
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;
                    // Only carve out the few large, rarely-co-loaded deps we actually care
                    // about splitting. Leaving everything else undefined lets Rollup's
                    // default heuristics group the rest — forcing every remaining
                    // node_modules package into one generic catch-all bucket previously
                    // produced a circular chunk when Vidstack's small dependencies landed
                    // in that bucket while Vidstack's own chunk imported them back.
                    if (id.includes('fluid-player')) return 'vendor-fluidplayer';
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
