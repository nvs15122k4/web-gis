import { test, expect } from '@playwright/test';
import { openMap, openTab } from './helpers';
import { appErrors } from './helpers-journey';

const VIEWPORTS = [
    { name: '320', width: 320, height: 640, mobile: true },
    { name: '768', width: 768, height: 900, mobile: true },
    { name: '1440', width: 1440, height: 900, mobile: false },
];

for (const vp of VIEWPORTS) {
    test.describe(`Viewport ${vp.name}px`, () => {
        test.use({ viewport: { width: vp.width, height: vp.height } });

        test(`V01 ${vp.name}: bản đồ hiển thị, không tràn ngang, không lỗi console`, async ({ page }) => {
            const errors = await openMap(page);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            const box = await page.locator('#map').boundingBox();
            expect(box!.width).toBeGreaterThan(vp.width * 0.4);
            expect(box!.height).toBeGreaterThan(200);
            expect(appErrors(errors)).toEqual([]);
        });

        test(`V02 ${vp.name}: tab và sidebar dùng được, chọn transect mở chi tiết`, async ({ page }) => {
            await openMap(page);
            if (vp.width < 600) {
                await page.click('#sidebar-toggle');
                await expect(page.locator('#sidebar')).not.toHaveClass(/collapsed/);
            }
            await openTab(page, 'hotspots');
            await expect(page.locator('#panel-hotspots')).toBeVisible();
            const t = await page.request.get('/api/transects').then((r) => r.json());
            await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.features[0].properties.id);
            await expect(page.locator('#inspector')).toBeVisible();
            const ins = await page.locator('#inspector').boundingBox();
            expect(ins!.x + ins!.width).toBeLessThanOrEqual(vp.width + 1);
            expect(ins!.x).toBeGreaterThanOrEqual(-1);
        });

        test(`V03 ${vp.name}: trang giới thiệu và quản trị không tràn ngang`, async ({ page }) => {
            for (const p of ['/gioi-thieu', '/admin']) {
                await page.goto(p);
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), p).toBe(true);
            }
        });

        test(`V04 ${vp.name}: sidebar ${vp.width >= 1000 ? 'mở sẵn' : 'tuỳ thiết bị'} và nút điều hướng nằm trong màn hình`, async ({ page }) => {
            await openMap(page);
            if (vp.width >= 1000) await expect(page.locator('#sidebar')).not.toHaveClass(/collapsed/);
            const nav = await page.locator('.topnav a').first().boundingBox();
            expect(nav!.x + nav!.width).toBeLessThanOrEqual(vp.width + 1);
        });
    });
}

test.describe('Hiệu năng cơ bản', () => {
    test('H01 trang bản đồ: DOMContentLoaded < 3s, dữ liệu API chính < 2s mỗi cuộc gọi', async ({ page }) => {
        const durations: Record<string, number> = {};
        page.on('requestfinished', async (r) => {
            if (r.url().includes('/api/')) durations[new URL(r.url()).pathname] = r.timing().responseEnd;
        });
        await openMap(page);
        const nav = await page.evaluate(() => {
            const n = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
            return { dcl: n.domContentLoadedEventEnd, load: n.loadEventEnd };
        });
        expect(nav.dcl).toBeLessThan(3000);
        expect(nav.load).toBeLessThan(8000);
        for (const [path, ms] of Object.entries(durations)) expect(ms, path).toBeLessThan(2000);
        expect(Object.keys(durations).length).toBeGreaterThan(0);
    });

    test('H02 API chính phản hồi nhanh (< 1.5s) và gọn (< 1MB)', async ({ request }) => {
        for (const p of ['/api/transects', '/api/summary', '/api/erosion-hotspots', '/api/status', '/api/config/thresholds']) {
            const t0 = Date.now();
            const res = await request.get(p);
            const body = await res.body();
            expect(res.ok(), p).toBe(true);
            expect(Date.now() - t0, p).toBeLessThan(1500);
            expect(body.length, p).toBeLessThan(1_000_000);
        }
    });

    test('H03 số request tải trang hợp lý và không có API gọi lặp', async ({ page }) => {
        const api: string[] = [];
        page.on('request', (r) => { if (r.url().includes('/api/')) api.push(new URL(r.url()).pathname + new URL(r.url()).search); });
        await openMap(page);
        await expect(page.locator('#hotspot-list li').first()).toBeAttached();
        expect(api.length).toBeLessThan(15);
        expect(api.filter((p) => p === '/api/summary')).toHaveLength(1);
    });
});
