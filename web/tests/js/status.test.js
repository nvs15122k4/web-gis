import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadDataStatus, setStatus, UPDATING } from '../../resources/js/status';

const respond = (body, okFlag = true) => vi.fn().mockResolvedValue({ ok: okFlag, status: okFlag ? 200 : 500, json: async () => body });

describe('setStatus', () => {
    beforeEach(() => { document.body.innerHTML = '<div id="status"></div>'; });

    it('ghi thông báo vào #status và xoá khi gọi không tham số', () => {
        setStatus('không có dữ liệu');
        expect(document.getElementById('status').textContent).toBe('không có dữ liệu');
        setStatus();
        expect(document.getElementById('status').textContent).toBe('');
    });

    it('không có phần tử #status thì không đụng tới phần còn lại của trang', () => {
        document.body.innerHTML = '<p id="other">giữ nguyên</p>';
        setStatus('x');
        expect(document.body.innerHTML).toBe('<p id="other">giữ nguyên</p>');
    });
});

describe('loadDataStatus', () => {
    beforeEach(() => { document.body.innerHTML = '<div id="data-banner" hidden></div>'; });
    afterEach(() => { delete globalThis.fetch; });

    const banner = () => document.getElementById('data-banner');

    it('đủ dữ liệu và GeoServer chạy thì ẩn banner', async () => {
        globalThis.fetch = respond({ geoserver: true, data: { shorelines: 1, transects: 1, hotspots: 1, reference_events: 1 } });
        await loadDataStatus();
        expect(banner().hidden).toBe(true);
        expect(banner().innerHTML).toBe('');
    });

    it('liệt kê các lớp còn thiếu dữ liệu', async () => {
        globalThis.fetch = respond({ geoserver: true, data: { shorelines: 1, transects: 0, hotspots: 0, reference_events: 1 } });
        await loadDataStatus();
        expect(banner().hidden).toBe(false);
        expect(banner().textContent).toBe(`Dữ liệu transect, hotspot: ${UPDATING.toLowerCase()}.`);
    });

    it('GeoServer tắt thì thêm dòng riêng về lớp bản đồ', async () => {
        globalThis.fetch = respond({ geoserver: false, data: { shorelines: 1, transects: 1, hotspots: 1, reference_events: 1 } });
        await loadDataStatus();
        expect(banner().textContent).toContain('Lớp bản đồ GeoServer');
        expect(banner().querySelectorAll('div')).toHaveLength(1);
    });

    it('API lỗi thì coi mọi lớp là đang cập nhật và vẫn trả về status mặc định', async () => {
        globalThis.fetch = respond({}, false);
        const status = await loadDataStatus();
        expect(status).toEqual({ mysql: false, postgis: false, geoserver: false, data: {} });
        expect(banner().hidden).toBe(false);
        expect(banner().textContent).toContain('đường bờ, transect, hotspot, sạt lở tham chiếu');
        expect(banner().textContent).toContain('GeoServer');
    });

    it('thiếu trường data trong phản hồi không gây lỗi', async () => {
        globalThis.fetch = respond({ geoserver: true });
        await loadDataStatus();
        expect(banner().textContent).toContain('đường bờ');
    });

    it('thiếu #data-banner thì không ném lỗi', async () => {
        document.body.innerHTML = '';
        globalThis.fetch = respond({ geoserver: true, data: {} });
        await expect(loadDataStatus()).resolves.toBeTruthy();
    });
});
