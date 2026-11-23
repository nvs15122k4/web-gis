import { beforeEach, describe, expect, it, vi } from 'vitest';

// Plugin Vietmap cần WebGL nên chỉ kiểm tra URL/tuỳ chọn mà module truyền cho Leaflet.
const created = [];
const vietmapGL = (opts) => { const l = { kind: 'vector', opts }; created.push(l); return l; };
const tileLayer = (url, opts) => { const l = { kind: 'raster', url, opts }; created.push(l); return l; };
vi.mock('leaflet', () => ({ default: { vietmapGL: (o) => vietmapGL(o), tileLayer: (u, o) => tileLayer(u, o) } }));
vi.mock('@vietmap/vietmap-gl-js/dist/vietmap-gl.css', () => ({}));
vi.mock('@vietmap/vietmap-gl-leaflet', () => ({}));

const { vietmapBasemaps } = await import('../../resources/js/vietmap');
const { BASEMAP_PANE } = await import('../../resources/js/basemaps');

describe('vietmapBasemaps', () => {
    beforeEach(() => { created.length = 0; });

    it('cung cấp ba nền: đường, sáng, vệ tinh', () => {
        expect(Object.keys(vietmapBasemaps('k'))).toEqual([
            'Bản đồ đường (Vietmap)', 'Nền sáng (Vietmap)', 'Ảnh vệ tinh (Vietmap)',
        ]);
    });

    it('chỉ tạo lớp khi được gọi (lazy), không tạo lúc khởi tạo', () => {
        vietmapBasemaps('k');
        expect(created).toEqual([]);
    });

    it('nền vector dùng style tm/lm kèm apikey và đúng pane', () => {
        const b = vietmapBasemaps('abc');
        const road = b['Bản đồ đường (Vietmap)']();
        const light = b['Nền sáng (Vietmap)']();
        expect(road.kind).toBe('vector');
        expect(road.opts.style).toBe('https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=abc');
        expect(light.opts.style).toBe('https://maps.vietmap.vn/maps/styles/lm/style.json?apikey=abc');
        expect(road.opts.pane).toBe(BASEMAP_PANE);
        expect(road.opts.attribution).toContain('VIETMAP');
    });

    it('ảnh vệ tinh là tile raster, zoom tối đa 20, đúng pane', () => {
        const sat = vietmapBasemaps('abc')['Ảnh vệ tinh (Vietmap)']();
        expect(sat.kind).toBe('raster');
        expect(sat.url).toBe('https://maps.vietmap.vn/maps/tiles/st/{z}/{x}/{y}.png?apikey=abc');
        expect(sat.opts).toMatchObject({ maxZoom: 20, pane: BASEMAP_PANE });
    });

    it('mã hoá apikey có ký tự đặc biệt để không phá URL', () => {
        const sat = vietmapBasemaps('a&b=c d')['Ảnh vệ tinh (Vietmap)']();
        expect(sat.url).toContain('apikey=a%26b%3Dc%20d');
    });
});
