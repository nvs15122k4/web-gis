import L from 'leaflet';
import { api } from './api';
import { UPDATING } from './status';

const CLASS_LABEL = { accretion: 'Bồi', stable: 'Ổn định', mild_erosion: 'Lùi nhẹ', severe_erosion: 'Lùi mạnh' };

/** Cột bảng thuộc tính transect: key lấy từ properties của /api/transects (do pipeline tính sẵn). */
export const COLUMNS = [
    { key: 'transect_code', label: 'Mã transect', type: 'text' },
    { key: 'change_class', label: 'Mức biến động', type: 'text', format: (v) => CLASS_LABEL[v] ?? v },
    { key: 'epr_m_per_year', label: 'EPR (m/năm)', type: 'num' },
    { key: 'lrr_m_per_year', label: 'LRR (m/năm)', type: 'num' },
    { key: 'is_significant', label: 'Ý nghĩa thống kê', type: 'bool', format: (v) => (v ? 'Có' : 'Không') },
];

/** Giá trị hiển thị của một ô; thiếu dữ liệu thì "Đang cập nhật", không điền số giả. */
export function cellText(col, props) {
    const v = props[col.key];
    if (v === null || v === undefined || v === '') return UPDATING;
    if (col.format) return col.format(v);
    return col.type === 'num' ? Number(v).toFixed(2) : String(v);
}

/** Sắp xếp ổn định theo cột; giá trị thiếu luôn xuống cuối, dù tăng hay giảm. */
export function sortRows(rows, key, dir = 1, type = 'text') {
    const val = (r) => r.properties[key];
    const missing = (v) => v === null || v === undefined || v === '';
    return [...rows].sort((a, b) => {
        const x = val(a);
        const y = val(b);
        if (missing(x) || missing(y)) return missing(x) - missing(y) || 0;
        const cmp = type === 'num' ? Number(x) - Number(y) : String(x).localeCompare(String(y), 'vi', { numeric: true });
        return cmp * dir;
    });
}

/** Giữ các dòng có tâm hình học nằm trong khung nhìn. bounds: [[s, w], [n, e]]. */
export function inBounds(rows, bounds) {
    const [[s, w], [n, e]] = bounds;
    return rows.filter((r) => {
        const c = centroid(r.geometry);
        return c && c[0] >= s && c[0] <= n && c[1] >= w && c[1] <= e;
    });
}

/** Điểm giữa (vĩ độ, kinh độ) của hình học GeoJSON LineString/Point, null nếu không có toạ độ. */
export function centroid(geometry) {
    const pts = geometry?.type === 'Point' ? [geometry.coordinates] : geometry?.coordinates;
    if (!Array.isArray(pts) || !pts.length) return null;
    const flat = Array.isArray(pts[0][0]) ? pts.flat() : pts;
    const mid = flat[Math.floor(flat.length / 2)];
    return [mid[1], mid[0]];
}

/** CSV (UTF-8, có BOM để Excel đọc đúng tiếng Việt); dấu ngoặc kép trong ô được nhân đôi. */
export function toCsv(rows, columns = COLUMNS) {
    const esc = (s) => `"${String(s).replaceAll('"', '""')}"`;
    const head = columns.map((c) => esc(c.label)).join(',');
    const body = rows.map((r) => columns.map((c) => esc(cellText(c, r.properties))).join(',')).join('\n');
    return `﻿${head}\n${body}\n`;
}

/**
 * Bảng thuộc tính liên kết hai chiều với bản đồ.
 * opts: map, root (phần tử chứa), onSelect(id) khi bấm dòng, viewportFilter (mặc định bật).
 */
export function createAttrTable({ map, root, onSelect = () => {}, highlightStyle = { color: '#e94b35', weight: 6, opacity: 0.95 } }) {
    const body = root.querySelector('[data-attr-body]');
    const head = root.querySelector('[data-attr-head]');
    const count = root.querySelector('[data-attr-count]');
    const viewport = root.querySelector('[data-attr-viewport]');
    const text = root.querySelector('[data-attr-text]');
    const csvBtn = root.querySelector('[data-attr-csv]');
    const state = { rows: [], sortKey: 'transect_code', dir: 1, selected: null, shown: [] };
    const hl = L.geoJSON(null, { style: highlightStyle, interactive: false }).addTo(map);

    head.innerHTML = `<tr>${COLUMNS.map((c) => `<th scope="col" data-key="${c.key}" aria-sort="none"><button type="button">${c.label}<i aria-hidden="true"></i></button></th>`).join('')}</tr>`;

    const visible = () => {
        let rows = state.rows;
        if (viewport?.checked) rows = inBounds(rows, [[map.getBounds().getSouth(), map.getBounds().getWest()], [map.getBounds().getNorth(), map.getBounds().getEast()]]);
        const q = text?.value.trim().toLowerCase();
        if (q) rows = rows.filter((r) => COLUMNS.some((c) => cellText(c, r.properties).toLowerCase().includes(q)));
        const col = COLUMNS.find((c) => c.key === state.sortKey);
        return sortRows(rows, state.sortKey, state.dir, col?.type);
    };

    const render = () => {
        state.shown = visible();
        count.textContent = state.rows.length
            ? `${state.shown.length}/${state.rows.length} transect${viewport?.checked ? ' trong khung nhìn' : ''}`
            : `Dữ liệu transect: ${UPDATING.toLowerCase()}.`;
        body.innerHTML = state.shown.map((r) => {
            const p = r.properties;
            return `<tr data-id="${r.id}" class="cls-${p.change_class ?? 'none'}${state.selected === r.id ? ' is-selected' : ''}" tabindex="0" aria-selected="${state.selected === r.id}">${
                COLUMNS.map((c) => `<td${c.type === 'num' ? ' class="num"' : ''}>${cellText(c, p)}</td>`).join('')}</tr>`;
        }).join('');
        head.querySelectorAll('th').forEach((th) => {
            const on = th.dataset.key === state.sortKey;
            th.setAttribute('aria-sort', on ? (state.dir === 1 ? 'ascending' : 'descending') : 'none');
        });
    };

    /** Chọn từ bản đồ hoặc bảng: tô sáng đối tượng và cuộn dòng tương ứng vào tầm nhìn. */
    const select = (id, { fromTable = false, zoom = false } = {}) => {
        state.selected = id;
        const row = state.rows.find((r) => r.id === id);
        hl.clearLayers();
        if (row) {
            hl.addData(row);
            if (zoom) map.fitBounds(hl.getBounds(), { maxZoom: 17, padding: [60, 60] });
        }
        render();
        body.querySelector(`tr[data-id="${id}"]`)?.scrollIntoView({ block: 'nearest' });
        if (fromTable) onSelect(id);
    };

    body.addEventListener('click', (e) => {
        const tr = e.target.closest('tr[data-id]');
        if (tr) select(Number(tr.dataset.id), { fromTable: true, zoom: true });
    });
    body.addEventListener('keydown', (e) => {
        const tr = e.target.closest('tr[data-id]');
        if (tr && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            select(Number(tr.dataset.id), { fromTable: true, zoom: true });
        }
    });
    head.addEventListener('click', (e) => {
        const th = e.target.closest('th[data-key]');
        if (!th) return;
        state.dir = state.sortKey === th.dataset.key ? -state.dir : 1;
        state.sortKey = th.dataset.key;
        render();
    });
    viewport?.addEventListener('change', render);
    text?.addEventListener('input', render);
    let timer = null;
    map.on('moveend', () => {
        if (!viewport?.checked) return;
        clearTimeout(timer);
        timer = setTimeout(render, 150);
    });
    csvBtn?.addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([toCsv(state.shown)], { type: 'text/csv;charset=utf-8' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: 'bang-thuoc-tinh-transect.csv' });
        a.click();
        URL.revokeObjectURL(url);
    });
    document.addEventListener('transect:selected', (e) => {
        if (e.detail?.id !== state.selected) select(e.detail.id);
    });

    const ready = api.transects().then((fc) => {
        state.rows = fc.features;
        render();
    }).catch(() => {
        count.textContent = `Dữ liệu transect: ${UPDATING.toLowerCase()}.`;
    });
    return { select, render, ready, state };
}
