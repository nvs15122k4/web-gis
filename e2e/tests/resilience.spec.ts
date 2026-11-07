import { test, expect } from '@playwright/test';
import { openMap, openTab } from './helpers';
import { openMapLoose, appErrors } from './helpers-journey';

// Các test dưới đây là "partial E2E (mocked: API/GeoServer)": giả lập lỗi hạ tầng bằng page.route
// vì không thể làm hỏng dịch vụ thật dùng chung. Mọi test đều chặn trước khi tải trang.
test.describe('Chịu lỗi: API', () => {
    for (const status of [500, 503]) {
        test(`E01 /api/summary trả ${status}: panel tổng quan báo đang cập nhật, phần còn lại dùng được`, async ({ page }) => {
            await page.route('**/api/summary', (r) => r.fulfill({ status, json: { message: 'lỗi' } }));
            const errors: string[] = [];
            page.on('pageerror', (e) => errors.push(e.message));
            await openMapLoose(page);
            await expect(page.locator('#summary-body')).toContainText('Số liệu tổng quan: đang cập nhật');
            await openTab(page, 'hotspots');
            await expect(page.locator('#hotspot-list li').first()).toBeAttached();
            expect(errors).toEqual([]);
        });

        test(`E02 /api/erosion-hotspots trả ${status}: báo "đang cập nhật", danh sách rỗng, không crash`, async ({ page }) => {
            await page.route('**/api/erosion-hotspots*', (r) => r.fulfill({ status, json: { message: 'lỗi' } }));
            const errors: string[] = [];
            page.on('pageerror', (e) => errors.push(e.message));
            await openMapLoose(page);
            await openTab(page, 'hotspots');
            await expect(page.locator('#hotspot-count')).toContainText('đang cập nhật');
            await expect(page.locator('#hotspot-list li')).toHaveCount(0);
            expect(errors).toEqual([]);
        });
    }

    test('E03 /api/transects/{id} trả 500 khi mở chi tiết: không crash, bản đồ vẫn dùng', async ({ page }) => {
        await openMapLoose(page);
        const t = await (await page.request.get('/api/transects')).json();
        const id = t.features[0].properties.id;
        await page.route(`**/api/transects/${id}`, (r) => r.fulfill({ status: 500, json: {} }));
        const rejected = await page.evaluate(async (i) => {
            try { await (window as any).__webgis.showTransect(i); return false; } catch { return true; }
        }, id);
        expect(rejected).toBe(true); // lỗi được trả về cho nơi gọi, không nuốt thầm
        await expect(page.locator('#inspector')).toBeHidden();
        await expect(page.locator('#map')).toBeVisible();
    });

    test('E04 /api/status trả 503: banner báo dữ liệu đang cập nhật', async ({ page }) => {
        await page.route('**/api/status', (r) => r.fulfill({ status: 503, json: {} }));
        await openMapLoose(page);
        await expect(page.locator('#data-banner')).toBeVisible();
        await expect(page.locator('#data-banner')).toContainText('đang cập nhật');
    });

    test('E05 /api/config/thresholds lỗi 500: ô ngưỡng hiện placeholder "Đang cập nhật" nhưng vẫn lọc được', async ({ page }) => {
        await page.route('**/api/config/thresholds', (r) => r.fulfill({ status: 500, json: {} }));
        await openMapLoose(page);
        await openTab(page, 'hotspots');
        await expect(page.locator('#min-rate')).toHaveAttribute('placeholder', 'Đang cập nhật');
        await page.fill('#min-rate', '3');
        await page.click('#apply-filter');
        await expect(page.locator('#hotspot-count')).toContainText('từ 3 m/năm');
    });

    test('E06 API trả JSON hỏng (200 nhưng không phải JSON): không có pageerror chưa bắt', async ({ page }) => {
        await page.route('**/api/erosion-hotspots*', (r) => r.fulfill({ status: 200, contentType: 'application/json', body: '{hong' }));
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await openMapLoose(page);
        await openTab(page, 'hotspots');
        await expect(page.locator('#hotspot-count')).toContainText('đang cập nhật');
        expect(errors).toEqual([]);
    });

    test('E07 reference-events lỗi 500 khi bật lớp: báo trạng thái, checkbox không crash', async ({ page }) => {
        await page.route('**/api/reference-events*', (r) => r.fulfill({ status: 500, json: {} }));
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await openMapLoose(page);
        await openTab(page, 'layers');
        await page.locator('#layer-reference').check();
        await expect(page.locator('#reference-status')).not.toBeEmpty();
        expect(errors).toEqual([]);
    });
});

test.describe('Chịu lỗi: chậm và mất mạng', () => {
    test('E08 API chậm: hiện "Đang tải…" trong lúc chờ rồi thay bằng dữ liệu thật', async ({ page }) => {
        let release!: () => void;
        const gate = new Promise<void>((res) => { release = res; });
        await page.route('**/api/summary', async (r) => { await gate; await r.continue(); });
        await openMapLoose(page);
        await expect(page.locator('#summary-body')).toContainText('Đang tải');
        release();
        await expect(page.locator('#summary-body')).not.toContainText('Đang tải');
    });

    test('E09 hotspot chậm: bản đồ và tab khác vẫn thao tác được trong lúc chờ', async ({ page }) => {
        let release!: () => void;
        const gate = new Promise<void>((res) => { release = res; });
        await page.route('**/api/erosion-hotspots*', async (r) => { await gate; await r.continue(); });
        await openMapLoose(page);
        await openTab(page, 'layers');
        await expect(page.locator('#panel-layers')).toBeVisible();
        await page.click('.leaflet-control-zoom-in');
        release();
        await openTab(page, 'hotspots');
        await expect(page.locator('#hotspot-list li').first()).toBeAttached();
    });

    test('E10 mất mạng sau khi tải: bấm Lọc báo "đang cập nhật", khôi phục mạng lọc lại được', async ({ page, context }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        await context.setOffline(true);
        await page.fill('#min-rate', '2');
        await page.click('#apply-filter');
        await expect(page.locator('#hotspot-count')).toContainText('đang cập nhật');
        await context.setOffline(false);
        await page.click('#apply-filter');
        await expect(page.locator('#hotspot-count')).toContainText('từ 2 m/năm');
        await expect(page.locator('#hotspot-count')).not.toContainText('đang cập nhật');
    });

    test('E11 mất mạng sau khi tải: trang admin đăng nhập báo lỗi thay vì treo', async ({ page, context }) => {
        await page.goto('/admin');
        await context.setOffline(true);
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.fill('#login-form [name=password]', 'password');
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-panel')).toBeHidden();
        await context.setOffline(false);
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-panel')).toBeVisible();
    });
});

test.describe('Chịu lỗi: GeoServer', () => {
    test('E12 GeoServer chết (WMS bị chặn): trang vẫn tải, API/hotspot/panel dùng được, không pageerror', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.route('**/geoserver/**', (r) => r.abort('connectionrefused'));
        await openMapLoose(page);
        await openTab(page, 'hotspots');
        await expect(page.locator('#hotspot-list li').first()).toBeAttached();
        await expect(page.locator('#summary-body')).not.toContainText('Đang tải');
        expect(errors).toEqual([]);
    });

    test('E13 GeoServer chết: click bản đồ không mở chi tiết và báo "đang cập nhật"', async ({ page }) => {
        await page.route('**/geoserver/**', (r) => r.abort('connectionrefused'));
        await openMapLoose(page);
        await page.mouse.click(300, 400);
        await expect(page.locator('#inspector')).toBeHidden();
    });

    test('E14 GeoServer trả 503: click transect báo trạng thái "Thông tin transect (GeoServer)"', async ({ page }) => {
        await openMap(page);
        const t = await (await page.request.get('/api/transects')).json();
        const [[lng1, lat1], [lng2]] = t.features[0].geometry.coordinates;
        await page.evaluate(([la, ln]) => (window as any).__webgis.map.setView([la, ln], 16, { animate: false }), [lat1, lng1 + (lng2 - lng1) / 2]);
        await page.route('**/geoserver/**/wms?*GetFeatureInfo*', (r) => r.fulfill({ status: 503, body: 'x' }));
        const c = await page.evaluate(() => { const m = (window as any).__webgis.map.getContainer().getBoundingClientRect(); return { x: m.left + m.width / 2, y: m.top + m.height / 2 }; });
        await page.mouse.click(c.x, c.y);
        await expect(page.locator('#status')).toContainText('Thông tin transect (GeoServer)');
        await expect(page.locator('#inspector')).toBeHidden();
    });

    test('E15 /api/status báo geoserver=false: banner nêu lớp GeoServer đang cập nhật', async ({ page }) => {
        await page.route('**/api/status', (r) => r.fulfill({ json: { mysql: true, postgis: true, geoserver: false, data: { shorelines: true, transects: true, hotspots: true, reference_events: true } } }));
        await openMapLoose(page);
        await expect(page.locator('#data-banner')).toContainText('Lớp bản đồ GeoServer: đang cập nhật');
    });

    test('E16 /api/status báo bảng rỗng: banner liệt kê đúng lớp thiếu', async ({ page }) => {
        await page.route('**/api/status', (r) => r.fulfill({ json: { mysql: true, postgis: true, geoserver: true, data: { shorelines: true, transects: true, hotspots: false, reference_events: false } } }));
        await openMapLoose(page);
        await expect(page.locator('#data-banner')).toContainText('hotspot, sạt lở tham chiếu');
        await expect(page.locator('#data-banner')).not.toContainText('đường bờ');
    });
});

test.describe('Console và network sạch', () => {
    test('C01 duyệt bản đồ + các tab: không có phản hồi 4xx/5xx của ứng dụng', async ({ page }) => {
        const bad: string[] = [];
        page.on('response', (r) => { if (r.status() >= 400 && new URL(r.url()).origin === 'http://127.0.0.1:8010') bad.push(`${r.status()} ${r.url()}`); });
        const errors = await openMap(page);
        for (const tab of ['hotspots', 'share', 'overview', 'layers']) await openTab(page, tab);
        await page.locator('#year-2020').check();
        await expect(page.locator('#year-2020')).toBeChecked();
        expect(bad).toEqual([]);
        expect(appErrors(errors)).toEqual([]);
    });

    test('C02 trang giới thiệu và quản trị: không 4xx/5xx, không lỗi console', async ({ page }) => {
        const bad: string[] = [];
        const errs: string[] = [];
        page.on('response', (r) => { if (r.status() >= 400 && new URL(r.url()).origin === 'http://127.0.0.1:8010') bad.push(`${r.status()} ${r.url()}`); });
        page.on('console', (m) => m.type() === 'error' && errs.push(m.text()));
        page.on('pageerror', (e) => errs.push(e.message));
        await page.goto('/gioi-thieu');
        await page.goto('/admin');
        await expect(page.locator('#login-form')).toBeVisible();
        expect(bad).toEqual([]);
        expect(appErrors(errs)).toEqual([]);
    });

    test('C03 đăng nhập sai chỉ sinh đúng một 401 từ /api/auth/login (là lỗi mong đợi)', async ({ page }) => {
        const codes: number[] = [];
        page.on('response', (r) => { if (r.url().includes('/api/auth/login')) codes.push(r.status()); });
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.fill('#login-form [name=password]', 'sai');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-message')).toContainText('Sai email hoặc mật khẩu');
        expect(codes).toEqual([401]);
    });

    test('C04 trang 404 không để lộ stack trace', async ({ page }) => {
        const res = await page.goto('/khong-co-trang-nay');
        expect(res!.status()).toBe(404);
        const text = await page.locator('body').innerText();
        expect(text).not.toMatch(/Illuminate\\|vendor\/|Stack trace/);
    });

    test('C05 API lỗi trả JSON, không lộ stack trace', async ({ request }) => {
        const res = await request.get('/api/transects/999999', { headers: { Accept: 'application/json' } });
        expect(res.status()).toBe(404);
        const body = await res.text();
        expect(body).not.toMatch(/vendor\/|Illuminate\\/);
    });
});
