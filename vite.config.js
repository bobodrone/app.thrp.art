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
        // Advertise a host the browser can actually reach for assets + HMR.
        // Defaults to localhost; set VITE_DEV_HOST to your LAN IP (as a shell
        // env var, not in .env) when testing from a phone or another device:
        //   VITE_DEV_HOST=192.168.69.129 npm run dev
        hmr: {
            host: process.env.VITE_DEV_HOST ?? 'localhost',
        },
    },
});