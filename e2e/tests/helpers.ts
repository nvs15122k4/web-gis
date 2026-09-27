import { expect, Page } from '@playwright/test';

/** Mở trang bản đồ và chờ Leaflet + biến kiểm thử sẵn sàng. */
export async function openMap(page: Page) {
    const errors: string[] = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto('/');
    await page.waitForFunction(() => (window as any).__webgis?.map);
    await expect(page.locator('#map .leaflet-tile-loaded').first()).toBeVisible();
    return errors;
}

/** Mở một tab trong sidebar (overview | layers | hotspots | share). */
export async function openTab(page: Page, name: string) {
    await page.click(`[role=tab][data-tab="${name}"]`);
}

export const API = {
    transect: async (page: Page, code: string) => {
        const fc = await (await page.request.get('/api/transects')).json();
        const f = fc.features.find((x: any) => x.properties.transect_code === code);
        return { id: f.properties.id as number, geometry: f.geometry };
    },
};

/** Đưa bản đồ về vùng có transect và trả toạ độ (lat,lng) giữa transect để click. */
export async function centerOnTransect(page: Page, code: string) {
    const t = await API.transect(page, code);
    const [[lng1, lat1], [lng2]] = t.geometry.coordinates;
    const lng = lng1 + (lng2 - lng1) * 0.5;
    await page.evaluate(([lat, lng]) => (window as any).__webgis.map.setView([lat, lng], 16, { animate: false }), [lat1, lng]);
    // chờ WMS tile của lớp transect vẽ xong
    await page.waitForFunction(() => document.querySelectorAll('.leaflet-tile-pane img[src*="transects"].leaflet-tile-loaded').length > 0);
    return { id: t.id, lat: lat1, lng };
}

/** Click vào đúng vị trí địa lý (lat,lng) trên bản đồ. */
export async function clickAt(page: Page, lat: number, lng: number) {
    const pt = await page.evaluate(([lat, lng]) => {
        const m = (window as any).__webgis.map;
        const p = m.latLngToContainerPoint([lat, lng]);
        const r = m.getContainer().getBoundingClientRect();
        return { x: r.left + p.x, y: r.top + p.y };
    }, [lat, lng]);
    await page.mouse.click(pt.x, pt.y);
}
