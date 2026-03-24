import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

/**
 * Svelte configuration.
 *
 * Used by:
 *  - @sveltejs/vite-plugin-svelte (Vite builds)
 *  - svelte-check (CI lint / IDE type checking)
 */
export default {
    // Run the Vite preprocessor so <style> blocks and import aliases work
    preprocess: vitePreprocess(),

    compilerOptions: {
        // Enforce Svelte 5 runes mode across all components
        runes: true,
    },
};
