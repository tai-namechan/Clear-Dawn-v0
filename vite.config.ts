import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Noto Sans JP', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Cormorant Garamond', {
                    weights: [400, 500],
                }),
                bunny('Shippori Mincho', {
                    weights: [400, 500],
                }),
                bunny('Zen Kaku Gothic New', {
                    weights: [400, 500],
                }),
                bunny('Zen Kurenaido', {
                    weights: [400],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
