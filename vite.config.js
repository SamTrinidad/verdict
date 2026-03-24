import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        svelte({
            // compilerOptions: { runes: true } for all project source files.
            // node_modules (.svelte files inside @inertiajs/svelte etc.) still
            // use legacy Svelte 4 `export let` syntax, so we must NOT force
            // runes mode on them.  The callback form applies runes only to
            // files that live outside node_modules.
            compilerOptions: (filename) =>
                /node_modules/.test(filename) ? {} : { runes: true },
        }),
    ],
    server: {
        // Bind to all interfaces so Vite is reachable inside Docker
        host: '0.0.0.0',
        hmr: {
            // Set VITE_HOST in .env to your Docker host IP / hostname when
            // running `npm run dev` inside a container.  Defaults to localhost
            // for native development.
            host: process.env.VITE_HOST ?? 'localhost',
        },
        watch: {
            usePolling: true, // required for file-change detection inside Docker
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
