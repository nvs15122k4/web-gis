import { defineConfig } from 'vitest/config';

// Cấu hình riêng cho test JS (không dùng vite.config.js để khỏi nạp plugin Laravel/Tailwind).
export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        restoreMocks: true,
    },
});
