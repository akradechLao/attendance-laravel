import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { resolve } from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue(),
    ],
    publicDir: false,
    build: {
        manifest: true,
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        }
    },
})
