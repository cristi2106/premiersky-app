import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// In Codespaces, the Vite dev server is reached through the forwarded
// HTTPS domain rather than localhost, so the HMR websocket client needs
// to be told to dial back out through that same wss:// host instead of
// the ws://[::1]:5173 it would otherwise guess.
const codespaceHmr = process.env.CODESPACE_NAME
    ? {
          host: `${process.env.CODESPACE_NAME}-5173.${process.env.GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}`,
          protocol: 'wss',
          clientPort: 443,
      }
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    server: {
        cors: process.env.CODESPACE_NAME ? true : undefined,
        hmr: codespaceHmr,
    },
});
