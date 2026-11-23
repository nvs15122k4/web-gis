import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { api, get } from '../../resources/js/api';

const ok = (body) => ({ ok: true, status: 200, json: async () => body });

// fetch giả: phản hồi lại URL và header đã nhận để kiểm tra qua giá trị trả về của api.
const echo = async (url, init) => ({ ok: true, status: 200, json: async () => ({ url, headers: init.headers }) });

describe('api wrapper', () => {
    beforeEach(() => {
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
});
