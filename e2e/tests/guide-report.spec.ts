import { test, expect } from '@playwright/test';
import { ADMIN, VIEWER } from './helpers-journey';

async function sessionLogin(page: import('@playwright/test').Page, user = ADMIN) {
    await page.goto('/dang-nhap');
    await page.fill('#session-login-form [name=email]', user.email);
    await page.fill('#session-login-form [name=password]', user.password);
    await page.click('#session-login-form button[type=submit]');
}

test.describe('Hướng dẫn và Báo cáo', () => {
    test('GR01 khách xem được Hướng dẫn, menu không có Báo cáo', { tag: ['@smoke', '@happy', '@regression'] }, async ({ page }) => {
        await page.goto('/huong-dan');
        await expect(page.locator('h1')).toHaveText('Hướng dẫn sử dụng');
        await expect(page.locator('.topnav a[href="/huong-dan"]')).toHaveAttribute('aria-current', 'page');
        await expect(page.locator('.topnav a[href="/bao-cao"]')).toHaveCount(0);
    });

    test('GR02 khách vào /bao-cao bị chuyển tới đăng nhập, đăng nhập admin thì quay lại báo cáo', { tag: ['@happy', '@regression'] }, async ({ page }) => {
        await page.goto('/bao-cao');
        await expect(page).toHaveURL(/\/dang-nhap$/);
        await sessionLogin(page);
        await expect(page).toHaveURL(/\/bao-cao$/);
        await expect(page.locator('h1')).toHaveText('Báo cáo công việc đã làm');
        await expect(page.locator('.topnav a[href="/bao-cao"]')).toHaveAttribute('aria-current', 'page');
        await expect(page.locator('#tien-do tbody tr')).not.toHaveCount(0);
    });

    test('GR03 user thường: không có menu Báo cáo, vào thẳng /bao-cao bị 403', { tag: ['@negative', '@regression'] }, async ({ page }) => {
        await sessionLogin(page, VIEWER);
        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('.topnav a[href="/bao-cao"]')).toHaveCount(0);
        const res = await page.goto('/bao-cao');
        expect(res?.status()).toBe(403);
        await expect(page.locator('h1')).toHaveText('Không có quyền truy cập');
        await expect(page.getByText('Tiến độ theo đề cương')).toHaveCount(0);
    });

    test('GR04 đăng xuất: menu Báo cáo biến mất và /bao-cao lại bị chặn', { tag: ['@happy', '@regression'] }, async ({ page }) => {
        await sessionLogin(page);
        await page.goto('/huong-dan');
        await expect(page.locator('.topnav a[href="/bao-cao"]')).toBeVisible();
        await page.click('.nav-logout button');
        await expect(page).toHaveURL(/\/$/);
        await expect(page.locator('.topnav a[href="/bao-cao"]')).toHaveCount(0);
        await page.goto('/bao-cao');
        await expect(page).toHaveURL(/\/dang-nhap$/);
    });

    test('GR05 sai mật khẩu hiện thông báo lỗi, không vào được báo cáo', { tag: ['@negative', '@regression'] }, async ({ page }) => {
        await sessionLogin(page, { ...ADMIN, password: 'sai-mat-khau' });
        await expect(page.locator('#session-login-form .field-error')).toHaveText('Sai email hoặc mật khẩu.');
        await page.goto('/bao-cao');
        await expect(page).toHaveURL(/\/dang-nhap$/);
    });

    test('GR06 điện thoại: menu cuộn ngang trong thanh tiêu đề, trang không tràn ngang', { tag: ['@regression', '@responsive'] }, async ({ page }) => {
        await page.setViewportSize({ width: 360, height: 740 });
        await sessionLogin(page);
        await page.goto('/bao-cao');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(overflow).toBe(false);
    });

    test('GR07 chỉ còn giao diện sáng: hệ điều hành chọn tối vẫn giữ nền sáng', { tag: ['@regression', '@responsive'] }, async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await sessionLogin(page);
        const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        expect(bg).toBe('rgb(239, 233, 218)');
    });
});
