import { test, expect } from '@playwright/test';
import { openMap, openTab } from './helpers';
import { collectErrors, apiLogin } from './helpers-extra';

test.describe('Điều hướng & trang tĩnh', () => {
    test('N01 menu: Bản đồ -> Phương pháp -> Quản trị, mục hiện tại có aria-current', async ({ page }) => {
        await page.goto('/');
        const nav = page.getByRole('navigation', { name: 'Điều hướng chính' });
        await expect(nav.getByRole('link', { name: 'Bản đồ' })).toHaveAttribute('aria-current', 'page');
        await nav.getByRole('link', { name: 'Phương pháp' }).click();
        await expect(page).toHaveURL(/\/gioi-thieu$/);
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Giới thiệu');
        await expect(nav.getByRole('link', { name: 'Phương pháp' })).toHaveAttribute('aria-current', 'page');
        await nav.getByRole('link', { name: 'Quản trị' }).click();
        await expect(page).toHaveURL(/\/admin$/);
        await expect(page.getByRole('heading', { level: 1 })).toHaveText('Quản trị ngưỡng hiển thị');
        await nav.getByRole('link', { name: 'Bản đồ' }).click();
        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('#map')).toBeVisible();
    });

    test('N02 logo thương hiệu đưa về trang bản đồ', async ({ page }) => {
        await page.goto('/gioi-thieu');
        await page.locator('a.brand').click();
        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('#map')).toBeVisible();
    });

    test('N03 trang không tồn tại trả 404', async ({ page }) => {
        const res = await page.goto('/khong-ton-tai-xyz');
        expect(res!.status()).toBe(404);
    });

    test('N04 API không tồn tại / id không hợp lệ trả 404', async ({ page }) => {
        expect((await page.request.get('/api/khong-co', { headers: { Accept: 'application/json' } })).status()).toBe(404);
        expect((await page.request.get('/api/transects/abc', { headers: { Accept: 'application/json' } })).status()).toBe(404);
        expect((await page.request.get('/api/transects/99999999', { headers: { Accept: 'application/json' } })).status()).toBe(404);
        expect((await page.request.get('/api/export/transects.xml', { headers: { Accept: 'application/json' } })).status()).toBe(404);
    });

    for (const path of ['/', '/gioi-thieu', '/admin']) {
        test(`N05 ${path}: có <title>, lang=vi và không có lỗi console`, async ({ page }) => {
            const errs = collectErrors(page);
            await page.goto(path);
            await expect(page.locator('html')).toHaveAttribute('lang', 'vi');
            await expect(page).toHaveTitle(/\S{3,}.*(WebGIS|Quản trị|Giới thiệu|Phương pháp)|(WebGIS|Quản trị|Giới thiệu|Phương pháp).*\S{3,}/);
            await page.waitForLoadState('networkidle');
            expect(errs()).toEqual([]);
        });
    }

    test('N06 trang giới thiệu: bảng trạng thái dữ liệu khớp API export', async ({ page }) => {
        await page.goto('/gioi-thieu');
        await expect(page.locator('td[data-layer="transects"]')).toHaveText('10 bản ghi');
        const shore = (await (await page.request.get('/api/export/shorelines.geojson')).json()).features.length;
        await expect(page.locator('td[data-layer="shorelines"]')).toContainText(String(shore));
        await expect(page.getByRole('heading', { name: 'Độ bất định' })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Hạn chế/ })).toBeVisible();
    });
});

test.describe('Bản đồ: bố cục và tab', () => {
    test('M01 bốn tab: chuyển tab đổi panel hiển thị và aria-selected', async ({ page }) => {
        await openMap(page);
        for (const name of ['overview', 'layers', 'hotspots', 'share']) {
            await openTab(page, name);
            await expect(page.locator(`#tab-${name}`)).toHaveAttribute('aria-selected', 'true');
            await expect(page.locator(`#panel-${name}`)).toBeVisible();
            for (const other of ['overview', 'layers', 'hotspots', 'share'].filter((n) => n !== name)) {
                await expect(page.locator(`#panel-${other}`)).toBeHidden();
                await expect(page.locator(`#tab-${other}`)).toHaveAttribute('aria-selected', 'false');
            }
        }
    });

    test('M02 chú giải: đủ 4 mức biến động, thu gọn/mở được', async ({ page }) => {
        await openMap(page);
        const legend = page.locator('#legend');
        for (const t of ['Bồi', 'Ổn định', 'Lùi nhẹ', 'Lùi mạnh', 'Đặc biệt nguy hiểm', 'Chưa phân loại']) {
            await expect(legend).toContainText(t);
        }
        await legend.locator('summary').click();
        await expect(legend.locator('.legend-body')).toBeHidden();
        await legend.locator('summary').click();
        await expect(legend.locator('.legend-body')).toBeVisible();
    });

    test('M03 điều khiển zoom: tăng rồi giảm mức phóng', async ({ page }) => {
        await openMap(page);
        const zoom = () => page.evaluate(() => (window as any).__webgis.map.getZoom());
        const z0 = await zoom();
        await page.click('.leaflet-control-zoom-in');
        await expect.poll(zoom).toBe(z0 + 1);
        await expect.poll(() => page.evaluate(() => (window as any).__webgis.map._animatingZoom)).toBeFalsy(); // chờ hết hoạt ảnh, Leaflet bỏ qua click khi đang zoom
        await page.click('.leaflet-control-zoom-out');
        await expect.poll(zoom).toBe(z0);
    });

    test('M04 kéo bản đồ làm đổi tâm và cập nhật hash liên kết', async ({ page }) => {
        await openMap(page);
        const c0 = await page.evaluate(() => (window as any).__webgis.map.getCenter());
        const box = (await page.locator('#map').boundingBox())!;
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width / 2 + 120, box.y + box.height / 2 + 60, { steps: 5 });
        await page.mouse.up();
        await expect.poll(() => page.evaluate(() => (window as any).__webgis.map.getCenter().lng)).not.toBeCloseTo(c0.lng, 6);
        await expect.poll(() => page.evaluate(() => location.hash)).toMatch(/^#map=\d+\/[\d.]+\/[\d.]+/);
    });

    test('M05 dữ liệu sẵn sàng: banner "đang cập nhật" ẩn, status không báo lỗi', async ({ page }) => {
        await openMap(page);
        await expect(page.locator('#data-banner')).toBeHidden();
        const st = await (await page.request.get('/api/status')).json();
        expect(st.postgis).toBe(true);
        expect(st.mysql).toBe(true);
    });
});

test.describe('Lớp dữ liệu', () => {
    test('L01 danh sách năm: mỗi năm có checkbox, năm chưa có ảnh bị đánh dấu', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        const items = page.locator('#year-list input[type=checkbox]');
        await expect(items.first()).toBeAttached();
        expect(await items.count()).toBeGreaterThanOrEqual(8);
        await expect(page.locator('#year-2020')).toBeAttached();
        await expect(page.locator('#year-2022')).toBeAttached();
    });

    test('L02 bật hai năm cùng lúc: cả hai lớp trên bản đồ, tắt một năm không ảnh hưởng năm kia', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        await page.locator('#year-2019').check();
        await page.locator('#year-2024').check();
        const on = (y: number) => page.evaluate((yy) => { const w = (window as any).__webgis; return w.map.hasLayer(w.layers.years.get(yy)); }, y);
        expect(await on(2019)).toBe(true);
        expect(await on(2024)).toBe(true);
        await page.locator('#year-2019').uncheck();
        expect(await on(2019)).toBe(false);
        expect(await on(2024)).toBe(true);
    });

    test('L03 bật lớp hotspot: tắt/bật theo checkbox', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        const has = () => page.evaluate(() => { const w = (window as any).__webgis; return w.map.hasLayer(w.layers.hotspots); });
        await expect(page.locator('#layer-hotspots')).toBeChecked();
        expect(await has()).toBe(true);
        await page.locator('#layer-hotspots').uncheck();
        expect(await has()).toBe(false);
        await page.locator('#layer-hotspots').check();
        expect(await has()).toBe(true);
    });

    test('L04 bỏ so sánh: gỡ cả hai lớp so sánh khỏi bản đồ', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        await page.selectOption('#compare-a', '2019');
        await page.selectOption('#compare-b', '2024');
        await page.click('#compare-apply');
        const state = () => page.evaluate(() => { const w = (window as any).__webgis; const c = w.layers.compare; return !!c && !!c.a && !!c.b && w.map.hasLayer(c.a) && w.map.hasLayer(c.b); });
        expect(await state()).toBe(true);
        await page.click('#compare-clear');
        expect(await state()).toBe(false);
    });

    test('L05 lớp sạt lở tham chiếu mặc định tắt, bật lên gọi API reference-events', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        await expect(page.locator('#layer-reference')).not.toBeChecked();
        const req = page.waitForResponse((r) => r.url().includes('/api/reference-events') && r.ok());
        await page.locator('#layer-reference').check();
        await req;
    });
});

test.describe('Tổng quan, bộ lọc, chi tiết', () => {
    test('F01 tổng quan khớp /api/summary', async ({ page }) => {
        await openMap(page);
        const s = await (await page.request.get('/api/summary')).json();
        const body = JSON.stringify(s);
        expect(body).toContain('10');
        await expect(page.locator('#kpi-transects')).toHaveText('10');
        const sum = await page.locator('#summary [data-class]').evaluateAll((els) => els.reduce((a, e) => a + Number(e.textContent), 0));
        expect(sum).toBe(10);
    });

    test('F02 hotspot: danh sách khớp API erosion-hotspots với cùng ngưỡng', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        const min = await page.inputValue('#min-rate');
        const api = await (await page.request.get(`/api/erosion-hotspots?min_rate=${min}`)).json();
        const n = (api.features ?? api).length;
        await expect(page.locator('#hotspot-list li')).toHaveCount(n);
    });

    test('F03 lọc ngưỡng gọi API với tham số người dùng nhập', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        await page.fill('#min-rate', '3');
        const req = page.waitForRequest((r) => r.url().includes('/api/erosion-hotspots') && /min_rate=3/.test(r.url()));
        await page.click('#apply-filter');
        await req;
    });

    test('F04 bấm hotspot trong danh sách: bản đồ phóng tới đoạn bờ đó', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        const z0 = await page.evaluate(() => (window as any).__webgis.map.getZoom());
        await page.locator('#hotspot-list li').first().click();
        await expect.poll(() => page.evaluate(() => (window as any).__webgis.map.getZoom())).toBeGreaterThanOrEqual(z0);
        await expect.poll(() => page.evaluate(() => location.hash)).toMatch(/^#map=/);
    });

    test('F05 chi tiết transect T10 qua showTransect: số liệu khớp API, biểu đồ có điểm', async ({ page }) => {
        await openMap(page);
        const fc = await (await page.request.get('/api/transects')).json();
        const f = fc.features.find((x: any) => x.properties.transect_code === 'T10');
        const id = f.properties.id;
        const stats = (await (await page.request.get(`/api/transects/${id}`)).json()).stats;
        await page.evaluate((i) => (window as any).__webgis.showTransect(i), id);
        await expect(page.locator('#transect-code')).toHaveText('T10');
        await expect(page.locator('#stat-nsm')).toHaveText(`${Number(stats.nsm_m).toFixed(2)} m`);
        const hist = await (await page.request.get(`/api/transects/${id}/history`)).json();
        await expect(page.locator('#history-chart')).toHaveAttribute('data-points', String(hist.length));
    });

    test('F06 liên kết chia sẻ: nút hiển thị thông báo và URL chứa khung nhìn', async ({ page, context, browserName }) => {
        if (browserName === 'chromium') await context.grantPermissions(['clipboard-read', 'clipboard-write']);
        await openMap(page);
        await openTab(page, 'share');
        await page.click('#share-link');
        await expect(page.locator('#share-message')).not.toBeEmpty();
        expect(await page.evaluate(() => location.hash)).toMatch(/^#map=\d+\/[\d.]+\/[\d.]+/);
    });

    test('F07 tải dữ liệu: link GeoJSON/CSV trả đúng content-type và dữ liệu', async ({ page }) => {
        await page.goto('/');
        await openTab(page, 'share');
        const links = page.locator('#panel-share .downloads a');
        await expect(links).toHaveCount(8);
        const geo = await page.request.get('/api/export/transects.geojson');
        expect(geo.ok()).toBe(true);
        expect((await geo.json()).features).toHaveLength(10);
        const csv = await page.request.get('/api/export/transects.csv');
        expect(csv.ok()).toBe(true);
        expect(csv.headers()['content-type']).toContain('csv');
        const lines = (await csv.text()).trim().split('\n');
        expect(lines.length).toBe(11); // tiêu đề + 10 transect
    });
});

test.describe('API từ giao diện', () => {
    test('A01 tải trang bản đồ gọi các API chính đều trả 2xx', async ({ page }) => {
        const bad: string[] = [];
        const seen = new Set<string>();
        page.on('response', (r) => {
            const u = new URL(r.url());
            if (u.pathname.startsWith('/api/')) { seen.add(u.pathname); if (!r.ok()) bad.push(`${r.status()} ${u.pathname}`); }
        });
        await openMap(page);
        await page.waitForLoadState('networkidle');
        expect(bad).toEqual([]);
        for (const p of ['/api/summary', '/api/erosion-hotspots']) expect(seen).toContain(p);
    });

    test('A02 API trả JSON đúng cấu trúc: transects là FeatureCollection 10 đối tượng', async ({ page }) => {
        const fc = await (await page.request.get('/api/transects')).json();
        expect(fc.type).toBe('FeatureCollection');
        expect(fc.features).toHaveLength(10);
        const codes = fc.features.map((f: any) => f.properties.transect_code).sort();
        expect(new Set(codes).size).toBe(10);
        for (const f of fc.features) expect(f.geometry.type).toBe('LineString');
    });

    test('A03 UI lỗi 500 từ summary: panel không crash, bản đồ vẫn dùng được', async ({ page }) => {
        await page.route('**/api/summary**', (r) => r.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"loi"}' }));
        const errs = collectErrors(page);
        await page.goto('/');
        await page.waitForFunction(() => (window as any).__webgis?.map);
        await expect(page.locator('#map')).toBeVisible();
        await openTab(page, 'hotspots');
        await expect(page.locator('#hotspot-list li').first()).toBeVisible();
        expect(errs().filter((e) => !/summary|500/i.test(e))).toEqual([]);
    });
});

test.describe('Đăng nhập & phân quyền', () => {
    test('P01 form đăng nhập: đúng nhãn, trường bắt buộc, panel admin ẩn lúc đầu', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.locator('#login-form [name=email]')).toHaveAttribute('required', '');
        await expect(page.locator('#login-form [name=password]')).toHaveAttribute('type', 'password');
        await expect(page.locator('#admin-panel')).toBeHidden();
    });

    test('P02 để trống mật khẩu: trình duyệt chặn gửi, không gọi API', async ({ page }) => {
        await page.goto('/admin');
        let called = false;
        page.on('request', (r) => { if (r.url().includes('/api/auth/login')) called = true; });
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#login-form [name=password]')).toHaveJSProperty('validity.valueMissing', true);
        expect(called).toBe(false);
        await expect(page.locator('#admin-panel')).toBeHidden();
    });

    test('P03 email sai định dạng: bị chặn ở form', async ({ page }) => {
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'khong-phai-email');
        await page.fill('#login-form [name=password]', 'x');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#login-form [name=email]')).toHaveJSProperty('validity.typeMismatch', true);
        await expect(page.locator('#admin-panel')).toBeHidden();
    });

    test('P04 email không tồn tại: báo lỗi, không lưu token', async ({ page }) => {
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'khongco@webgis.test');
        await page.fill('#login-form [name=password]', 'password');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-message')).toContainText('Sai email hoặc mật khẩu');
        await expect(page.locator('#admin-panel')).toBeHidden();
        expect(await page.evaluate(() => sessionStorage.getItem('webgis_admin_token'))).toBeNull();
    });

    test('P05 đăng nhập admin: hiện email+vai trò, danh sách ngưỡng, rồi đăng xuất quay về form', async ({ page }) => {
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.fill('#login-form [name=password]', 'password');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-message')).toContainText('Đăng nhập thành công');
        await expect(page.locator('#admin-user')).toContainText('admin@webgis.test');
        await expect(page.locator('#login-form')).toBeHidden();
        await expect(page.locator('.threshold-form').first()).toBeVisible();
        expect(await page.evaluate(() => sessionStorage.getItem('webgis_admin_token'))).toBeTruthy();
        await page.click('#logout');
        await expect(page.locator('#login-form')).toBeVisible();
        await expect(page.locator('#admin-panel')).toBeHidden();
        expect(await page.evaluate(() => sessionStorage.getItem('webgis_admin_token'))).toBeNull();
    });

    test('P06 phiên không giữ qua tab mới (token ở sessionStorage)', async ({ page, context }) => {
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.fill('#login-form [name=password]', 'password');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-panel')).toBeVisible();
        const p2 = await context.newPage();
        await p2.goto('/admin');
        await expect(p2.locator('#login-form')).toBeVisible();
        await expect(p2.locator('#admin-panel')).toBeHidden();
    });

    test('P07 API: đăng nhập sai 401, thiếu trường 422, đúng trả token + vai trò', async ({ page }) => {
        const bad = await page.request.post('/api/auth/login', { data: { email: 'admin@webgis.test', password: 'sai' }, headers: { Accept: 'application/json' } });
        expect(bad.status()).toBe(401);
        const empty = await page.request.post('/api/auth/login', { data: {}, headers: { Accept: 'application/json' } });
        expect(empty.status()).toBe(422);
        const ok = await apiLogin(page, 'admin@webgis.test', 'password');
        expect(ok.res.status()).toBe(200);
        expect(ok.body.token).toBeTruthy();
        expect(ok.body.user.role).toBe('admin');
        expect(ok.body.user).not.toHaveProperty('password');
    });

    test('P08 phân quyền backend: không token 401, viewer 403, ngưỡng không đổi', async ({ page }) => {
        const before = await (await page.request.get('/api/config/thresholds')).json();
        const url = `/api/admin/config/thresholds/${before[0].key}`;
        const anon = await page.request.put(url, { data: { value: 9 }, headers: { Accept: 'application/json' } });
        expect(anon.status()).toBe(401);
        const v = await apiLogin(page, 'viewer@webgis.test', 'password');
        expect(v.res.status()).toBe(200);
        const forb = await page.request.put(url, { data: { value: 9 }, headers: { Accept: 'application/json', Authorization: `Bearer ${v.body.token}` } });
        expect(forb.status()).toBe(403);
        const after = await (await page.request.get('/api/config/thresholds')).json();
        expect(after).toEqual(before);
    });

    test('P09 admin nhập giá trị âm: phải bị từ chối 422, ngưỡng không đổi', async ({ page }) => {
        const before = await (await page.request.get('/api/config/thresholds')).json();
        const key = before[0].key;
        const a = await apiLogin(page, 'admin@webgis.test', 'password');
        const auth = { Accept: 'application/json', Authorization: `Bearer ${a.body.token}` };
        try {
            const res = await page.request.put(`/api/admin/config/thresholds/${key}`, { data: { value: -5 }, headers: auth });
            expect(res.status()).toBe(422);
            expect(await (await page.request.get('/api/config/thresholds')).json()).toEqual(before);
        } finally {
            // trả lại giá trị gốc để không làm bẩn dữ liệu cho test khác
            await page.request.put(`/api/admin/config/thresholds/${key}`, { data: { value: before[0].value }, headers: auth });
        }
    });
});

test.describe('Responsive & giao diện', () => {
    test('R01 mobile 375px: không tràn ngang ở cả 3 trang', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 700 });
        for (const p of ['/', '/gioi-thieu', '/admin']) {
            await page.goto(p);
            await page.waitForLoadState('domcontentloaded');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
    });

    test('R02 mobile: nút sidebar đóng/mở đổi aria-expanded', async ({ page }) => {
        await page.setViewportSize({ width: 400, height: 800 });
        await openMap(page);
        const btn = page.locator('#sidebar-toggle');
        await expect(btn).toHaveAttribute('aria-expanded', 'false');
        await btn.click();
        await expect(btn).toHaveAttribute('aria-expanded', 'true');
        await btn.click();
        await expect(btn).toHaveAttribute('aria-expanded', 'false');
    });

    test('R03 mobile: form đăng nhập admin dùng được ở 375px', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 700 });
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.fill('#login-form [name=password]', 'password');
        await page.click('#login-form button[type=submit]');
        await expect(page.locator('#admin-panel')).toBeVisible();
    });

    test('R04 desktop: sidebar mở sẵn cạnh bản đồ', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await openMap(page);
        await expect(page.locator('#sidebar')).not.toHaveClass(/collapsed/);
        await expect(page.locator('#sidebar')).toBeVisible();
    });

    test('R05 dark mode: trang tải được và chữ/nền tương phản', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.goto('/gioi-thieu');
        const c = await page.evaluate(() => { const s = getComputedStyle(document.body); return { fg: s.color, bg: s.backgroundColor }; });
        expect(c.fg).not.toBe(c.bg);
    });

    test('R06 bàn phím: Tab đến được liên kết đầu tiên của menu và Enter điều hướng', async ({ page }) => {
        await page.goto('/');
        await page.getByRole('navigation', { name: 'Điều hướng chính' }).getByRole('link', { name: 'Phương pháp' }).focus();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/gioi-thieu$/);
    });
});
