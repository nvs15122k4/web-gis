import { test, expect } from '@playwright/test';
import { openMap, openTab, centerOnTransect, clickAt } from './helpers';

const fmt = (v: number | null, d = 2) => (v == null ? '—' : Number(v).toFixed(d));

test.describe('Bản đồ WebGIS sông Hậu', () => {
    test('01 tải trang: bản đồ + AOI render, không lỗi console', async ({ page }) => {
        const errors = await openMap(page);
        await expect(page.locator('#map .leaflet-overlay-pane path').first()).toBeAttached(); // AOI
        const t0 = Date.now();
        await expect(page.locator('#map .leaflet-tile-loaded').first()).toBeVisible({ timeout: 3000 });
        expect(Date.now() - t0).toBeLessThan(3000);
        // bỏ qua lỗi tải tile nền (Esri) do mạng ngoài, chỉ quan tâm lỗi của ứng dụng
        const appErrors = errors.filter((e) => !/arcgisonline|ERR_|Failed to load resource/i.test(e));
        expect(appErrors).toEqual([]);
    });

    test('02 layer control: bật/tắt lớp Đường bờ 2020', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        const req = page.waitForRequest((r) => /shorelines/.test(r.url()) && /year%3D2020|year=2020/i.test(r.url()));
        await page.locator('#year-2020').check();
        await req;
        expect(await page.evaluate(() => { const w = (window as any).__webgis; return w.map.hasLayer(w.layers.years.get(2020)); })).toBe(true);
        await page.locator('#year-2020').uncheck();
        expect(await page.evaluate(() => { const w = (window as any).__webgis; return w.map.hasLayer(w.layers.years.get(2020)); })).toBe(false);
        // lớp transect cũng bật/tắt theo checkbox
        await page.locator('#layer-transects').uncheck();
        expect(await page.evaluate(() => { const w = (window as any).__webgis; return w.map.hasLayer(w.layers.transects); })).toBe(false);
    });

    test('03 so sánh năm: 2019 & 2024 hiển thị đồng thời, hai màu khác nhau', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        await page.selectOption('#compare-a', '2019');
        await page.selectOption('#compare-b', '2024');
        await page.click('#compare-apply');
        const info = await page.evaluate(() => {
            const w = (window as any).__webgis;
            const { a, b } = w.layers.compare;
            return { onA: w.map.hasLayer(a), onB: w.map.hasLayer(b), envA: a.wmsParams.env, envB: b.wmsParams.env, fa: a.wmsParams.cql_filter, fb: b.wmsParams.cql_filter };
        });
        expect(info.onA && info.onB).toBe(true);
        expect(info.envA).not.toBe(info.envB);
        expect([info.fa, info.fb]).toEqual(['year=2019', 'year=2024']);
    });

    test('04 chi tiết transect: click hiển thị NSM/EPR/LRR/R² khớp API', async ({ page }) => {
        await openMap(page);
        const { id, lat, lng } = await centerOnTransect(page, 'T09');
        await clickAt(page, lat, lng);
        await expect(page.locator('#transect-code')).toHaveText('T09');
        const s = (await (await page.request.get(`/api/transects/${id}`)).json()).stats;
        await expect(page.locator('#stat-nsm')).toHaveText(`${fmt(s.nsm_m)} m`);
        await expect(page.locator('#stat-epr')).toHaveText(`${fmt(s.epr_m_per_year)} m/năm`);
        await expect(page.locator('#stat-lrr')).toHaveText(`${fmt(s.lrr_m_per_year)} m/năm`);
        await expect(page.locator('#stat-r2')).toHaveText(fmt(s.r_squared, 3));
        await expect(page.locator('#stat-class')).toHaveText('Lùi mạnh');
    });

    test('05 biểu đồ: số điểm = số năm có dữ liệu', async ({ page }) => {
        await openMap(page);
        const { id, lat, lng } = await centerOnTransect(page, 'T01');
        await clickAt(page, lat, lng);
        const history = await (await page.request.get(`/api/transects/${id}/history`)).json();
        await expect(page.locator('#history-chart')).toHaveAttribute('data-points', String(history.length));
        expect(history.length).toBe(8);
    });

    test('06 lọc ngưỡng: danh sách hotspot cập nhật theo API', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        await expect(page.locator('#hotspot-list li')).toHaveCount(2); // ngưỡng mặc định 1.0
        await page.fill('#min-rate', '3');
        await page.click('#apply-filter');
        await expect(page.locator('#hotspot-list li')).toHaveCount(1);
        await expect(page.locator('#hotspot-list li')).toContainText('4.8');
        await page.fill('#min-rate', '99');
        await page.click('#apply-filter');
        await expect(page.locator('#hotspot-list li')).toHaveCount(0);
        await expect(page.locator('#hotspot-count')).toContainText('0 đoạn');
    });

    test('07 sạt lở tham chiếu: điểm đúng vị trí, popup đủ thông tin', async ({ page }) => {
        await openMap(page);
        const api = await (await page.request.get('/api/reference-events')).json();
        const [lng, lat] = api.features[0].geometry.coordinates;
        await page.evaluate(([lat, lng]) => (window as any).__webgis.map.setView([lat, lng], 15, { animate: false }), [lat, lng]);
        await openTab(page, 'layers');
        await page.locator('#layer-reference').check();
        await expect(page.locator('#reference-status')).toContainText('sự kiện trong khung nhìn');
        const first = await page.evaluate(() => {
            const l = (window as any).__webgis.layers.reference.getLayers()[0];
            l.openPopup();
            const ll = l.getLatLng();
            return { lat: ll.lat, lng: ll.lng };
        });
        expect(first.lat).toBeCloseTo(lat, 5);
        expect(first.lng).toBeCloseTo(lng, 5);
        const popup = page.locator('.leaflet-popup-content');
        await expect(popup).toContainText('2019-08-14');
        await expect(popup).toContainText('Nguồn');
    });

    test('08 biên: năm không có composite → báo không có dữ liệu, không crash', async ({ page }) => {
        const errors = await openMap(page);
        await openTab(page, 'layers');
        await page.locator('#year-2022').click(); // ứng dụng tự bỏ chọn nên không dùng check()
        await expect(page.locator('#status')).toContainText('Không có dữ liệu');
        await expect(page.locator('#year-2022')).not.toBeChecked();
        await page.selectOption('#compare-a', '2022');
        await page.click('#compare-apply');
        await expect(page.locator('#status')).toContainText('2022');
        await expect(page.locator('#map')).toBeVisible();
        expect(errors.filter((e) => !/arcgisonline|ERR_|Failed to load resource/i.test(e))).toEqual([]);
    });

    test('09 biên: transect thiếu năm → chart bỏ điểm thiếu, không nội suy', async ({ page }) => {
        await openMap(page);
        const { lat, lng } = await centerOnTransect(page, 'T05');
        await clickAt(page, lat, lng);
        await expect(page.locator('#transect-code')).toHaveText('T05');
        await expect(page.locator('#history-chart')).toHaveAttribute('data-years', '2018,2019,2023,2024,2025,2026');
        await expect(page.locator('#history-chart')).toHaveAttribute('data-points', '6');
    });

    test('10 biên: vùng không có sạt lở tham chiếu → rỗng hợp lệ, không lỗi', async ({ page }) => {
        const errors = await openMap(page);
        await page.evaluate(() => (window as any).__webgis.map.setView([10.597, 105.21], 16, { animate: false }));
        await openTab(page, 'layers');
        await page.locator('#layer-reference').check();
        await expect(page.locator('#reference-status')).toContainText('Không có sự kiện');
        expect(await page.evaluate(() => (window as any).__webgis.layers.reference.getLayers().length)).toBe(0);
        expect(errors.filter((e) => !/arcgisonline|ERR_|Failed to load resource/i.test(e))).toEqual([]);
    });

    test('11 responsive 400px: sidebar thu gọn, bản đồ full-width, vẫn thao tác được', async ({ page }) => {
        await page.setViewportSize({ width: 400, height: 800 });
        await openMap(page);
        await expect(page.locator('#sidebar')).toHaveClass(/collapsed/);
        const map = await page.locator('#map').boundingBox();
        expect(map!.width).toBeGreaterThanOrEqual(399);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        // bản đồ vẫn zoom được
        const z0 = await page.evaluate(() => (window as any).__webgis.map.getZoom());
        await page.click('.leaflet-control-zoom-in');
        await expect.poll(() => page.evaluate(() => (window as any).__webgis.map.getZoom())).toBeGreaterThan(z0);
        // mở sidebar bằng nút, dùng được điều khiển bên trong
        await page.click('#sidebar-toggle');
        await expect(page.locator('#sidebar')).not.toHaveClass(/collapsed/);
        await openTab(page, 'layers');
        await page.locator('#year-2020').check();
        await expect(page.locator('#year-2020')).toBeChecked();
    });

    test('12 đăng nhập admin: sai/viewer bị từ chối, admin sửa ngưỡng và UI phản ánh ngay', async ({ page }) => {
        await page.goto('/admin');
        const login = async (email: string, password: string) => {
            await page.fill('#login-form [name=email]', email);
            await page.fill('#login-form [name=password]', password);
            await page.click('#login-form button[type=submit]');
        };

        await login('admin@webgis.test', 'sai-mat-khau');
        await expect(page.locator('#admin-message')).toContainText('Sai email hoặc mật khẩu');
        await expect(page.locator('#admin-panel')).toBeHidden();

        // tài khoản không phải admin: đăng nhập được nhưng sửa ngưỡng bị từ chối
        await login('viewer@webgis.test', 'password');
        await expect(page.locator('#admin-panel')).toBeVisible();
        await page.fill('.threshold-form input[name=value]', '9');
        await page.click('.threshold-form button');
        await expect(page.locator('#admin-message')).toContainText('Không đủ quyền');
        await page.click('#logout');

        // admin: sửa ngưỡng thành công
        await page.waitForSelector('#login-form');
        await login('admin@webgis.test', 'password');
        await page.fill('.threshold-form input[name=value]', '2.5');
        await page.click('.threshold-form button');
        await expect(page.locator('#admin-message')).toContainText('2.5');

        // phản ánh ngay trên bản đồ: ngưỡng mặc định = 2.5 → chỉ còn 1 hotspot (4.8 m/năm)
        await page.goto('/');
        await expect(page.locator('#min-rate')).toHaveValue('2.5');
        await expect(page.locator('#hotspot-list li')).toHaveCount(1);

        // trả về mặc định để không ảnh hưởng các project trình duyệt sau
        await page.goto('/admin');
        await login('admin@webgis.test', 'password');
        await page.fill('.threshold-form input[name=value]', '1.0');
        await page.click('.threshold-form button');
        await expect(page.locator('#admin-message')).toContainText('1.0');
    });
});
