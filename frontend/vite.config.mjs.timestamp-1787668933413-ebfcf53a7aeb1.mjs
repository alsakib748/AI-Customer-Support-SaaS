// vite.config.mjs
import { fileURLToPath, URL } from 'node:url';
import { PrimeVueResolver } from 'file:///D:/Admin%20Dashboard%20Template/Vue/Sakai-Vue%20Admin%20Dashboard%20-%20Main/node_modules/@primevue/auto-import-resolver/index.mjs';
import tailwindcss from 'file:///D:/Admin%20Dashboard%20Template/Vue/Sakai-Vue%20Admin%20Dashboard%20-%20Main/node_modules/@tailwindcss/vite/dist/index.mjs';
import vue from 'file:///D:/Admin%20Dashboard%20Template/Vue/Sakai-Vue%20Admin%20Dashboard%20-%20Main/node_modules/@vitejs/plugin-vue/dist/index.mjs';
import Components from 'file:///D:/Admin%20Dashboard%20Template/Vue/Sakai-Vue%20Admin%20Dashboard%20-%20Main/node_modules/unplugin-vue-components/dist/vite.js';
import { defineConfig } from 'file:///D:/Admin%20Dashboard%20Template/Vue/Sakai-Vue%20Admin%20Dashboard%20-%20Main/node_modules/vite/dist/node/index.js';
var __vite_injected_original_import_meta_url = 'file:///D:/Admin%20Dashboard%20Template/Vue/Sakai-Vue%20Admin%20Dashboard%20-%20Main/vite.config.mjs';
var vite_config_default = defineConfig({
    optimizeDeps: {
        noDiscovery: true
    },
    plugins: [
        vue(),
        tailwindcss(),
        Components({
            resolvers: [PrimeVueResolver()]
        })
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', __vite_injected_original_import_meta_url))
        }
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler'
            }
        }
    }
});
export { vite_config_default as default };
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcubWpzIl0sCiAgInNvdXJjZXNDb250ZW50IjogWyJjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfZGlybmFtZSA9IFwiRDpcXFxcQWRtaW4gRGFzaGJvYXJkIFRlbXBsYXRlXFxcXFZ1ZVxcXFxTYWthaS1WdWUgQWRtaW4gRGFzaGJvYXJkIC0gTWFpblwiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9maWxlbmFtZSA9IFwiRDpcXFxcQWRtaW4gRGFzaGJvYXJkIFRlbXBsYXRlXFxcXFZ1ZVxcXFxTYWthaS1WdWUgQWRtaW4gRGFzaGJvYXJkIC0gTWFpblxcXFx2aXRlLmNvbmZpZy5tanNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfaW1wb3J0X21ldGFfdXJsID0gXCJmaWxlOi8vL0Q6L0FkbWluJTIwRGFzaGJvYXJkJTIwVGVtcGxhdGUvVnVlL1Nha2FpLVZ1ZSUyMEFkbWluJTIwRGFzaGJvYXJkJTIwLSUyME1haW4vdml0ZS5jb25maWcubWpzXCI7aW1wb3J0IHsgZmlsZVVSTFRvUGF0aCwgVVJMIH0gZnJvbSAnbm9kZTp1cmwnO1xyXG5cclxuaW1wb3J0IHsgUHJpbWVWdWVSZXNvbHZlciB9IGZyb20gJ0BwcmltZXZ1ZS9hdXRvLWltcG9ydC1yZXNvbHZlcic7XHJcbmltcG9ydCB0YWlsd2luZGNzcyBmcm9tICdAdGFpbHdpbmRjc3Mvdml0ZSc7XHJcbmltcG9ydCB2dWUgZnJvbSAnQHZpdGVqcy9wbHVnaW4tdnVlJztcclxuaW1wb3J0IENvbXBvbmVudHMgZnJvbSAndW5wbHVnaW4tdnVlLWNvbXBvbmVudHMvdml0ZSc7XHJcbmltcG9ydCB7IGRlZmluZUNvbmZpZyB9IGZyb20gJ3ZpdGUnO1xyXG5cclxuLy8gaHR0cHM6Ly92aXRlanMuZGV2L2NvbmZpZy9cclxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcclxuICAgIG9wdGltaXplRGVwczoge1xyXG4gICAgICAgIG5vRGlzY292ZXJ5OiB0cnVlXHJcbiAgICB9LFxyXG4gICAgcGx1Z2luczogW1xyXG4gICAgICAgIHZ1ZSgpLFxyXG4gICAgICAgIHRhaWx3aW5kY3NzKCksXHJcbiAgICAgICAgQ29tcG9uZW50cyh7XHJcbiAgICAgICAgICAgIHJlc29sdmVyczogW1ByaW1lVnVlUmVzb2x2ZXIoKV1cclxuICAgICAgICB9KVxyXG4gICAgXSxcclxuICAgIHJlc29sdmU6IHtcclxuICAgICAgICBhbGlhczoge1xyXG4gICAgICAgICAgICAnQCc6IGZpbGVVUkxUb1BhdGgobmV3IFVSTCgnLi9zcmMnLCBpbXBvcnQubWV0YS51cmwpKVxyXG4gICAgICAgIH1cclxuICAgIH0sXHJcbiAgICBjc3M6IHtcclxuICAgICAgICBwcmVwcm9jZXNzb3JPcHRpb25zOiB7XHJcbiAgICAgICAgICAgIHNjc3M6IHtcclxuICAgICAgICAgICAgICAgIGFwaTogJ21vZGVybi1jb21waWxlcidcclxuICAgICAgICAgICAgfVxyXG4gICAgICAgIH1cclxuICAgIH1cclxufSk7XHJcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFBd1ksU0FBUyxlQUFlLFdBQVc7QUFFM2EsU0FBUyx3QkFBd0I7QUFDakMsT0FBTyxpQkFBaUI7QUFDeEIsT0FBTyxTQUFTO0FBQ2hCLE9BQU8sZ0JBQWdCO0FBQ3ZCLFNBQVMsb0JBQW9CO0FBTm1OLElBQU0sMkNBQTJDO0FBU2pTLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBQ3hCLGNBQWM7QUFBQSxJQUNWLGFBQWE7QUFBQSxFQUNqQjtBQUFBLEVBQ0EsU0FBUztBQUFBLElBQ0wsSUFBSTtBQUFBLElBQ0osWUFBWTtBQUFBLElBQ1osV0FBVztBQUFBLE1BQ1AsV0FBVyxDQUFDLGlCQUFpQixDQUFDO0FBQUEsSUFDbEMsQ0FBQztBQUFBLEVBQ0w7QUFBQSxFQUNBLFNBQVM7QUFBQSxJQUNMLE9BQU87QUFBQSxNQUNILEtBQUssY0FBYyxJQUFJLElBQUksU0FBUyx3Q0FBZSxDQUFDO0FBQUEsSUFDeEQ7QUFBQSxFQUNKO0FBQUEsRUFDQSxLQUFLO0FBQUEsSUFDRCxxQkFBcUI7QUFBQSxNQUNqQixNQUFNO0FBQUEsUUFDRixLQUFLO0FBQUEsTUFDVDtBQUFBLElBQ0o7QUFBQSxFQUNKO0FBQ0osQ0FBQzsiLAogICJuYW1lcyI6IFtdCn0K
