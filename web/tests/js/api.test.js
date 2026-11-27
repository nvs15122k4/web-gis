import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, clearApiCache, get, prefetchShorelines } from '../../resources/js/api';

const ok = (body) => ({ ok: true, status: 200, json: async () => body });

// fetch giả: phản hồi lại URL và header đã nhận để kiểm tra qua giá trị trả về của api.
const echo = async (url, init) => ({ ok: true, status: 200, json: async () => ({ url, headers: init.headers }) });

describe('api wrapper', () => {
    beforeEach(() => {
        clearApiCache();
        globalThis.fetch = echo;
    });
    afterEach(() => {
        delete globalThis.fetch;
    });

    it('gọi /api{path} với header Accept JSON và trả về body đã parse', async () => {
        const res = await get('/summary');
        expect(res).toEqual({ url: '/api/summary', headers: { Accept: 'application/json' } });
    });

    it('ghép query string từ params', async () => {
        expect((await get('/x', { a: 1, b: 'hai ba' })).url).toBe('/api/x?a=1&b=hai+ba');
    });

    it('ném lỗi có path và mã trạng thái khi HTTP không ok', async () => {
        globalThis.fetch = async () => ({ ok: false, status: 503, json: async () => ({}) });
        await expect(get('/status')).rejects.toThrow('API /status → 503');
    });

    it('shorelines: có năm thì gửi ?year=, không có thì không gửi query', async () => {
        expect((await api.shorelines(2020)).url).toBe('/api/shorelines?year=2020');
        expect((await api.shorelines()).url).toBe('/api/shorelines');
    });

    it('hotspots: min_rate = 0 vẫn được gửi, null và chuỗi rỗng thì bỏ', async () => {
        const urls = [];
        for (const v of [0, null, '', 1.5]) urls.push((await api.hotspots(v)).url);
        expect(urls).toEqual([
            '/api/erosion-hotspots?min_rate=0',
            '/api/erosion-hotspots',
            '/api/erosion-hotspots',
            '/api/erosion-hotspots?min_rate=1.5',
        ]);
    });

    it('referenceEvents: bbox được mã hoá trong query, thiếu bbox thì không có query', async () => {
        expect((await api.referenceEvents('105.1,10.5,105.3,10.7')).url).toBe('/api/reference-events?bbox=105.1%2C10.5%2C105.3%2C10.7');
        expect((await api.referenceEvents()).url).toBe('/api/reference-events');
    });

    it('các endpoint còn lại trỏ đúng đường dẫn', async () => {
        const urls = (await Promise.all([
            api.transect(7), api.transectHistory(7), api.thresholds(), api.transects(), api.summary(),
        ])).map((r) => r.url);
        expect(urls).toEqual([
            '/api/transects/7',
            '/api/transects/7/history',
            '/api/config/thresholds',
            '/api/transects',
            '/api/summary',
        ]);
    });

    it('các yêu cầu giống hệt nhau đang bay dùng chung một lần fetch', async () => {
        const fetchMock = vi.fn(echo);
        globalThis.fetch = fetchMock;
        const [a, b] = await Promise.all([api.transects(), api.transects()]);
        expect(a).toBe(b);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('dữ liệu pipeline được nhớ, lần gọi lại không tốn fetch; hết hạn hoặc xoá cache thì tải lại', async () => {
        const fetchMock = vi.fn(echo);
        globalThis.fetch = fetchMock;
        await api.shorelines(2020);
        await api.shorelines(2020);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        clearApiCache();
        await api.shorelines(2020);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('cấu hình admin vừa sửa (thresholds, layers) và sạt lở theo bbox không bị nhớ', async () => {
        const fetchMock = vi.fn(echo);
        globalThis.fetch = fetchMock;
        await api.thresholds();
        await api.thresholds();
        await api.layerSettings();
        await api.layerSettings();
        expect(fetchMock).toHaveBeenCalledTimes(4);
    });

    it('lỗi HTTP không bị nhớ: lần sau gọi lại được', async () => {
        globalThis.fetch = async () => ({ ok: false, status: 500, json: async () => ({}) });
        await expect(api.summary()).rejects.toThrow('500');
        const fetchMock = vi.fn(echo);
        globalThis.fetch = fetchMock;
        await api.summary();
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('prefetchShorelines: một yêu cầu year[] cho nhiều năm, rồi api.shorelines(năm) lấy từ đó', async () => {
        const fc = { features: [{ properties: { year: 2018 } }, { properties: { year: 2019 } }, { properties: { year: 2019 } }] };
        const fetchMock = vi.fn(async () => ({ ok: true, status: 200, json: async () => fc }));
        globalThis.fetch = fetchMock;
        const done = prefetchShorelines([2018, 2019]);
        const [y18, y19] = await Promise.all([api.shorelines(2018), api.shorelines(2019)]);
        await done;
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(decodeURIComponent(fetchMock.mock.calls[0][0])).toBe('/api/shorelines?year[]=2018&year[]=2019');
        expect(y18.features).toHaveLength(1);
        expect(y19.features).toHaveLength(2);
    });

    it('prefetchShorelines: chỉ một năm thì không gộp (để api.shorelines tự gọi)', async () => {
        const fetchMock = vi.fn(echo);
        globalThis.fetch = fetchMock;
        await prefetchShorelines([2018]);
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
