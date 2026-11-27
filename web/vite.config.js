import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/steps.js', 'resources/js/back-to-top.js', 'resources/js/admin.js', 'resources/js/admin-map.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // vietmap (~1 MB) chỉ tải động khi người dùng chọn nền VietMap; không nằm trên đường tải đầu.
        chunkSizeWarningLimit: 1100,
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        // Leaflet dùng chung mọi trang bản đồ: tách riêng để cache lâu, không đổi hash khi sửa mã app.
                        { name: 'leaflet', test: /node_modules[\\/]leaflet[\\/]/ },
                    ],
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
