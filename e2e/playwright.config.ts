import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const laravel = path.resolve(__dirname, '../web');
// Cổng riêng: không bao giờ tái sử dụng nhầm server dev (:8000, CSDL phát triển; artisan serve tự nhảy sang 8001, 8002… khi cổng bận) khi đang chạy song song.
const BASE = 'http://127.0.0.1:8010';

/**
 * E2E chạy trên CSDL riêng (`*_e2e`, nạp bởi E2eFixtureSeeder) và workspace GeoServer riêng `songhau_e2e`,
 * tách khỏi dữ liệu phát triển (Mục 9/10 kế hoạch). Cần GeoServer đang chạy ở :8080 (xem README).
 */
export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
    workers: 1,
    retries: 1,
    timeout: 45_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: { baseURL: BASE, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    globalSetup: './global-setup.ts',
    webServer: {
        command: 'php artisan serve --env=e2e --host=127.0.0.1 --port=8010',
        cwd: laravel,
        url: `${BASE}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
});
