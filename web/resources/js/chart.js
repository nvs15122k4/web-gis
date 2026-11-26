import Chart from 'chart.js/auto';

Chart.defaults.font.family = "'Public Sans', system-ui, sans-serif";
Chart.defaults.font.size = 11;

/** Lấy màu theo giao diện hiện tại từ biến CSS; dự phòng khi chưa có biến. */
const cssVar = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

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
    const muted = cssVar('--ink-3', '#566472');
    const line = cssVar('--chart-line', '#0f766e');
    const grid = cssVar('--chart-grid', '#e1e6eb');
    Chart.defaults.color = muted;
    return new Chart(canvas, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Khoảng cách tới baseline (m)',
                data: history.map((h) => ({ x: h.year, y: h.distance_m })),
                borderColor: line,
                backgroundColor: line,
                pointRadius: 3,
                borderWidth: 2,
                tension: 0,
                segment: { borderDash: (ctx) => (ctx.p1.parsed.x - ctx.p0.parsed.x > 1 ? [6, 4] : undefined) },
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { type: 'linear', ticks: { stepSize: 1, precision: 0, maxRotation: 0, autoSkipPadding: 6, callback: (v) => String(v) }, title: { display: true, text: 'Năm' }, grid: { color: grid } },
                y: { title: { display: true, text: 'Cách baseline (m)' }, grid: { color: grid } },
            },
        },
    });
}

/**
 * Cột Δ vị trí bờ trung bình so với năm trước (m): đỏ = lùi vào, xanh = tiến ra. Chỉ vẽ năm có dữ liệu liền kề.
 */
export function renderYearlyChart(canvas, stats) {
    Chart.getChart(canvas)?.destroy();
    const rows = stats.filter((r) => r.mean_delta_m !== null);
    if (!rows.length) return null;
    Chart.defaults.color = cssVar('--ink-3', '#566472');
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((r) => r.year),
            datasets: [{
                label: 'Δ so với năm trước (m)',
                data: rows.map((r) => r.mean_delta_m),
                backgroundColor: rows.map((r) => (r.mean_delta_m < 0 ? '#d0312d' : '#2a7fb8')),
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.parsed.y > 0 ? '+' : ''}${c.parsed.y} m so với năm trước` } } },
            scales: { y: { title: { display: true, text: 'Δ vị trí bờ (m)' }, grid: { color: cssVar('--chart-grid', '#e1e6eb') } }, x: { grid: { display: false } } },
        },
    });
}
