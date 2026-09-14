import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        emptyOutDir: true,
    },
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            // Testbench-Dusk boots the Workbench from its Laravel skeleton.
            // Keep the Vite manifest in that application's public directory so
            // @vite() resolves exactly as it does in a normal Laravel app.
            publicDirectory: '../vendor/orchestra/testbench-dusk/laravel/public',
            refresh: true,
        }),
    ],
});
