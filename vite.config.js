import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Listen on all interfaces so devices on the LAN can reach Vite.
        host: '0.0.0.0',
        // Advertise a host the phone can actually reach for assets + HMR.
        // Override per-machine with VITE_DEV_HOST if your LAN IP differs.
        hmr: {
            host: process.env.VITE_DEV_HOST ?? '192.168.69.128',
        },
    },
});