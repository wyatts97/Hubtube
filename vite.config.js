import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';
import { sentryVitePlugin } from '@sentry/vite-plugin';

export default defineConfig({
    build: {
        sourcemap: true, // Required for Sentry source map uploads
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin/sortable-init.js'],
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
