import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react({
            jsxRuntime: 'automatic',
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        rollupOptions: {
            output: {
                // Match by path: the app imports bootstrap/dist/js/bootstrap.bundle.min.js,
                // which a package-name entry ('bootstrap') never catches.
                manualChunks(id) {
                    if (id.includes('node_modules/bootstrap/')) {
                        return 'bootstrap';
                    }
                    if (/node_modules\/(react|react-dom|scheduler)\//.test(id)) {
                        return 'react';
                    }
                },
            },
        },
    },
});
