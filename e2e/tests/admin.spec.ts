import { test, expect } from '@playwright/test';
import { ADMIN, VIEWER, THRESHOLD_KEY, loginViaForm, putThreshold, readThreshold } from './helpers-journey';
import { openMap, openTab } from './helpers';

// Mỗi test sửa ngưỡng đều khôi phục về giá trị gốc đã đọc trước đó, kể cả khi test fail.
test.describe('Quản trị ngưỡng', () => {
    let original: string;
    test.beforeEach(async ({ request }) => {
        original = await readThreshold(request);
    });
    test.afterEach(async ({ request }) => {
        const res = await putThreshold(request, original);
        expect(res.status()).toBe(200);
    });

    test('AD01 form hiển thị đúng giá trị ngưỡng hiện tại từ API', async ({ page, request }) => {
        await loginViaForm(page);
        await expect(page.locator(`.threshold-form[data-key=${THRESHOLD_KEY}] input[name=value]`)).toHaveValue(original);
        await expect(page.locator('#admin-user')).toHaveText(`${ADMIN.email} (admin)`);
    });

    test('AD02 giá trị hợp lệ: lưu, API và bản đồ phản ánh, reload vẫn giữ', async ({ page, request }) => {
        await loginViaForm(page);
        const input = page.locator(`.threshold-form[data-key=${THRESHOLD_KEY}] input[name=value]`);
        await input.fill('3.5');
        await page.click(`.threshold-form[data-key=${THRESHOLD_KEY}] button`);
        await expect(page.locator('#admin-message')).toHaveText(`Đã cập nhật ${THRESHOLD_KEY} = 3.5.`);
        expect(Number(await readThreshold(request))).toBe(3.5);
        await page.reload(); // token ở sessionStorage nhưng form hiện lại -> đăng nhập lại
        await loginViaForm(page);
        await expect(page.locator(`.threshold-form[data-key=${THRESHOLD_KEY}] input[name=value]`)).toHaveValue('3.5');
    });

    test('AD03 biên 0: chấp nhận ngưỡng 0, hotspot hiện đủ mọi đoạn lùi bờ', async ({ page, request }) => {
        const res = await putThreshold(request, 0);
        expect(res.status()).toBe(200);
        await openMap(page);
        await openTab(page, 'hotspots');
        await expect(page.locator('#min-rate')).toHaveValue('0');
        const api = await (await request.get('/api/erosion-hotspots?min_rate=0')).json();
        await expect(page.locator('#hotspot-list li')).toHaveCount(api.features.length);
    });

    test('AD04 số âm trên form: trình duyệt chặn (min=0), API không bị gọi, ngưỡng không đổi', async ({ page, request }) => {
        await loginViaForm(page);
        let put = false;
        page.on('request', (r) => { if (r.method() === 'PUT') put = true; });
        const input = page.locator(`.threshold-form[data-key=${THRESHOLD_KEY}] input[name=value]`);
        await input.fill('-2');
        await page.click(`.threshold-form[data-key=${THRESHOLD_KEY}] button`);
        await expect(input).toHaveJSProperty('validity.rangeUnderflow', true);
        expect(put).toBe(false);
        expect(await readThreshold(request)).toBe(original);
    });

    test('AD05 số âm gửi thẳng API: 422 và ngưỡng không đổi', async ({ request }) => {
        const res = await putThreshold(request, -1);
        expect(res.status()).toBe(422);
        expect(await readThreshold(request)).toBe(original);
    });

    test('AD06 không phải số ("abc", rỗng): API 422, ngưỡng không đổi', async ({ request }) => {
        for (const bad of ['abc', '', '1,5']) {
            const res = await putThreshold(request, bad);
            expect(res.status(), `giá trị "${bad}"`).toBe(422);
        }
        expect(await readThreshold(request)).toBe(original);
    });

    test('AD07 ô số trên form không nhận chữ', async ({ page }) => {
        await loginViaForm(page);
        const input = page.locator(`.threshold-form[data-key=${THRESHOLD_KEY}] input[name=value]`);
        await input.fill('');
        await input.pressSequentially('abc');
        await expect(input).toHaveValue('');
        await expect(input).toHaveAttribute('type', 'number');
    });

    test('AD08 viewer: panel hiện nhưng PUT bị 403, ngưỡng không đổi', async ({ page, request }) => {
        const res = await putThreshold(request, 7, VIEWER);
        expect(res.status()).toBe(403);
        expect(await readThreshold(request)).toBe(original);
    });

    test('AD09 ngưỡng mới ảnh hưởng số hotspot trên UI và khớp API', async ({ page, request }) => {
        await putThreshold(request, 3);
        await openMap(page);
        await openTab(page, 'hotspots');
        const api = await (await request.get('/api/erosion-hotspots?min_rate=3')).json();
        await expect(page.locator('#min-rate')).toHaveValue('3');
        await expect(page.locator('#hotspot-list li')).toHaveCount(api.features.length);
        for (const li of await page.locator('#hotspot-list li').all()) {
            expect(Number(await li.getAttribute('data-severity'))).toBeGreaterThanOrEqual(3);
        }
        await expect(page.locator('#hotspot-count')).toContainText(`${api.features.length} đoạn từ 3 m/năm`);
    });

    test('AD10 ngưỡng cao hơn mọi tốc độ: danh sách rỗng, không lỗi console', async ({ page, request }) => {
        await putThreshold(request, 50);
        const errors = await openMap(page);
        await openTab(page, 'hotspots');
        await expect(page.locator('#hotspot-list li')).toHaveCount(0);
        await expect(page.locator('#hotspot-count')).toContainText('0 đoạn từ 50 m/năm');
        expect(errors.filter((e) => !/arcgisonline|ERR_|Failed to load resource/i.test(e))).toEqual([]);
    });

    test('AD11 đăng xuất xoá token, tải lại /admin hiện lại form đăng nhập', async ({ page }) => {
        await loginViaForm(page);
        await page.click('#logout');
        await expect(page.locator('#login-form')).toBeVisible();
        expect(await page.evaluate(() => sessionStorage.getItem('webgis_admin_token'))).toBeNull();
    });

    test('AD12 token đã đăng xuất / giả không sửa được ngưỡng (401)', async ({ request }) => {
        const res = await request.put(`/api/admin/config/thresholds/${THRESHOLD_KEY}`, {
            data: { value: 9 },
            headers: { Authorization: 'Bearer token-gia-123', Accept: 'application/json' },
        });
        expect(res.status()).toBe(401);
        expect(await readThreshold(request)).toBe(original);
    });

    test('AD13 khoá ngưỡng không tồn tại: 404', async ({ request }) => {
        const res = await putThreshold(request, 1, ADMIN, 'khong_ton_tai');
        expect(res.status()).toBe(404);
    });

    test('AD14 API lỗi 500 khi tải ngưỡng: panel báo "đang cập nhật", không crash', async ({ page }) => {
        await page.route('**/api/config/thresholds', (r) => r.fulfill({ status: 500, json: { message: 'x' } }));
        await loginViaForm(page);
        await expect(page.locator('#thresholds')).toContainText('Cấu hình ngưỡng đang cập nhật');
    });

    test('AD15 lỗi 500 khi lưu: hiện thông báo lỗi, ngưỡng thật không đổi', async ({ page, request }) => {
        await loginViaForm(page);
        await page.route('**/api/admin/config/thresholds/*', (r) => r.fulfill({ status: 500, json: { message: 'Lỗi máy chủ.' } }));
        await page.locator(`.threshold-form[data-key=${THRESHOLD_KEY}] input[name=value]`).fill('8');
        await page.click(`.threshold-form[data-key=${THRESHOLD_KEY}] button`);
        await expect(page.locator('#admin-message')).toHaveText('Lỗi máy chủ.');
        expect(await readThreshold(request)).toBe(original);
    });
});
