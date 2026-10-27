import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
            fonts: [
                local('Instrument Sans', {
                    optimizedFallbacks: false,
                    variants: [
                        {
                            src: [
                                'resources/fonts/instrument-sans/instrument-sans-400.woff2',
                                'resources/fonts/instrument-sans/instrument-sans-400.woff',
                            ],
                            weight: 400,
                        },
                        {
                            src: [
                                'resources/fonts/instrument-sans/instrument-sans-500.woff2',
                                'resources/fonts/instrument-sans/instrument-sans-500.woff',
                            ],
                            weight: 500,
                        },
                        {
                            src: [
                                'resources/fonts/instrument-sans/instrument-sans-600.woff2',
                                'resources/fonts/instrument-sans/instrument-sans-600.woff',
                            ],
                            weight: 600,
                        },
                    ],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
