import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadSummary } from '../../resources/js/summary';
import { UPDATING } from '../../resources/js/status';
import { clearApiCache } from '../../resources/js/api';

const summary = (over = {}) => ({
    transects: {
        total: 10,
        by_class: { accretion: 2, stable: 3, mild_erosion: 3, severe_erosion: 2 },
        mean_lrr_m_per_year: -1.234,
        significant: 6,
        max_erosion: { id: 5, transect_code: 'T005', lrr_m_per_year: -4.5 },
        max_accretion: { id: 9, transect_code: 'T009', lrr_m_per_year: 2 },
    },
    shorelines: { count: 8 },
    hotspots: { count: 3, length_m: 2500 },
    reference_events: { count: 4 },
    ...over,
});

const mockFetch = (body) => {
    clearApiCache(); // dữ liệu giả đổi thì api không được trả bản đã nhớ
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => body });
};
const body = () => document.getElementById('summary-body');

describe('loadSummary', () => {
    beforeEach(() => {
        clearApiCache(); // api nhớ kết quả 60 s: mỗi ca kiểm thử phải bắt đầu từ bộ nhớ trống
        document.body.innerHTML = '<div id="summary-body"></div>';
    });
    afterEach(() => { delete globalThis.fetch; });

    it('không có #summary-body thì không gọi API', async () => {
        document.body.innerHTML = '';
        const requested = [];
        globalThis.fetch = async (url) => { requested.push(url); return { ok: true, json: async () => ({}) }; };
        await loadSummary(() => {});
        expect(requested).toEqual([]);
    });

    it('API lỗi thì hiện thông báo đang cập nhật', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, status: 500, json: async () => ({}) });
        await loadSummary(() => {});
        expect(body().textContent).toBe(`Số liệu tổng quan: ${UPDATING.toLowerCase()}.`);
    });

    it('thống kê theo năm không phải mảng thì hiện thông báo đang cập nhật, không ném lỗi', async () => {
        document.body.innerHTML = '<div id="summary-body"></div>';
        globalThis.fetch = vi.fn(async (url) => ({ ok: true, status: 200, json: async () => (String(url).includes('yearly-stats') ? {} : summary()) }));
        await loadSummary(() => {});
        await new Promise((r) => setTimeout(r, 0));
        expect(document.getElementById('yearly-table').textContent).toContain(UPDATING.toLowerCase());
    });

    it('biểu đồ theo năm chỉ tải chart.js khi canvas sắp hiện ra', async () => {
        const renderYearlyChart = vi.fn();
        vi.doMock('../../resources/js/chart', () => ({ renderYearlyChart }));
        let trigger;
        globalThis.IntersectionObserver = class { constructor(cb) { trigger = cb; } observe() {} disconnect() {} };
        const stats = [{ year: 2019, mean_delta_m: 1, cumulative_m: 1, eroding: 1, accreting: 2 }];
        globalThis.fetch = vi.fn(async (url) => ({ ok: true, status: 200, json: async () => (String(url).includes('yearly-stats') ? stats : summary()) }));
        await loadSummary(() => {});
        await new Promise((r) => setTimeout(r, 0));
        expect(document.getElementById('yearly-table').textContent).toContain('2019');
        expect(renderYearlyChart).not.toHaveBeenCalled();
        trigger([{ isIntersecting: false }]);
        await new Promise((r) => setTimeout(r, 0));
        expect(renderYearlyChart).not.toHaveBeenCalled();
        trigger([{ isIntersecting: true }]);
        await vi.waitFor(() => expect(renderYearlyChart).toHaveBeenCalledTimes(1));
        delete globalThis.IntersectionObserver;
        vi.doUnmock('../../resources/js/chart');
    });

    it('chưa có transect nào thì hiện thông báo chưa có kết quả phân tích', async () => {
        mockFetch(summary({ transects: { total: 0 } }));
        await loadSummary(() => {});
        expect(body().textContent).toContain('Chưa có kết quả phân tích transect');
    });

    it('hiển thị KPI: tổng transect, % lùi bờ (nhẹ + mạnh) và số năm đường bờ', async () => {
        mockFetch(summary());
        await loadSummary(() => {});
        expect(document.getElementById('kpi-transects').textContent).toBe('10');
        expect(document.getElementById('kpi-erosion').textContent).toBe('50%');
        expect(document.getElementById('kpi-years').textContent).toBe('8');
        expect(document.getElementById('kpi-mean-lrr').textContent).toBe('-1.23 m/năm');
    });

    it('đánh dấu cảnh báo khi có lùi bờ, không đánh dấu khi không có', async () => {
        mockFetch(summary());
        await loadSummary(() => {});
        expect(body().querySelectorAll('.kpi.is-alert')).toHaveLength(1);

        mockFetch(summary({ transects: { ...summary().transects, by_class: { accretion: 5, stable: 5, mild_erosion: 0, severe_erosion: 0 } } }));
        await loadSummary(() => {});
        expect(body().querySelectorAll('.kpi.is-alert')).toHaveLength(0);
        expect(document.getElementById('kpi-erosion').textContent).toBe('0%');
    });

    it('đếm theo lớp và tỉ lệ phần trăm đúng', async () => {
        mockFetch(summary());
        await loadSummary(() => {});
        expect(body().querySelector('[data-class=severe_erosion]').textContent).toBe('2');
        expect(body().querySelector('[data-class=stable]').nextElementSibling.textContent).toBe('30%');
    });

    it('thanh phân lớp bỏ qua lớp có 0 transect', async () => {
        mockFetch(summary({ transects: { ...summary().transects, by_class: { accretion: 0, stable: 4, mild_erosion: 6, severe_erosion: 0 } } }));
        await loadSummary(() => {});
        expect(body().querySelectorAll('.class-bar span')).toHaveLength(2);
    });

    it('độ dài hotspot: dưới 1000 m hiện m, từ 1000 m hiện km', async () => {
        mockFetch(summary({ hotspots: { count: 1, length_m: 800 } }));
        await loadSummary(() => {});
        expect(body().textContent).toContain('1 đoạn · 800 m');
        mockFetch(summary({ hotspots: { count: 2, length_m: 2500 } }));
        await loadSummary(() => {});
        expect(body().textContent).toContain('2 đoạn · 2.50 km');
    });

    it('đúng biên 999 m và 1000 m', async () => {
        mockFetch(summary({ hotspots: { count: 1, length_m: 999 } }));
        await loadSummary(() => {});
        expect(body().textContent).toContain('999 m');
        mockFetch(summary({ hotspots: { count: 1, length_m: 1000 } }));
        await loadSummary(() => {});
        expect(body().textContent).toContain('1.00 km');
    });

    it('thiếu hotspot, sự kiện tham chiếu hoặc năm đường bờ thì hiện "đang cập nhật"/"—"', async () => {
        mockFetch(summary({ hotspots: { count: 0, length_m: 0 }, reference_events: { count: 0 }, shorelines: { count: 0 } }));
        await loadSummary(() => {});
        expect(document.getElementById('kpi-years').textContent).toBe('—');
        expect(body().textContent.match(new RegExp(UPDATING, 'g'))).toHaveLength(2);
    });

    it('thiếu transect lùi/bồi mạnh nhất hoặc LRR null thì hiện "đang cập nhật"', async () => {
        mockFetch(summary({ transects: { ...summary().transects, max_erosion: null, mean_lrr_m_per_year: null } }));
        await loadSummary(() => {});
        expect(document.getElementById('kpi-max-erosion').textContent).toBe(UPDATING);
        expect(document.getElementById('kpi-mean-lrr').textContent).toBe(`${UPDATING} m/năm`);
    });

    it('bấm liên kết transect gọi callback với id số và chặn điều hướng', async () => {
        mockFetch(summary());
        const onSelect = vi.fn();
        await loadSummary(onSelect);
        const ev = new MouseEvent('click', { bubbles: true, cancelable: true });
        body().querySelector('[data-transect="5"]').dispatchEvent(ev);
        expect(onSelect).toHaveBeenCalledExactlyOnceWith(5);
        expect(ev.defaultPrevented).toBe(true);
    });
});
