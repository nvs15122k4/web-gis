import colors from './colors.json';
import { api } from './api';
import { UPDATING } from './status';

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
        </dl>`;

    body.querySelectorAll('[data-transect]').forEach((a) => a.addEventListener('click', (e) => {
        e.preventDefault();
        onSelectTransect(Number(a.dataset.transect));
    }));
}
