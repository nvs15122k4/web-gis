import { test, expect, Page } from '@playwright/test';
import { openAbout } from './helpers';

test.describe('Trang giới thiệu & phương pháp (đề cương 2.5, độ chính xác, dịch vụ OGC)', () => {
    test('M01 hàng "Lùi mạnh" nêu ngưỡng LRR ≤ −2 m/năm', { tag: ['@happy', '@regression'] }, async ({ page }) => {
        await openAbout(page);
        const strong = page.getByRole('row').filter({ has: page.getByRole('cell', { name: 'Lùi mạnh', exact: true }) });
        await expect(strong).toHaveCount(1);
        await expect(strong).toContainText('LRR');
        await expect(strong).toContainText(/[−-]2 m\/năm/);
    });

    test('M02 đoạn "Về độ chính xác vị trí" nêu 3,3 km và không phải vị trí sạt lở chính xác', { tag: ['@happy', '@regression'] }, async ({ page }) => {
        await openAbout(page);
        const p = page.locator('#sat-lo p', { has: page.getByText('Về độ chính xác vị trí:') });
        await expect(p).toHaveCount(1);
        await expect(p).toContainText('3,3 km');
        await expect(p).toContainText('không phải vị trí sạt lở chính xác');
    });

    test.describe('liên kết dịch vụ OGC', () => {
        const links = (page: Page) => page.locator('#ogc-services a.pill');

        test('M03 WMS/WFS GetCapabilities trỏ đúng workspace và trả XML hợp lệ', { tag: ['@happy', '@regression'] }, async ({ page }) => {
            await openAbout(page);
            for (const [name, root] of [['WMS', 'WMS_Capabilities'], ['WFS', 'WFS_Capabilities']] as const) {
                const href = (await page.locator('#ogc-services').getByRole('link', { name, exact: true }).getAttribute('href'))!;
                expect(href).toContain('/songhau_e2e/');
                expect(href).toMatch(new RegExp(`service=${name}`));
                const res = await page.request.get(href);
                expect(res.ok(), `${name} ${res.status()}`).toBeTruthy();
                expect(res.headers()['content-type']).toMatch(/xml/i);
                expect(await res.text()).toContain(root);
            }
        });

        test('M04 hai liên kết GetFeature GeoJSON trả features là mảng', { tag: ['@happy', '@regression'] }, async ({ page }) => {
            await openAbout(page);
            const hrefs = await links(page).evaluateAll((as) =>
                as.map((a) => (a as HTMLAnchorElement).getAttribute('href')!).filter((h) => /outputFormat=application%2Fjson/i.test(h)));
            expect(hrefs).toHaveLength(2);
            for (const href of hrefs) {
                expect(href).toContain('songhau_e2e:');
                const res = await page.request.get(href);
                expect(res.ok(), href).toBeTruthy();
                const body = await res.json();
                expect(Array.isArray(body.features)).toBe(true);
            }
            // Bộ lọc severe_erosion chỉ chứa transect lùi mạnh (fixture có 3).
            const severe = await (await page.request.get(hrefs.find((h) => h.includes('transects'))!)).json();
            expect(severe.features).toHaveLength(3);
            expect(severe.features.every((f: any) => f.properties.change_class === 'severe_erosion')).toBe(true);
        });
    });
});
