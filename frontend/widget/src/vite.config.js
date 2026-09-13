// widget/vite.config.js

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'dist',
        rollupOptions: {
            input: 'src/main.js',
            output: {
                entryFileNames: 'widget.js',
                format: 'iife',
                name: 'ChatWidget'
            }
        },
        minify: true,
        sourcemap: false
    },
    optimizeDeps: {
        noDiscovery: true,
        include: ['event-source-polyfill']
    },
    server: {
        port: 3001,
        cors: true
    }
});
