import js from '@eslint/js';
import globals from 'globals';

/**
 * ESLint flat config (ESLint 9+).
 *
 * Scope: resources/js/**\/*.js only.
 * Svelte files are covered by `svelte-check` (run separately in CI).
 */
export default [
    // Apply recommended rules restricted to our JS source files
    {
        ...js.configs.recommended,
        files: ['resources/js/**/*.js'],
    },

    // Language options for browser + ES2022 module environment
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.es2022,
            },
            ecmaVersion: 2022,
            sourceType: 'module',
        },
        rules: {
            // Downgrade unused-vars to a warning so dev experience stays smooth
            'no-unused-vars': 'warn',
        },
    },

    // Ignore generated / third-party directories
    {
        ignores: [
            'vendor/',
            'node_modules/',
            'public/',
            'bootstrap/ssr/',
            'storage/',
        ],
    },
];
