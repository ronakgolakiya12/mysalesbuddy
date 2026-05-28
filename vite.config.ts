import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    server: {
        // Force IPv4 — Node defaults to ::1 on Windows, but IPv6 literals
        // (e.g. `[::1]:5173`) aren't valid CSP source expressions and get
        // dropped by the browser, breaking script loading in dev.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
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
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./resources/js/tests/setup.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html', 'lcov', 'json-summary'],
            reportsDirectory: './storage/coverage/frontend',
            include: [
                'resources/js/**/*.{ts,vue}',
            ],
            exclude: [
                'node_modules/**',
                'resources/js/tests/**',
                'resources/js/**/*.d.ts',
                'resources/js/app.ts',
                'resources/js/bootstrap.ts',
                'resources/js/echo.ts',
                'resources/js/router/**',
                'resources/js/types/**',
            ],
            thresholds: {
                lines: 80,
                statements: 80,
                functions: 80,
                branches: 75,
            },
        },
    },
});
