import { test, expect } from '@playwright/test';
import { openMap, openTab, centerOnTransect, clickAt, API } from './helpers';
import { appErrors } from './helpers-journey';

test.describe('Hành trình người dùng', () => {
    test('J01 xem bản đồ -> lọc hotspot -> chọn transect -> xem lịch sử -> tải về', async ({ page, request }) => {
        const errors = await openMap(page);

        // 1. bản đồ + tổng quan khớp API
        const summary = await (await request.get('/api/summary')).json();
        await expect(page.locator('#summary-body')).not.toContainText('Đang tải');
        expect(summary).toBeTruthy();

        // 2. lọc hotspot theo ngưỡng 3 m/năm
        await openTab(page, 'hotspots');
        await page.fill('#min-rate', '3');
        await page.click('#apply-filter');
        const hs = await (await request.get('/api/erosion-hotspots?min_rate=3')).json();
        await expect(page.locator('#hotspot-list li')).toHaveCount(hs.features.length);
        expect(hs.features.length).toBeGreaterThan(0);

        // 3. chọn transect trên bản đồ
        const { id, lat, lng } = await centerOnTransect(page, 'T01');
        await clickAt(page, lat, lng);
        await expect(page.locator('#transect-code')).toHaveText('T01');
        await expect(page.locator('#inspector')).toBeVisible();

        // 4. lịch sử: số điểm và các năm khớp API
        const history = await (await request.get(`/api/transects/${id}/history`)).json();
        await expect(page.locator('#history-chart')).toHaveAttribute('data-points', String(history.length));
        await expect(page.locator('#history-chart')).toHaveAttribute('data-years', history.map((h: any) => h.year).join(','));
        await expect(page.locator('#stat-obs')).toContainText(`${history.length} năm`);

        // 5. tải về transects.csv từ tab chia sẻ
        await openTab(page, 'share');
        const [dl] = await Promise.all([
            page.waitForEvent('download'),
            page.locator('#panel-share .downloads a[href="/api/export/transects.csv"]').click(),
        ]);
        expect(dl.suggestedFilename()).toMatch(/transects.*\.csv$/);
        expect(dl.failure()).resolves.toBeNull();
        expect(appErrors(errors)).toEqual([]);
    });

    test('J02 hành trình tải GeoJSON: tệp tải về parse được và đủ 10 transect', async ({ page }) => {
        await page.goto('/');
        await openTab(page, 'share');
        const [dl] = await Promise.all([
            page.waitForEvent('download'),
            page.locator('#panel-share .downloads a[href="/api/export/transects.geojson"]').click(),
        ]);
        const fs = await import('node:fs');
        const fc = JSON.parse(fs.readFileSync((await dl.path())!, 'utf8'));
        expect(fc.type).toBe('FeatureCollection');
        expect(fc.features).toHaveLength(10);
    });

    test('J03 so sánh 2019 và 2024 rồi chọn transect: hai thao tác không làm mất nhau', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        await page.selectOption('#compare-a', '2019');
        await page.selectOption('#compare-b', '2024');
        await page.click('#compare-apply');
        await expect(page.locator('#status')).not.toContainText('Không có dữ liệu');
        const { lat, lng } = await centerOnTransect(page, 'T10');
        await clickAt(page, lat, lng);
        await expect(page.locator('#transect-code')).toHaveText('T10');
        expect(await page.evaluate(() => Object.keys((window as any).__webgis.layers).length)).toBeGreaterThan(0);
        await expect(page.locator('#compare-a')).toHaveValue('2019');
        await expect(page.locator('#compare-b')).toHaveValue('2024');
    });

    test('J04 chọn từ danh sách hotspot rồi đóng chi tiết bằng Escape', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        await page.locator('#hotspot-list li button').first().click();
        await expect(page.locator('.leaflet-popup-content')).toContainText('m/năm');
        const t1 = await API.transect(page, 'T01');
        await page.evaluate((id) => (window as any).__webgis.showTransect(id), t1.id);
        await expect(page.locator('#inspector')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('#inspector')).toBeHidden();
    });

    test('J05 đóng chi tiết bằng nút: hash bỏ tham số t', async ({ page, request }) => {
        await openMap(page);
        const t = await API.transect(page, 'T03');
        await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
        await expect.poll(() => page.evaluate(() => location.hash)).toContain(`t=${t.id}`);
        await page.click('#inspector-close');
        await expect(page.locator('#inspector')).toBeHidden();
        await expect.poll(() => page.evaluate(() => location.hash)).not.toContain('t=');
    });

    test('J06 chọn transect khác: panel đổi mã và số liệu theo transect mới', async ({ page, request }) => {
        await openMap(page);
        for (const code of ['T02', 'T07']) {
            const t = await API.transect(page, code);
            await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
            await expect(page.locator('#transect-code')).toHaveText(code);
            const d = await (await request.get(`/api/transects/${t.id}`)).json();
            await expect(page.locator('#stat-epr')).toContainText(Number(d.stats.epr_m_per_year).toFixed(2));
        }
    });
});

test.describe('Popup, tooltip, đồng bộ URL', () => {
    test('U01 popup transect hiện mã và mức biến động', async ({ page }) => {
        await openMap(page);
        const { lat, lng } = await centerOnTransect(page, 'T01');
        await clickAt(page, lat, lng);
        const popup = page.locator('.leaflet-popup-content');
        await expect(popup).toContainText('T01');
        await expect(popup).toContainText(/Bồi|Ổn định|Lùi nhẹ|Lùi mạnh/);
    });

    test('U02 popup hotspot: có tốc độ lùi bờ khớp danh sách', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        const first = page.locator('#hotspot-list li').first();
        const sev = await first.getAttribute('data-severity');
        await first.locator('button').click();
        await expect(page.locator('.leaflet-popup-content')).toContainText(`Tốc độ lùi bờ: ${sev} m/năm`);
    });

    test('U03 tooltip (title) giải thích NSM/EPR/LRR', async ({ page }) => {
        await openMap(page);
        const t = await API.transect(page, 'T01');
        await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
        await expect(page.locator('.stat-tile[title="Net Shoreline Movement"]')).toContainText('NSM');
        await expect(page.locator('.stat-tile[title="End Point Rate"]')).toContainText('EPR');
        await expect(page.locator('.stat-tile[title="Linear Regression Rate"]')).toContainText('LRR');
    });

    test('U04 hash đồng bộ khi chọn transect và đổi khi di chuyển', async ({ page }) => {
        await openMap(page);
        const t = await API.transect(page, 'T04');
        await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
        await expect.poll(() => page.evaluate(() => location.hash)).toMatch(new RegExp(`^#map=\\d+/[\\d.]+/[\\d.]+&t=${t.id}$`));
        const before = await page.evaluate(() => location.hash);
        await page.evaluate(() => (window as any).__webgis.map.setView([10.58, 105.2], 14, { animate: false }));
        await expect.poll(() => page.evaluate(() => location.hash)).toContain('map=14/10.58000/105.20000');
        expect(await page.evaluate(() => location.hash)).not.toBe(before);
    });

    test('U05 mở thẳng URL có hash: bản đồ đúng tâm/zoom và transect được chọn', async ({ page }) => {
        const t = await API.transect(page, 'T06');
        await page.goto(`/#map=15/10.58000/105.21000&t=${t.id}`);
        await page.waitForFunction(() => (window as any).__webgis?.map);
        await expect(page.locator('#transect-code')).toHaveText('T06');
        const v = await page.evaluate(() => { const m = (window as any).__webgis.map; return { z: m.getZoom(), c: m.getCenter() }; });
        expect(v.z).toBeGreaterThanOrEqual(15);
    });

    test('U06 reload giữ khung nhìn và transect đã chọn', async ({ page }) => {
        await openMap(page);
        const t = await API.transect(page, 'T08');
        await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
        await expect.poll(() => page.evaluate(() => location.hash)).toContain(`t=${t.id}`);
        const hash = await page.evaluate(() => location.hash);
        await page.reload();
        await page.waitForFunction(() => (window as any).__webgis?.map);
        await expect(page.locator('#transect-code')).toHaveText('T08');
        expect(await page.evaluate(() => location.hash)).toContain(`t=${t.id}`);
        expect(hash).toContain('map=');
    });

    test('U07 hash hỏng (map=abc, t=-5): bỏ qua, bản đồ vẫn dùng được, không lỗi console', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.goto('/#map=abc/x/y&t=-5');
        await page.waitForFunction(() => (window as any).__webgis?.map);
        await expect(page.locator('#inspector')).toBeHidden();
        expect(await page.evaluate(() => Number.isFinite((window as any).__webgis.map.getZoom()))).toBe(true);
        expect(errors).toEqual([]);
    });

    test('U08 hash trỏ transect không tồn tại: không crash', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.goto('/#map=15/10.58000/105.21000&t=999999');
        await page.waitForFunction(() => (window as any).__webgis?.map);
        await expect(page.locator('#map')).toBeVisible();
        expect(errors).toEqual([]);
    });
});

test.describe('Đa tab', () => {
    test('T01 hai tab cùng context có khung nhìn và lựa chọn độc lập', async ({ context }) => {
        const a = await context.newPage();
        const b = await context.newPage();
        await openMap(a);
        await openMap(b);
        await a.evaluate(() => (window as any).__webgis.map.setView([10.58, 105.2], 13, { animate: false }));
        await b.evaluate(() => (window as any).__webgis.map.setView([10.59, 105.21], 17, { animate: false }));
        expect(await a.evaluate(() => (window as any).__webgis.map.getZoom())).toBe(13);
        expect(await b.evaluate(() => (window as any).__webgis.map.getZoom())).toBe(17);
        const t = await API.transect(a, 'T01');
        await a.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
        await expect(a.locator('#inspector')).toBeVisible();
        await expect(b.locator('#inspector')).toBeHidden();
    });

    test('T02 mở liên kết chia sẻ ở tab mới tái tạo đúng khung nhìn', async ({ context }) => {
        const a = await context.newPage();
        await openMap(a);
        await a.evaluate(() => (window as any).__webgis.map.setView([10.585, 105.205], 15, { animate: false }));
        await expect.poll(() => a.evaluate(() => location.hash)).toContain('map=15/10.58500/105.20500');
        const url = a.url();
        const b = await context.newPage();
        await b.goto(url);
        await b.waitForFunction(() => (window as any).__webgis?.map);
        expect(await b.evaluate(() => (window as any).__webgis.map.getZoom())).toBe(15);
        const c = await b.evaluate(() => (window as any).__webgis.map.getCenter());
        expect(c.lat).toBeCloseTo(10.585, 3);
        expect(c.lng).toBeCloseTo(105.205, 3);
    });
});
