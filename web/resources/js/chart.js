import Chart from 'chart.js/auto';

Chart.defaults.font.family = "'Be Vietnam Pro', system-ui, sans-serif";
Chart.defaults.color = '#5a6876';

/**
 * Vẽ khoảng cách baseline→bờ theo năm. Trục X là số thực (năm) nên năm thiếu dữ liệu chỉ tạo khoảng trống,
 * không có điểm bịa; đoạn nối qua chỗ thiếu vẽ nét đứt để không gây hiểu nhầm là đo đủ.
 */
export function renderHistoryChart(canvas, history) {
    Chart.getChart(canvas)?.destroy();
    // Phơi số điểm/các năm ra DOM để kiểm thử E2E đối chiếu.
    canvas.dataset.points = String(history.length);
    canvas.dataset.years = history.map((h) => h.year).join(',');
    const empty = document.getElementById('chart-empty');
    if (empty) {
        empty.hidden = history.length > 0;
        empty.textContent = 'Chưa có số liệu vị trí đường bờ — đang cập nhật.';
    }
    if (!history.length) return null;
    return new Chart(canvas, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Khoảng cách tới baseline (m)',
                data: history.map((h) => ({ x: h.year, y: h.distance_m })),
                borderColor: '#0f6e8c',
                backgroundColor: '#0f6e8c',
                pointRadius: 3.5,
                tension: 0,
                segment: { borderDash: (ctx) => (ctx.p1.parsed.x - ctx.p0.parsed.x > 1 ? [6, 4] : undefined) },
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { type: 'linear', ticks: { stepSize: 1, precision: 0, maxRotation: 0, autoSkipPadding: 6, callback: (v) => String(v) }, title: { display: true, text: 'Năm' } },
                y: { title: { display: true, text: 'Cách baseline (m)' } },
            },
        },
    });
}
