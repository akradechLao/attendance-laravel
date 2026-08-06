import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        }
    },
    build: {
        outDir: 'public/build',
        rollupOptions: {
            input: resolve(__dirname, 'resources/js/app.js'),
        }
    }
})
