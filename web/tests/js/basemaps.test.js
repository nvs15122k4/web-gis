import { beforeEach, describe, expect, it, vi } from 'vitest';
import L from 'leaflet';

const GEO = { url: 'http://gs.test/geoserver', workspace: 'songhau' };

const makeMap = () => {
    const el = document.createElement('div');
    document.body.appendChild(el);
    const map = L.map(el, { center: [10.5, 105.2], zoom: 12 });
    map.addedLayers = [];
    const add = L.Layer.prototype.addTo;
    // Ghi lại lớp nền được thêm, không để Leaflet tải tile thật.
    vi.spyOn(L.TileLayer.prototype, 'addTo').mockImplementation(function (m) { m.addedLayers.push(this); return this; });
    return { map, restore: () => { L.Layer.prototype.addTo = add; } };
};

const load = async () => {
    vi.resetModules();
    return import('../../resources/js/basemaps');
};

describe('initBasemaps', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        localStorage.clear();
        window.WEBGIS = { geoserver: GEO };
    });

    it('tạo pane nền dưới các lớp chuyên đề', async () => {
        const { initBasemaps, BASEMAP_PANE } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        expect(map.getPane(BASEMAP_PANE).style.zIndex).toBe('150');
    });

    it('mặc định dùng nền GeoServer qua WMTS của workspace cấu hình', async () => {
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        const layer = map.addedLayers[0];
        expect(layer._url).toContain('http://gs.test/geoserver/gwc/service/wmts');
        expect(layer._url).toContain('layer=songhau:nen_ban_do');
    });

    it('không đặt GeoServer thì mặc định dùng nền vector Việt Nam (có Hoàng Sa, Trường Sa), không có nen_ban_do', async () => {
        window.WEBGIS = { geoserver: { url: null, workspace: 'songhau' } };
        globalThis.fetch = vi.fn(() => Promise.resolve({ json: () => Promise.resolve({ type: 'FeatureCollection', features: [] }) }));
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        expect(globalThis.fetch).toHaveBeenCalledWith('/data/basemap_vn.geojson');
        expect(map.addedLayers.some((l) => l._url?.includes('nen_ban_do'))).toBe(false);
    });

    it('luôn có lớp nhãn Hoàng Sa, Trường Sa trên mọi nền', async () => {
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        expect(document.body.innerHTML).toContain('Quần đảo Hoàng Sa');
        expect(document.body.innerHTML).toContain('Quần đảo Trường Sa');
    });

    it('không đặt GeoServer nhưng có khoá VIETMAP thì VIETMAP là nền mặc định', async () => {
        window.WEBGIS = { geoserver: { url: null, workspace: 'songhau' } };
        vi.doMock('../../resources/js/vietmap', () => ({
            vietmapBasemaps: () => ({ 'Nền Vietmap giả': () => L.tileLayer('https://vm.test/{z}/{x}/{y}.png') }),
        }));
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map, 'KEY');
        expect(map.addedLayers[0]._url).toBe('https://vm.test/{z}/{x}/{y}.png');
        vi.doUnmock('../../resources/js/vietmap');
    });

    it('khôi phục nền đã chọn từ localStorage', async () => {
        localStorage.setItem('webgis_basemap', 'Ảnh vệ tinh (Esri)');
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        expect(map.addedLayers[0]._url).toContain('arcgisonline.com');
    });

    it('nền đã lưu không còn tồn tại thì quay về nền mặc định', async () => {
        localStorage.setItem('webgis_basemap', 'Nền đã bị xoá');
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        expect(map.addedLayers[0]._url).toContain('gwc/service/wmts');
    });

    it('lưu lựa chọn nền khi người dùng đổi baselayer', async () => {
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        map.fire('baselayerchange', { name: 'Ảnh vệ tinh (Esri)' });
        expect(localStorage.getItem('webgis_basemap')).toBe('Ảnh vệ tinh (Esri)');
    });

    it('hiện toạ độ con trỏ và xoá khi chuột rời bản đồ', async () => {
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map);
        map.fire('mousemove', { latlng: L.latLng(10.123456, 105.654321) });
        expect(document.getElementById('cursor-coords').textContent).toBe('10.12346°N, 105.65432°E');
        map.fire('mouseout');
        expect(document.getElementById('cursor-coords').textContent).toBe('');
    });

    it('có API key thì thêm các nền Vietmap vào bộ chọn', async () => {
        vi.doMock('../../resources/js/vietmap', () => ({
            vietmapBasemaps: () => ({ 'Nền Vietmap giả': () => L.tileLayer('https://vm.test/{z}/{x}/{y}.png') }),
        }));
        localStorage.setItem('webgis_basemap', 'Nền Vietmap giả');
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map, 'KEY');
        expect(map.addedLayers[0]._url).toBe('https://vm.test/{z}/{x}/{y}.png');
        vi.doUnmock('../../resources/js/vietmap');
    });

    it('tải Vietmap lỗi thì cảnh báo và vẫn dùng nền GeoServer + Esri', async () => {
        vi.doMock('../../resources/js/vietmap', () => { throw new Error('không tải được'); });
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await initBasemaps(map, 'KEY');
        expect(warn).toHaveBeenCalled();
        expect(map.addedLayers[0]._url).toContain('gwc/service/wmts');
        vi.doUnmock('../../resources/js/vietmap');
    });

    it('localStorage bị chặn thì vẫn khởi tạo được', async () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('blocked'); });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('blocked'); });
        const { initBasemaps } = await load();
        const { map } = makeMap();
        await expect(initBasemaps(map)).resolves.toBeUndefined();
        expect(() => map.fire('baselayerchange', { name: 'x' })).not.toThrow();
    });
});
