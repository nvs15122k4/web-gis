import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { BASE, PORT, LARAVEL_ENV, SLOT } from './env';

const laravel = path.resolve(__dirname, '../web');
// Cổng/CSDL lấy từ ./env.ts (mặc định 8010; đặt E2E_SLOT=<tên> để mỗi phiên chạy song song có cổng + CSDL riêng).

/**
 * E2E chạy trên CSDL riêng (`*_e2e`, nạp bởi E2eFixtureSeeder) và workspace GeoServer riêng `songhau_e2e`,
 * tách khỏi dữ liệu phát triển (Mục 9/10 kế hoạch). Cần GeoServer đang chạy ở :8080 (xem README).
 */
export default defineConfig({
    testDir: './tests',
    // Mỗi slot có thư mục kết quả/báo cáo riêng để các lần chạy song song không ghi đè trace của nhau.
    outputDir: SLOT ? `test-results-${SLOT}` : 'test-results',
    fullyParallel: false,
    workers: 1,
    retries: 1,
    timeout: 45_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never', outputFolder: SLOT ? `playwright-report-${SLOT}` : 'playwright-report' }]],
    use: { baseURL: BASE, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    globalSetup: './global-setup.ts',
    webServer: {
        // .env.e2e đặt SESSION_DRIVER=array (mỗi yêu cầu một phiên mới); các trang cần đăng nhập bằng phiên (/bao-cao) cần driver lưu được.
        command: `SESSION_DRIVER=file php artisan serve --env=e2e --host=127.0.0.1 --port=${PORT}`,
        env: { ...(process.env as Record<string, string>), ...LARAVEL_ENV },
        cwd: laravel,
        url: `${BASE}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        // WebKit của Playwright chỉ có bản build cho Ubuntu 24.04 trở xuống; trên bản mới hơn (vd. 26.04) không khởi chạy được.
        // Đặt E2E_NO_WEBKIT=1 (hoặc dùng `npm run test:no-webkit`) để bỏ qua project này.
        ...(process.env.E2E_NO_WEBKIT ? [] : [{ name: 'webkit', use: { ...devices['Desktop Safari'] } }]),
    ],
});
