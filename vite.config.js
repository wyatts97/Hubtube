import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
