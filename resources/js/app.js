import './bootstrap';
import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';

createInertiaApp({
    // Resolve page components from resources/js/Pages/**/*.svelte
    resolve(name) {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
        const page = pages[`./Pages/${name}.svelte`];
        if (!page) {
            throw new Error(`Inertia page not found: ./Pages/${name}.svelte`);
        }
        return page;
    },

    // Mount using the Svelte 5 `mount()` API
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },

    // Show a simple progress bar during Inertia navigations
    progress: {
        color: '#6366f1',
    },
});
