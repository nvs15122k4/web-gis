import { beforeEach, describe, expect, it, vi } from 'vitest';

// jsdom không có canvas 2D nên thay Chart.js bằng bản giả ghi lại cấu hình truyền vào.
const ChartMock = vi.fn(function Chart(canvas, config) {
    this.canvas = canvas;
    this.config = config;
});
ChartMock.getChart = vi.fn();
ChartMock.defaults = { font: {}, color: '' };
vi.mock('chart.js/auto', () => ({ default: ChartMock }));

const { renderHistoryChart } = await import('../../resources/js/chart');

const history = [
    { year: 2015, distance_m: 100 },
    { year: 2016, distance_m: 98 },
    { year: 2020, distance_m: 90 },
];

describe('renderHistoryChart', () => {
    let canvas;
    beforeEach(() => {
        document.body.innerHTML = '<canvas id="c"></canvas><p id="chart-empty" hidden></p>';
        canvas = document.getElementById('c');
        ChartMock.mockClear();
        ChartMock.getChart.mockReset();
    });

    it('phơi số điểm và các năm ra data-attribute để E2E đối chiếu', () => {
        renderHistoryChart(canvas, history);
        expect(canvas.dataset.points).toBe('3');
        expect(canvas.dataset.years).toBe('2015,2016,2020');
    });

    it('vẽ dữ liệu với x là năm (số thực) và y là khoảng cách', () => {
        const chart = renderHistoryChart(canvas, history);
        expect(chart.config.type).toBe('line');
        expect(chart.config.data.datasets[0].data).toEqual([
            { x: 2015, y: 100 }, { x: 2016, y: 98 }, { x: 2020, y: 90 },
        ]);
        expect(chart.config.options.scales.x.type).toBe('linear');
    });

    it('đoạn nối qua năm thiếu (cách > 1 năm) vẽ nét đứt, năm liền kề vẽ nét liền', () => {
        const { segment } = renderHistoryChart(canvas, history).config.data.datasets[0];
        const seg = (x0, x1) => ({ p0: { parsed: { x: x0 } }, p1: { parsed: { x: x1 } } });
        expect(segment.borderDash(seg(2015, 2016))).toBeUndefined();
        expect(segment.borderDash(seg(2016, 2020))).toEqual([6, 4]);
    });

    it('không có dữ liệu: trả về null, hiện thông báo và không tạo biểu đồ', () => {
        const result = renderHistoryChart(canvas, []);
        expect(result).toBeNull();
        expect(ChartMock).not.toHaveBeenCalled();
        const empty = document.getElementById('chart-empty');
        expect(empty.hidden).toBe(false);
        expect(empty.textContent).toContain('Chưa có số liệu');
        expect(canvas.dataset.points).toBe('0');
    });

    it('có dữ liệu thì ẩn thông báo trống', () => {
        document.getElementById('chart-empty').hidden = false;
        renderHistoryChart(canvas, history);
        expect(document.getElementById('chart-empty').hidden).toBe(true);
    });

    it('huỷ biểu đồ cũ trên cùng canvas trước khi vẽ lại', () => {
        const old = { destroyed: false, destroy() { this.destroyed = true; } };
        ChartMock.getChart.mockReturnValue(old);
        const fresh = renderHistoryChart(canvas, history);
        expect(old.destroyed).toBe(true);
        expect(fresh).not.toBe(old);
    });

    it('thiếu #chart-empty vẫn xử lý được dữ liệu rỗng', () => {
        document.getElementById('chart-empty').remove();
        expect(renderHistoryChart(canvas, [])).toBeNull();
        expect(canvas.dataset.points).toBe('0');
    });

    it('khoảng hở đúng 1 năm thiếu (cách 2 năm) cũng vẽ nét đứt', () => {
        const { segment } = renderHistoryChart(canvas, history).config.data.datasets[0];
        expect(segment.borderDash({ p0: { parsed: { x: 2015 } }, p1: { parsed: { x: 2017 } } })).toEqual([6, 4]);
    });
});
