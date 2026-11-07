import { APIRequestContext, Page, expect } from '@playwright/test';

export const ADMIN = { email: 'admin@webgis.test', password: 'password' };
export const VIEWER = { email: 'viewer@webgis.test', password: 'password' };
export const THRESHOLD_KEY = 'min_erosion_rate';

export async function tokenOf(request: APIRequestContext, user = ADMIN) {
    const res = await request.post('/api/auth/login', { data: user });
    expect(res.status()).toBe(200);
    return (await res.json()).token as string;
}

export async function readThreshold(request: APIRequestContext, key = THRESHOLD_KEY) {
    const list = await (await request.get('/api/config/thresholds')).json();
    return String(list.find((c: any) => c.key === key).value);
}

export async function putThreshold(request: APIRequestContext, value: string | number, user = ADMIN, key = THRESHOLD_KEY) {
    const token = await tokenOf(request, user);
    return request.put(`/api/admin/config/thresholds/${key}`, {
        data: { value },
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    });
}

/** Đăng nhập qua form /admin. */
export async function loginViaForm(page: Page, user = ADMIN) {
    await page.goto('/admin');
    await page.fill('#login-form [name=email]', user.email);
    await page.fill('#login-form [name=password]', user.password);
    await page.click('#login-form button[type=submit]');
    await expect(page.locator('#admin-panel')).toBeVisible();
}

/** Mở bản đồ mà không chờ tile nền (dùng khi mạng/API bị giả lập lỗi). */
export async function openMapLoose(page: Page) {
    await page.goto('/');
    await page.waitForFunction(() => (window as any).__webgis?.map);
}

/** Lỗi console không phải nhiễu từ mạng ngoài (tile vệ tinh). */
export const appErrors = (errs: string[]) => errs.filter((e) => !/arcgisonline|ERR_|Failed to load resource/i.test(e));
