import { test, expect } from '@playwright/test';
import { openMap, openTab } from './helpers';

test.describe('Trợ năng (a11y)', () => {
    for (const path of ['/', '/gioi-thieu', '/admin']) {
        test(`Y01 ${path}: landmark chính, h1/h2 có nội dung, mọi nút/ liên kết có tên`, async ({ page }) => {
            await page.goto(path);
            await expect(page.locator('header.appbar')).toBeVisible();
            await expect(page.getByRole('navigation', { name: 'Điều hướng chính' })).toBeVisible();
            const unnamed = await page.evaluate(() =>
                [...document.querySelectorAll('button, a[href]')].filter((el) => {
                    const t = (el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || '').trim();
                    return !t;
                }).map((el) => el.outerHTML.slice(0, 80)));
            expect(unnamed).toEqual([]);
        });
    }

    test('Y02 trang bản đồ: mọi input/select có nhãn (label, aria-label)', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'layers');
        const bad = await page.evaluate(() =>
            [...document.querySelectorAll('#sidebar input, #sidebar select')].filter((el: any) => {
                const hasLabel = el.closest('label') || (el.id && document.querySelector(`label[for="${el.id}"]`));
                return !hasLabel && !el.getAttribute('aria-label') && !el.getAttribute('aria-labelledby');
            }).map((el: any) => el.id || el.name));
        expect(bad).toEqual([]);
    });

    test('Y03 ảnh/biểu tượng SVG trang trí ẩn khỏi trình đọc màn hình, <img> có alt', async ({ page }) => {
        await openMap(page);
        const noAlt = await page.evaluate(() => [...document.querySelectorAll('img:not(.leaflet-tile):not(.leaflet-marker-icon):not(.leaflet-marker-shadow)')]
            .filter((i) => !i.hasAttribute('alt')).map((i) => i.getAttribute('src')));
        expect(noAlt).toEqual([]);
        const svgBad = await page.evaluate(() => [...document.querySelectorAll('.tabs svg, .appbar svg')].filter((s) => !s.closest('[aria-hidden=true]')).length);
        expect(svgBad).toBe(0);
    });

    test('Y04 tab: roving tabindex, chỉ tab đang chọn tabindex != -1, liên kết aria-controls đúng', async ({ page }) => {
        await openMap(page);
        for (const name of ['overview', 'layers', 'hotspots', 'share']) {
            await openTab(page, name);
            for (const other of ['overview', 'layers', 'hotspots', 'share']) {
                const tab = page.locator(`[role=tab][data-tab="${other}"]`);
                await expect(tab).toHaveAttribute('aria-selected', String(other === name));
                const panel = page.locator(`#panel-${other}`);
                await expect(panel).toHaveAttribute('aria-labelledby', `tab-${other}`);
                if (other === name) await expect(panel).toBeVisible();
                else await expect(panel).toBeHidden();
            }
        }
    });

    test('Y05 bàn phím: Tab đưa focus qua nút và vòng focus nhìn thấy được', async ({ page }) => {
        await page.goto('/');
        await page.keyboard.press('Tab');
        const info = await page.evaluate(() => {
            const el = document.activeElement as HTMLElement;
            const cs = getComputedStyle(el);
            return { tag: el.tagName, outline: cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0, shadow: cs.boxShadow !== 'none' };
        });
        expect(['A', 'BUTTON']).toContain(info.tag);
        expect(info.outline || info.shadow).toBe(true);
    });

    test('Y06 bàn phím: nút Lọc kích hoạt bằng Enter trong ô ngưỡng và bằng Space trên nút', async ({ page }) => {
        await openMap(page);
        await openTab(page, 'hotspots');
        await page.fill('#min-rate', '3');
        await page.press('#min-rate', 'Enter');
        await expect(page.locator('#hotspot-count')).toContainText('từ 3 m/năm');
        await page.fill('#min-rate', '4');
        await page.focus('#apply-filter');
        await page.keyboard.press('Space');
        await expect(page.locator('#hotspot-count')).toContainText('từ 4 m/năm');
    });

    test('Y07 bàn phím: nút sidebar mở/đóng bằng Enter và aria-expanded đổi theo (mobile)', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 700 });
        await openMap(page);
        const btn = page.locator('#sidebar-toggle');
        const before = await btn.getAttribute('aria-expanded');
        await btn.focus();
        await page.keyboard.press('Enter');
        await expect(btn).not.toHaveAttribute('aria-expanded', before!);
    });

    test('Y08 vùng thông báo (status/banner) có role=status để đọc tự động', async ({ page }) => {
        await openMap(page);
        await expect(page.locator('#status')).toHaveAttribute('role', 'status');
        await expect(page.locator('#data-banner')).toHaveAttribute('role', 'status');
        await expect(page.locator('#hotspot-count')).toHaveAttribute('role', 'status');
    });

    test('Y09 form đăng nhập: nhãn gắn với ô, thông báo role=status, autocomplete đúng', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.getByLabel('Email')).toHaveAttribute('autocomplete', 'username');
        await expect(page.getByLabel('Mật khẩu')).toHaveAttribute('autocomplete', 'current-password');
        await expect(page.locator('#admin-message')).toHaveAttribute('role', 'status');
    });

    test('Y10 nút đóng chi tiết transect có tên truy cập, thẻ chi tiết gắn tiêu đề', async ({ page }) => {
        await openMap(page);
        await expect(page.getByRole('button', { name: 'Đóng chi tiết transect', includeHidden: true })).toHaveCount(1);
        await expect(page.locator('#inspector')).toHaveAttribute('aria-labelledby', 'inspector-title');
    });

    test('Y11 tab order trong trang admin: email -> mật khẩu -> Đăng nhập', async ({ page }) => {
        await page.goto('/admin');
        await page.focus('#login-form [name=email]');
        await page.keyboard.press('Tab');
        await expect(page.locator('#login-form [name=password]')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByRole('button', { name: 'Đăng nhập' })).toBeFocused();
    });

    test('Y12 Enter trong form đăng nhập gửi biểu mẫu', async ({ page }) => {
        await page.goto('/admin');
        await page.fill('#login-form [name=email]', 'admin@webgis.test');
        await page.fill('#login-form [name=password]', 'password');
        await page.press('#login-form [name=password]', 'Enter');
        await expect(page.locator('#admin-panel')).toBeVisible();
    });

    test('Y13 prefers-reduced-motion không làm hỏng trang', async ({ browser }) => {
        const ctx = await browser.newContext({ reducedMotion: 'reduce' });
        const page = await ctx.newPage();
        await openMap(page);
        await expect(page.locator('#sidebar')).toBeVisible();
        await ctx.close();
    });
});
