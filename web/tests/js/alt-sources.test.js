import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import L from 'leaflet';

const FC = (features = []) => ({ type: 'FeatureCollection', features });
const META = {
    main: { key: 'main', label: 'Bản chính', color: '#1565c0', years: [2018, 2020, 2026] },
    sources: [
        { key: 's2c1', label: 'Sentinel-2 Collection 1', color: '#00c853', years: [2018, 2019, 2020], low_confidence_years: [2018, 2019, 2022] },
        { key: 'landsat', label: 'Landsat 8/9', color: '#ff6d00', years: [2020, 2022], low_confidence_years: [] },
    ],
    note: 'Bản đối chứng độc lập, dùng để kiểm tra chéo bản chính; không thay thế bản chính và không phải dự báo sạt lở.',
};
const COMPARE = (over = {}) => ({
    id: 8, transect_code: 'T08',
    main: { key: 'main', label: 'Bản chính', color: '#1565c0', lrr_m_per_year: -3.5, change_class: 'severe_erosion' },
    sources: [
        { key: 's2c1', label: 'Sentinel-2 Collection 1', color: '#00c853', lrr_m_per_year: -3.1, change_class: 'severe_erosion', diff_vs_main: 0.4, low_confidence_years: [2018, 2019, 2022] },
        { key: 'landsat', label: 'Landsat 8/9', color: '#ff6d00', lrr_m_per_year: null, change_class: null, diff_vs_main: null, low_confidence_years: [] },
    ],
    note: META.note,
    ...over,
});

const html = `<div id="map"></div><div id="status"></div>
    <section id="alt-sources"><div id="alt-source-list"></div><p id="alt-low-note" hidden></p><button id="alt-clear"></button></section>
    <div id="alt-compare"><div id="alt-compare-body"></div></div>`;

const setup = async (geoserver = { url: null, workspace: 'songhau' }) => {
    document.body.innerHTML = html;
    window.WEBGIS = { geoserver, years: { all: [2018, 2019], available: [2018, 2019] } };
    vi.resetModules();
    const mod = await import('../../resources/js/alt-sources');
    const map = L.map('map', { center: [10.5, 105.2], zoom: 13 });
    map.createPane('shorelines');
    return { mod, map };
};

const urls = () => globalThis.fetch.mock.calls.map(([u]) => String(u));

describe('alt-sources: lớp đường bờ theo nguồn (GeoJSON)', () => {
    beforeEach(() => {
        globalThis.fetch = vi.fn(async (url) => {
            const u = String(url);
            const body = u.includes('/alt-sources') ? META : u.includes('/compare') ? COMPARE() : FC();
            return { ok: true, status: 200, json: async () => body };
        });
    });
    afterEach(() => { delete globalThis.fetch; });

    it('màu cố định: bản chính #1565c0, S2 C1 #00c853, Landsat #ff6d00', async () => {
        const { mod } = await setup();
        expect(mod.SOURCE_COLORS).toEqual({ main: '#1565c0', s2c1: '#00c853', landsat: '#ff6d00' });
    });

    it('bản chính gọi /api/shorelines không kèm source; bản đối chứng kèm ?source=', async () => {
        const { mod } = await setup();
        mod.sourceShorelineLayer('main', 2020);
        mod.sourceShorelineLayer('s2c1', 2020);
        mod.sourceShorelineLayer('landsat', 2022);
        await vi.waitFor(() => expect(urls()).toHaveLength(3));
        expect(urls()[0]).toBe('/api/shorelines?year=2020');
        expect(urls()[1]).toBe('/api/shorelines?year=2020&source=s2c1');
        expect(urls()[2]).toBe('/api/shorelines?year=2022&source=landsat');
    });

    it('vẽ đường bờ bằng đúng màu của nguồn', async () => {
        const { mod, map } = await setup();
        const layer = mod.sourceShorelineLayer('landsat', 2020).addTo(map);
        layer.addData({ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [[105.2, 10.5], [105.21, 10.51]] } });
        expect(layer.getLayers()[0].options.color).toBe('#ff6d00');
    });

    it('nguồn lạ bị từ chối, không gọi mạng', async () => {
        const { mod } = await setup();
        expect(() => mod.sourceShorelineLayer("s2c1' OR 1=1", 2020)).toThrow();
        expect(globalThis.fetch).not.toHaveBeenCalled();
    });

    it('khung công tắc: một hàng mỗi nguồn, năm đúng theo API, năm tin cậy thấp có dấu cảnh báo', async () => {
        const { mod, map } = await setup();
        mod.initAltSources(map);
        await vi.waitFor(() => expect(document.querySelectorAll('.alt-source')).toHaveLength(3));
        const years = (k) => [...document.querySelectorAll(`.alt-source[data-source=${k}] input`)].map((i) => Number(i.dataset.year));
        expect(years('main')).toEqual([2018, 2020, 2026]);
        expect(years('s2c1')).toEqual([2018, 2019, 2020]);
        expect(years('landsat')).toEqual([2020, 2022]);
        expect(document.querySelector('#alt-s2c1-2018').closest('label').classList.contains('is-low')).toBe(true);
        expect(document.querySelector('#alt-s2c1-2020').closest('label').classList.contains('is-low')).toBe(false);
        expect(document.querySelector('#alt-landsat-2022').closest('label').classList.contains('is-low')).toBe(false);
        const note = document.getElementById('alt-low-note');
        expect(note.hidden).toBe(false);
        expect(note.textContent).toContain('2018, 2019, 2022');
        expect(note.textContent).toContain('độ tin cậy thấp');
    });

    it('bật/tắt công tắc thêm/bớt lớp trên bản đồ và nhớ lớp theo (nguồn, năm)', async () => {
        const { mod, map } = await setup();
        const alt = mod.initAltSources(map);
        await vi.waitFor(() => expect(document.querySelector('#alt-s2c1-2020')).not.toBeNull());
        const box = document.querySelector('#alt-s2c1-2020');
        box.checked = true;
        box.dispatchEvent(new Event('change', { bubbles: true }));
        const layer = alt.s2c1.get(2020);
        expect(map.hasLayer(layer)).toBe(true);
        box.checked = false;
        box.dispatchEvent(new Event('change', { bubbles: true }));
        expect(map.hasLayer(layer)).toBe(false);
        box.checked = true;
        box.dispatchEvent(new Event('change', { bubbles: true }));
        expect(alt.s2c1.get(2020)).toBe(layer); // dùng lại, không tạo lại
        expect(map.hasLayer(layer)).toBe(true);
    });

    it('bật cả ba nguồn cùng năm rồi "Tắt hết" gỡ mọi lớp', async () => {
        const { mod, map } = await setup();
        const alt = mod.initAltSources(map);
        await vi.waitFor(() => expect(document.querySelector('#alt-landsat-2020')).not.toBeNull());
        for (const id of ['#alt-main-2020', '#alt-s2c1-2020', '#alt-landsat-2020']) {
            const b = document.querySelector(id);
            b.checked = true;
            b.dispatchEvent(new Event('change', { bubbles: true }));
        }
        expect([alt.main, alt.s2c1, alt.landsat].every((m) => map.hasLayer(m.get(2020)))).toBe(true);
        document.getElementById('alt-clear').click();
        expect([alt.main, alt.s2c1, alt.landsat].some((m) => map.hasLayer(m.get(2020)))).toBe(false);
        expect(document.querySelectorAll('#alt-source-list input:checked')).toHaveLength(0);
    });

    it('không có #alt-sources thì bỏ qua êm (trang không có khung)', async () => {
        const { mod, map } = await setup();
        document.getElementById('alt-sources').remove();
        expect(() => mod.initAltSources(map)).not.toThrow();
        expect(urls().some((u) => u.includes('/alt-sources'))).toBe(false);
    });

    it('API đối chứng lỗi: ghi "Đang cập nhật", không ném lỗi', async () => {
        const { mod, map } = await setup();
        globalThis.fetch = vi.fn(async () => ({ ok: false, status: 500, json: async () => ({}) }));
        mod.initAltSources(map);
        await vi.waitFor(() => expect(document.getElementById('alt-source-list').textContent.toLowerCase()).toContain('đang cập nhật'));
    });
});

describe('alt-sources: lớp WMS', () => {
    afterEach(() => { delete globalThis.fetch; });

    it('bản chính dùng lớp shorelines, bản đối chứng dùng shorelines_alt với CQL năm và nguồn', async () => {
        const { mod } = await setup({ url: 'http://gs/geoserver', workspace: 'songhau' });
        const main = mod.sourceShorelineLayer('main', 2020);
        const s2 = mod.sourceShorelineLayer('s2c1', 2020);
        expect(main.wmsParams.layers).toBe('songhau:shorelines');
        expect(main.wmsParams.cql_filter).toBe('year=2020');
        expect(main.wmsParams.env).toBe('color:1565c0');
        expect(s2.wmsParams.layers).toBe('songhau:shorelines_alt');
        expect(s2.wmsParams.cql_filter).toBe("year=2020 AND source='s2c1'");
        expect(s2.wmsParams.env).toBe('color:00c853');
    });
});

describe('alt-sources: panel so sánh LRR của transect', () => {
    afterEach(() => { delete globalThis.fetch; });

    it('compareHtml: ba hàng đúng thứ tự, số liệu, nhóm, chênh và chú thích độc lập', async () => {
        const { mod } = await setup();
        document.getElementById('alt-compare-body').innerHTML = mod.compareHtml(COMPARE());
        const rows = [...document.querySelectorAll('.alt-table tbody tr')];
        expect(rows.map((r) => r.dataset.source)).toEqual(['main', 's2c1', 'landsat']);
        expect(rows[0].textContent).toContain('-3.50');
        expect(rows[0].textContent).toContain('Lùi mạnh');
        expect(rows[1].textContent).toContain('+0.40');
        expect(rows[2].textContent).toContain('Đang cập nhật'); // thiếu số liệu: không bịa 0
        expect(rows[0].querySelector('.line-key').style.background).toContain('rgb(21, 101, 192)');
        expect(rows[1].querySelector('.line-key').style.background).toContain('rgb(0, 200, 83)');
        expect(rows[2].querySelector('.line-key').style.background).toContain('rgb(255, 109, 0)');
        expect(document.getElementById('alt-compare-note').textContent).toContain('đối chứng độc lập');
        expect(document.getElementById('alt-compare-note').textContent).toContain('không phải dự báo sạt lở');
        expect(document.getElementById('alt-compare-low').textContent).toContain('Sentinel-2 Collection 1 năm 2018, 2019, 2022');
    });

    it('compareHtml: không có năm tin cậy thấp thì không hiện cảnh báo; nhãn lạ được escape', async () => {
        const { mod } = await setup();
        const d = COMPARE();
        d.sources.forEach((s) => { s.low_confidence_years = []; });
        d.main.change_class = '<img src=x onerror=alert(1)>';
        const out = mod.compareHtml(d);
        expect(out).not.toContain('alt-compare-low');
        expect(out).not.toContain('<img');
        expect(out).toContain('&lt;img');
    });

    it('chọn transect → gọi /api/transects/{id}/compare và vẽ bảng', async () => {
        globalThis.fetch = vi.fn(async () => ({ ok: true, status: 200, json: async () => COMPARE() }));
        const { mod } = await setup();
        mod.initComparePanel();
        document.dispatchEvent(new CustomEvent('transect:selected', { detail: { id: 8 } }));
        await vi.waitFor(() => expect(document.querySelector('.alt-table')).not.toBeNull());
        expect(urls()).toEqual(['/api/transects/8/compare']);
        expect(document.getElementById('alt-compare-body').hasAttribute('aria-busy')).toBe(false);
    });

    it('chọn liên tiếp: chỉ kết quả của lần chọn cuối được hiển thị', async () => {
        const resolvers = {};
        globalThis.fetch = vi.fn((url) => new Promise((resolve) => {
            resolvers[String(url)] = () => resolve({ ok: true, status: 200, json: async () => COMPARE({ transect_code: String(url).includes('/1/') ? 'T01' : 'T02' }) });
        }));
        const { mod } = await setup();
        mod.initComparePanel();
        document.dispatchEvent(new CustomEvent('transect:selected', { detail: { id: 1 } }));
        document.dispatchEvent(new CustomEvent('transect:selected', { detail: { id: 2 } }));
        resolvers['/api/transects/2/compare']();
        await vi.waitFor(() => expect(document.querySelector('.alt-table')).not.toBeNull());
        resolvers['/api/transects/1/compare'](); // trả muộn: phải bị bỏ
        await new Promise((r) => setTimeout(r, 10));
        expect(document.querySelectorAll('.alt-table')).toHaveLength(1);
    });

    it('API lỗi: báo "Đang cập nhật" và vẫn ghi chú bản đối chứng độc lập', async () => {
        globalThis.fetch = vi.fn(async () => ({ ok: false, status: 500, json: async () => ({}) }));
        const { mod } = await setup();
        mod.initComparePanel();
        document.dispatchEvent(new CustomEvent('transect:selected', { detail: { id: 8 } }));
        await vi.waitFor(() => expect(document.getElementById('alt-compare-body').textContent.toLowerCase()).toContain('đang cập nhật'));
        expect(document.getElementById('alt-compare-body').textContent).toContain('đối chứng độc lập');
    });
});
