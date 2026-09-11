import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import { familySources } from '@schemastud/seam/vite';

export default defineConfig({
    // Family packages may be consumed as `link:` symlinks into ~/Workspaces/js (local co-dev). Vite then
    // resolves their externalized peers (react, @inertiajs/react, react-query) from the JS workspace's own
    // node_modules, bundling a SECOND React and breaking every hook at boot
    // ("Cannot read properties of null (reading 'useEffect')"). Dedupe pins one copy: the host's.
    resolve: {
        dedupe: [
            'react',
            'react-dom',
            'react-router',
            '@inertiajs/core',
            '@inertiajs/react',
            '@tanstack/react-query',
        ],
    },
    plugins: [
        familySources(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
    ],
});
