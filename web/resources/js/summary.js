import colors from './colors.json';
import { api } from './api';
import { UPDATING } from './status';
import { countUp } from './motion';

const CLASS_LABEL = { accretion: 'Bồi', stable: 'Ổn định', mild_erosion: 'Lùi nhẹ', severe_erosion: 'Lùi mạnh' };
const num = (v, d = 2) => (v === null || v === undefined ? UPDATING : Number(v).toFixed(d));
const pct = (n, total) => (total ? Math.round((n / total) * 100) : 0);

/**
 * Panel "Tổng quan" — số liệu chính của khu vực (kiểu "key findings" của DEA Coastlines).
 * onSelectTransect: gọi khi bấm vào transect lùi/bồi mạnh nhất.
 */
export async function loadSummary(onSelectTransect) {
    const body = document.getElementById('summary-body');
    if (!body) return;

    let s;
    try {
        s = await api.summary();
    } catch {
        body.innerHTML = `<p class="updating">Số liệu tổng quan: ${UPDATING.toLowerCase()}.</p>`;
        return;
    }

    const t = s.transects;
    if (!t.total) {
        body.innerHTML = `<p class="updating">Chưa có kết quả phân tích transect — ${UPDATING.toLowerCase()}.</p>`;
        return;
    }

    const erosion = t.by_class.mild_erosion + t.by_class.severe_erosion;
    const bar = Object.entries(t.by_class)
        .filter(([, n]) => n > 0)
        .map(([c, n]) => `<span style="flex:${n};background:${colors[c]}" title="${CLASS_LABEL[c]}: ${n}"></span>`)
        .join('');
    const rows = Object.entries(t.by_class)
        .map(([c, n]) => `<li><span class="legend-swatch" style="background:${colors[c]}"></span>${CLASS_LABEL[c]}<b data-class="${c}">${n}</b><small>${pct(n, t.total)}%</small></li>`)
        .join('');
    const link = (x) => (x ? `<a href="#" data-transect="${x.id}">${x.transect_code}</a> (${num(x.lrr_m_per_year)} m/năm)` : UPDATING);
    const hotLen = s.hotspots.length_m >= 1000 ? `${(s.hotspots.length_m / 1000).toFixed(2)} km` : `${s.hotspots.length_m} m`;

    const fact = (label, value, id = '') => `<div><dt>${label}</dt><dd${id ? ` id="${id}"` : ''}>${value}</dd></div>`;
    body.innerHTML = `
        <div class="kpis">
            <div class="kpi"><b id="kpi-transects">${t.total}</b><span>transect</span></div>
            <div class="kpi${erosion ? ' is-alert' : ''}"><b id="kpi-erosion">${pct(erosion, t.total)}%</b><span>đang lùi bờ</span></div>
            <div class="kpi"><b id="kpi-years">${s.shorelines.count || '—'}</b><span>năm có đường bờ</span></div>
        </div>
        <div class="class-bar" aria-hidden="true">${bar}</div>
        <ul class="class-counts">${rows}</ul>
        <dl class="facts">
            ${fact('LRR trung bình', `${num(t.mean_lrr_m_per_year)} m/năm`, 'kpi-mean-lrr')}
            ${fact('Có ý nghĩa thống kê', `${t.significant}/${t.total} transect`)}
            ${fact('Lùi mạnh nhất', link(t.max_erosion), 'kpi-max-erosion')}
            ${fact('Bồi mạnh nhất', link(t.max_accretion))}
            ${fact('Hotspot', s.hotspots.count ? `${s.hotspots.count} đoạn · ${hotLen}` : UPDATING)}
            ${fact('Sạt lở tham chiếu', s.reference_events.count ? `${s.reference_events.count} sự kiện` : UPDATING)}
        </dl>
        <h3 class="sub-title">Dịch chuyển bờ trung bình theo năm</h3>
        <div class="chart-box short"><canvas id="yearly-chart" aria-label="Biểu đồ dịch chuyển bờ trung bình theo năm"></canvas></div>
        <p class="hint">Cột đỏ: bờ lùi vào so với năm trước; cột xanh: bờ tiến ra. Trung bình trên các transect có dữ liệu hai năm liền kề.</p>
        <div id="yearly-table"></div>`;
    loadYearly(body);

    // Số liệu đếm lên khi bảng tổng quan hiện ra (đặt lại về giá trị cuối nếu giảm chuyển động).
    countUp(body.querySelector('#kpi-transects'), t.total);
    countUp(body.querySelector('#kpi-erosion'), pct(erosion, t.total), { suffix: '%', delay: 80 });
    if (s.shorelines.count) countUp(body.querySelector('#kpi-years'), s.shorelines.count, { delay: 160 });
    body.querySelectorAll('.class-counts b[data-class]').forEach((b, i) => countUp(b, Number(b.textContent), { delay: 200 + i * 60 }));

    body.querySelectorAll('[data-transect]').forEach((a) => a.addEventListener('click', (e) => {
        e.preventDefault();
        onSelectTransect(Number(a.dataset.transect));
    }));
}

/** Biểu đồ + bảng thống kê theo năm (tổng hợp từ khoảng cách baseline → bờ của từng transect). */
async function loadYearly(body) {
    let stats;
    try {
        stats = await api.yearlyStats();
    } catch {
        body.querySelector('#yearly-table').innerHTML = `<p class="updating">Thống kê theo năm: ${UPDATING.toLowerCase()}.</p>`;
        return;
    }
    const { renderYearlyChart } = await import('./chart');
    renderYearlyChart(body.querySelector('#yearly-chart'), stats);
    const sign = (v) => (v === null ? '—' : `${v > 0 ? '+' : ''}${v.toFixed(1)}`);
    body.querySelector('#yearly-table').innerHTML = `<table class="mini-table"><thead><tr><th>Năm</th><th>Δ năm (m)</th><th>Tích luỹ (m)</th><th>Lùi</th><th>Bồi</th></tr></thead><tbody>${stats
        .map((r) => `<tr><td>${r.year}</td><td class="num">${sign(r.mean_delta_m)}</td><td class="num">${sign(r.cumulative_m)}</td><td class="num">${r.mean_delta_m === null ? '—' : r.eroding}</td><td class="num">${r.mean_delta_m === null ? '—' : r.accreting}</td></tr>`)
        .join('')}</tbody></table>`;
}
