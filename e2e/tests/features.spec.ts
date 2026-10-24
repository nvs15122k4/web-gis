import { test, expect } from '@playwright/test';
import { openMap, openTab, API } from './helpers';

/** Các tính năng tham khảo từ DEA Coastlines, USGS Coastal Change Hazards Portal và bản đồ sạt lở ĐBSCL. */
test.describe('Tính năng bổ sung theo WebGIS tham chiếu', () => {
    test('13 tổng quan: đếm đúng 4 mức, bấm transect lùi mạnh nhất mở chi tiết', async ({ page }) => {
        await openMap(page);
        await expect(page.locator('#kpi-transects')).toHaveText('10');
        await expect(page.locator('#summary [data-class="severe_erosion"]')).toHaveText('3');
        await expect(page.locator('#summary [data-class="accretion"]')).toHaveText('2');
        await page.locator('#kpi-max-erosion a').click();
        await expect(page.locator('#transect-code')).toHaveText('T10');
    });

    test('14 lọc ý nghĩa thống kê: WMS transect gửi CQL_FILTER is_significant=true', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        const req = page.waitForRequest((r) => /transects/.test(r.url()) && /cql_filter=is_significant%3Dtrue/i.test(r.url()));
        await page.locator('#only-significant').check();
        await req;
        const off = page.waitForRequest((r) => /transects/.test(r.url()) && /cql_filter=INCLUDE/i.test(r.url()));
        await page.locator('#only-significant').uncheck();
        await off;
    });

    test('15 chi tiết transect: ý nghĩa thống kê, KTC 95%, số năm quan trắc', async ({ page }) => {
        await openMap(page);
        const t = await API.transect(page, 'T05');
        await page.evaluate((id) => (window as any).__webgis.showTransect(id), t.id);
        await expect(page.locator('#stat-sig')).toContainText('Không');
        await expect(page.locator('#stat-obs')).toHaveText('6 năm (2018–2026)');
        await expect(page.locator('#stat-ci')).toContainText('m/năm');
    });

    test('16 liên kết chia sẻ: mở lại đúng khung nhìn và transect', async ({ page }) => {
        const t = await API.transect(page, 'T09');
        await page.goto(`/#map=16/10.59200/105.20700&t=${t.id}`);
        await page.waitForFunction(() => (window as any).__webgis?.map);
        await expect(page.locator('#transect-code')).toHaveText('T09');
        expect(await page.evaluate(() => (window as any).__webgis.map.getZoom())).toBe(16);
        await page.evaluate(() => (window as any).__webgis.map.setZoom(15, { animate: false }));
        await expect.poll(() => page.evaluate(() => location.hash)).toContain('map=15/');
        expect(await page.evaluate(() => location.hash)).toContain(`t=${t.id}`);
    });

    test('17 sạt lở tham chiếu: popup có mức độ QĐ 01/2011, thiếu dữ liệu ghi "Đang cập nhật"', async ({ page }) => {
        await openMap(page);
        const fc = await (await page.request.get('/api/reference-events')).json();
        const [lng, lat] = fc.features[0].geometry.coordinates;
        await page.evaluate(([lat, lng]) => (window as any).__webgis.map.setView([lat, lng], 14, { animate: false }), [lat, lng]);
        await openTab(page, 'layers');
        await page.locator('#layer-reference').check();
        await expect(page.locator('#reference-status')).toContainText('2 sự kiện');

        const texts = await page.evaluate(() => (window as any).__webgis.layers.reference.getLayers().map((l: any) => {
            l.openPopup();
            return l.getPopup().getElement().querySelector('.leaflet-popup-content').textContent;
        }));
        const known = texts.find((s: string) => s.includes('2019-08-14'))!;
        expect(known).toContain('Nguy hiểm');
        expect(known).toContain('Ấp Bình Hòa');
        expect(known).toContain('120 m');
        expect(texts.find((s: string) => s.includes('2020-03-02'))).toContain('Đang cập nhật');
    });

    test('18 nền bản đồ: mặc định nền tự phục vụ, chuyển sang ảnh vệ tinh', async ({ page }) => {
        await openMap(page);
        // Nền mặc định lấy từ GeoServer của dự án (không có "đường lưỡi bò"), không phải tile bên thứ ba.
        await expect(page.locator('.leaflet-basemap-pane img[src*="nen_ban_do"]').first()).toBeAttached();
        await page.locator('.leaflet-control-layers').hover();
        await page.getByLabel('Ảnh vệ tinh (Esri)').check();
        await expect(page.locator('.leaflet-basemap-pane img[src*="World_Imagery"]').first()).toBeAttached();
        // Nền tải bất đồng bộ (thêm sau lớp WMS) nhưng phải luôn nằm dưới, không che transect/đường bờ.
        const z = await page.evaluate(() => ['.leaflet-basemap-pane', '.leaflet-tile-pane']
            .map((s) => Number(getComputedStyle(document.querySelector(s)!).zIndex)));
        expect(z[0]).toBeLessThan(z[1]);
    });

    test('21 khung nhìn neo vào đoạn sông Hậu – xã Bình Mỹ, vẫn thu phóng được', async ({ page }) => {
        await page.goto('/#map=8/13.50000/111.00000');
        await page.waitForFunction(() => (window as any).__webgis?.map);
        const inArea = (c: { lat: number; lng: number }) => c.lat >= 10.40 && c.lat <= 10.62 && c.lng >= 105.12 && c.lng <= 105.42;
        // Liên kết trỏ ra Biển Đông vẫn bị kéo về khu vực nghiên cứu.
        expect(inArea(await page.evaluate(() => (window as any).__webgis.map.getCenter()))).toBe(true);
        const view = await page.evaluate(() => {
            const m = (window as any).__webgis.map;
            m.setZoom(3, { animate: false });
            const out = m.getZoom();
            m.setZoom(17, { animate: false });
            m.panBy([20000, -20000], { animate: false });
            return { out, min: m.getMinZoom(), zoom: m.getZoom(), center: m.getCenter() };
        });
        expect(view.out).toBe(view.min);
        expect(view.min).toBeLessThanOrEqual(6);
        expect(view.zoom).toBe(17);
        expect(inArea(view.center)).toBe(true);
    });

    test('19 trang giới thiệu & tải dữ liệu', async ({ page }) => {
        await page.goto('/gioi-thieu');
        await expect(page.getByRole('heading', { name: 'Tiêu chí phân loại mức biến động (Mục 2.5 đề cương)' })).toBeVisible();
        await expect(page.locator('td[data-layer="transects"]')).toHaveText('10 bản ghi');
        const res = await page.request.get('/api/export/hotspots.geojson');
        expect(res.ok()).toBeTruthy();
        expect((await res.json()).features).toHaveLength(2);
    });

    test('20 chưa có dữ liệu: các panel ghi "Đang cập nhật", không crash', async ({ page }) => {
        const down = { status: 503, contentType: 'application/json', body: '{"message":"Dữ liệu đang cập nhật.","status":"updating"}' };
        for (const path of ['summary', 'erosion-hotspots', 'config/thresholds']) {
            await page.route(`**/api/${path}**`, (r) => r.fulfill(down));
        }
        await page.route('**/api/status', (r) => r.fulfill({
            contentType: 'application/json',
            body: JSON.stringify({ mysql: true, postgis: false, geoserver: false, data: {} }),
        }));
        const errors = await openMap(page);
        await expect(page.locator('#data-banner')).toContainText('đang cập nhật');
        await expect(page.locator('#summary-body')).toContainText('đang cập nhật');
        await expect(page.locator('#hotspot-count')).toContainText('đang cập nhật');
        expect(errors.filter((e) => !/google\.com|arcgisonline|ERR_|Failed to load resource/i.test(e))).toEqual([]);
    });
});
