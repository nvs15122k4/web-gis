import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import L from 'leaflet';

const FC = (features) => ({ type: 'FeatureCollection', features });
const line = (id, props) => ({
    type: 'Feature', id, properties: { transect_code: `T${id}`, ...props },
    geometry: { type: 'LineString', coordinates: [[105.2, 10.5], [105.21, 10.51]] },
});

const setup = async (geoserver) => {
    document.body.innerHTML = `<div id="map"></div><div id="status"></div>
        <input type="checkbox" id="layer-transects"><input type="checkbox" id="layer-rates" checked><input type="checkbox" id="only-significant">
        <input type="checkbox" id="layer-hotspots"><input type="checkbox" id="layer-reference"><span id="reference-status"></span>
        <div id="year-list"></div><select id="compare-a"></select><select id="compare-b"></select>
        <button id="compare-clear"></button><button id="compare-apply"></button>
        <input id="min-rate"><button id="apply-filter"></button><span id="hotspot-count"></span><ul id="hotspot-list"></ul>`;
    window.WEBGIS = { geoserver, years: { all: [2018, 2019], available: [2018, 2019] } };
    // jsdom không có canvas 2D: dùng renderer SVG thay thế khi kiểm thử.
    vi.spyOn(L, 'canvas').mockImplementation((o) => L.svg(o));
    vi.resetModules();
    const mod = await import('../../resources/js/layers');
    const map = L.map('map', { center: [10.5, 105.2], zoom: 13 });
    return { mod, map };
};

describe('layers: chế độ GeoJSON (không có GeoServer)', () => {
    beforeEach(() => {
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);
            const body = u.includes('/api/transects') ? FC([line(1, { change_class: 'severe_erosion', is_significant: true }), line(2, { change_class: 'stable', is_significant: false })])
                : u.includes('/api/shorelines') ? FC([line(9, { year: 2018 })])
                    : u.includes('/config/thresholds') ? [] : FC([]);
            return { ok: true, status: 200, json: async () => body };
        });
    });
    afterEach(() => { delete globalThis.fetch; });

    it('lớp transect tắt mặc định thì chưa gọi /api/transects', async () => {
        const { mod, map } = await setup({ url: null, workspace: 'songhau' });
        mod.initLayers(map);
        await new Promise((r) => setTimeout(r, 20));
        expect(globalThis.fetch.mock.calls.some(([u]) => String(u).includes('/api/transects'))).toBe(false);
    });

    it('không gọi WMS: lớp transect là GeoJSON vẽ từ /api/transects', async () => {
        const { mod, map } = await setup({ url: null, workspace: 'songhau' });
        expect(mod.USE_WMS).toBe(false);
        mod.initLayers(map);
        mod.layers.transects.addTo(map);
        mod.layers.transects.addTo(map); // lớp tắt mặc định: chỉ tải dữ liệu khi được bật
        await vi.waitFor(() => expect(mod.layers.transects.getLayers()).toHaveLength(2));
        expect(globalThis.fetch.mock.calls.some(([u]) => String(u).includes('/wms'))).toBe(false);
    });

    it('lọc "chỉ ý nghĩa thống kê" giữ lại transect có is_significant', async () => {
        const { mod, map } = await setup({ url: null, workspace: 'songhau' });
        mod.initLayers(map);
        mod.layers.transects.addTo(map); // lớp tắt mặc định: chỉ tải dữ liệu khi được bật
        await vi.waitFor(() => expect(mod.layers.transects.getLayers()).toHaveLength(2));
        const box = document.getElementById('only-significant');
        box.checked = true;
        box.dispatchEvent(new Event('change'));
        expect(mod.layers.transects.getLayers()).toHaveLength(1);
        box.checked = false;
        box.dispatchEvent(new Event('change'));
        expect(mod.layers.transects.getLayers()).toHaveLength(2);
    });

    it('bấm một transect gọi handler với feature và vị trí', async () => {
        const { mod, map } = await setup({ url: null, workspace: 'songhau' });
        mod.initLayers(map);
        mod.layers.transects.addTo(map); // lớp tắt mặc định: chỉ tải dữ liệu khi được bật
        await vi.waitFor(() => expect(mod.layers.transects.getLayers()).toHaveLength(2));
        mod.handlers.onTransectClick = vi.fn();
        mod.layers.transects.getLayers()[0].fire('click', { latlng: L.latLng(10.5, 105.2) });
        expect(mod.handlers.onTransectClick).toHaveBeenCalledOnce();
        expect(mod.handlers.onTransectClick.mock.calls[0][0].id).toBe(1);
    });

    it('bật đường bờ nhiều năm lúc khởi động chỉ tốn MỘT yêu cầu /api/shorelines (gộp year[])', async () => {
        const { mod, map } = await setup({ url: null, workspace: 'songhau' });
        mod.initLayers(map);
        const box = document.getElementById('year-2018');
        box.checked = true;
        box.dispatchEvent(new Event('change'));
        await vi.waitFor(() => expect(mod.layers.years.get(2018).getLayers()).toHaveLength(1));
        const calls = globalThis.fetch.mock.calls.map(([u]) => String(u)).filter((u) => u.includes('/api/shorelines'));
        expect(calls).toHaveLength(1);
        expect(decodeURIComponent(calls[0])).toBe('/api/shorelines?year[]=2018&year[]=2019');
        // mỗi năm chỉ nhận đúng đối tượng của năm đó
        expect(mod.layers.years.get(2018).getLayers()).toHaveLength(1);
        await vi.waitFor(() => expect(mod.layers.years.get(2019)).toBeDefined());
        expect(mod.layers.years.get(2019).getLayers()).toHaveLength(0);
    });
});

describe('layers: chế độ GeoServer (tương thích ngược)', () => {
    it('có GEOSERVER_URL thì dùng WMS', async () => {
        globalThis.fetch = vi.fn(async () => ({ ok: true, status: 200, json: async () => [] }));
        const { mod, map } = await setup({ url: 'http://gs.test/geoserver', workspace: 'songhau' });
        expect(mod.USE_WMS).toBe(true);
        mod.initLayers(map);
        expect(mod.layers.transects.wmsParams.layers).toBe('songhau:transects');
        delete globalThis.fetch;
    });
});
