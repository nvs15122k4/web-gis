import L from 'leaflet';
import { api } from './api';
import { setStatus, UPDATING } from './status';
import { SHORELINE_PANE, USE_WMS, wms } from './layers';

/** Màu cố định từng nguồn (khớp API/legend): bản chính xanh dương, S2 C1 xanh lá, Landsat cam. */
export const SOURCE_COLORS = { main: '#1565c0', s2c1: '#00c853', landsat: '#ff6d00' };
const SOURCE_LABELS = { main: 'Bản chính', s2c1: 'Sentinel-2 Collection 1', landsat: 'Landsat 8/9' };
const CLASS_LABEL = { accretion: 'Bồi', stable: 'Ổn định', mild_erosion: 'Lùi nhẹ', severe_erosion: 'Lùi mạnh' };

export const NOTE_INDEPENDENT = 'Bản đối chứng độc lập, dùng để kiểm tra chéo bản chính; không thay thế bản chính và không phải dự báo sạt lở.';

/** Lớp đường bờ đang bật: altLayers[nguồn] = Map(năm → layer Leaflet). Gắn vào layers.alt để kiểm thử/E2E truy cập. */
export const altLayers = { main: new Map(), s2c1: new Map(), landsat: new Map() };

const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const missing = (v) => v === null || v === undefined;
const updating = `<span class="updating">${UPDATING}</span>`;
const num = (v, d = 2) => (missing(v) ? updating : Number(v).toFixed(d));
const signed = (v) => (missing(v) ? updating : `${Number(v) > 0 ? '+' : ''}${Number(v).toFixed(2)}`);

/** Lớp đường bờ của một nguồn/năm: WMS (lọc CQL theo năm và nguồn) hoặc GeoJSON từ /api/shorelines?source=. */
export function sourceShorelineLayer(key, year, color = SOURCE_COLORS[key]) {
    if (!(key in SOURCE_COLORS)) throw new Error(`Nguồn không hợp lệ: ${key}`);
    const alt = key !== 'main';
    if (USE_WMS) {
        return wms(alt ? 'shorelines_alt' : 'shorelines', {
            cql_filter: alt ? `year=${Number(year)} AND source='${key}'` : `year=${Number(year)}`,
            env: `color:${color.replace('#', '')}`,
            pane: SHORELINE_PANE,
        });
    }
    const layer = L.geoJSON(null, {
        pane: SHORELINE_PANE,
        style: { color, weight: alt ? 2.5 : 3, opacity: 0.95, className: `shoreline-path shoreline-${key}` },
        interactive: false,
    });
    api.shorelines(year, alt ? key : undefined).then((fc) => layer.addData(fc)).catch(() => {
        setStatus(`Dữ liệu đường bờ ${SOURCE_LABELS[key]} năm ${year}: ${UPDATING.toLowerCase()}.`);
    });
    return layer;
}

function chip(key, year, low) {
    const label = document.createElement('label');
    label.className = `year-chip${low ? ' is-low' : ''}`;
    label.title = low ? `Năm ${year}: độ tin cậy thấp (ít cảnh ảnh hợp lệ)` : `Đường bờ ${SOURCE_LABELS[key]} năm ${year}`;
    label.innerHTML = `<input type="checkbox" id="alt-${key}-${year}" data-source="${key}" data-year="${year}" aria-label="${esc(SOURCE_LABELS[key])} năm ${year}${low ? ', độ tin cậy thấp' : ''}">${year}${low ? '<span class="low-mark" aria-hidden="true">⚠</span>' : ''}<span class="year-swatch" style="background:${SOURCE_COLORS[key]}"></span>`;
    return label;
}

/**
 * Khung "Đường bờ đối chứng": mỗi nguồn (bản chính / S2 C1 / Landsat) một hàng công tắc theo năm, đúng màu riêng.
 * Phần tử gốc là `#alt-sources` (không có thì bỏ qua). Trả về altLayers.
 */
export function initAltSources(map, root = document) {
    const section = root.getElementById ? root.getElementById('alt-sources') : root.querySelector('#alt-sources');
    if (!section) return altLayers;
    const list = section.querySelector('#alt-source-list');
    const lowNote = section.querySelector('#alt-low-note');

    const toggle = (key, year, on) => {
        const layer = altLayers[key].get(year);
        if (!on) {
            if (layer) map.removeLayer(layer);
            return;
        }
        if (!layer) altLayers[key].set(year, sourceShorelineLayer(key, year));
        altLayers[key].get(year).addTo(map);
    };

    list.addEventListener('change', (e) => {
        const box = e.target.closest?.('input[data-source]');
        if (box) toggle(box.dataset.source, Number(box.dataset.year), box.checked);
    });
    section.querySelector('#alt-clear')?.addEventListener('click', () => {
        list.querySelectorAll('input[data-source]:checked').forEach((box) => {
            box.checked = false;
            toggle(box.dataset.source, Number(box.dataset.year), false);
        });
    });

    api.altSources().then((meta) => {
        list.innerHTML = '';
        const rows = [
            { key: 'main', years: meta.main?.years ?? [], low: [] },
            ...(meta.sources ?? []).map((s) => ({ key: s.key, years: s.years, low: s.low_confidence_years ?? [] })),
        ].filter((r) => r.key in SOURCE_COLORS);
        for (const { key, years, low } of rows) {
            const group = document.createElement('div');
            group.className = 'alt-source';
            group.dataset.source = key;
            group.innerHTML = `<h3 class="alt-source-title"><i class="line-key" style="background:${SOURCE_COLORS[key]}"></i>${esc(SOURCE_LABELS[key])}</h3>`;
            const grid = document.createElement('div');
            grid.className = 'year-grid';
            years.forEach((y) => grid.appendChild(chip(key, y, low.includes(y))));
            if (!years.length) grid.innerHTML = `<p class="hint">${UPDATING}</p>`;
            group.appendChild(grid);
            list.appendChild(group);
        }
        const s2 = (meta.sources ?? []).find((s) => s.key === 's2c1');
        if (lowNote && s2?.low_confidence_years?.length) {
            lowNote.hidden = false;
            lowNote.textContent = `Sentinel-2 Collection 1 năm ${s2.low_confidence_years.join(', ')}: độ tin cậy thấp (ít cảnh ảnh hợp lệ), cần diễn giải thận trọng.`;
        }
    }).catch(() => {
        list.innerHTML = `<p class="hint">Dữ liệu đối chứng: ${UPDATING.toLowerCase()}.</p>`;
    });
    return altLayers;
}

/** HTML bảng so sánh LRR ba nguồn của một transect (dữ liệu từ /api/transects/{id}/compare). */
export function compareHtml(d) {
    const rows = [d.main, ...d.sources];
    const body = rows.map((r) => `<tr data-source="${esc(r.key)}">
        <th scope="row"><i class="line-key" style="background:${esc(SOURCE_COLORS[r.key] ?? r.color)}"></i>${esc(SOURCE_LABELS[r.key] ?? r.label)}</th>
        <td class="num">${num(r.lrr_m_per_year)}</td>
        <td>${missing(r.change_class) ? updating : esc(CLASS_LABEL[r.change_class] ?? r.change_class)}</td>
        <td class="num">${r.key === 'main' ? '—' : signed(r.diff_vs_main)}</td></tr>`).join('');
    const low = d.sources.filter((s) => s.low_confidence_years?.length)
        .map((s) => `${SOURCE_LABELS[s.key] ?? s.label} năm ${s.low_confidence_years.join(', ')}`);
    return `<table class="alt-table"><thead><tr><th scope="col">Nguồn</th><th scope="col">LRR (m/năm)</th><th scope="col">Nhóm</th><th scope="col" title="LRR đối chứng trừ LRR bản chính">Chênh</th></tr></thead><tbody>${body}</tbody></table>
        <p class="hint" id="alt-compare-note">${esc(d.note ?? NOTE_INDEPENDENT)}</p>
        ${low.length ? `<p class="note" id="alt-compare-low">⚠ ${esc(low.join('; '))}: độ tin cậy thấp (ít cảnh ảnh hợp lệ), cần diễn giải thận trọng.</p>` : ''}`;
}

/** Panel so sánh trong thẻ chi tiết transect: nghe sự kiện `transect:selected` do showTransect phát ra. */
export function initComparePanel(root = document) {
    const box = root.getElementById ? root.getElementById('alt-compare') : root.querySelector('#alt-compare');
    if (!box) return;
    const body = box.querySelector('#alt-compare-body') ?? box;
    let seq = 0;
    document.addEventListener('transect:selected', async (e) => {
        const mine = ++seq;
        body.setAttribute('aria-busy', 'true');
        try {
            const d = await api.transectCompare(e.detail.id);
            if (mine !== seq) return; // đã chọn transect khác: bỏ kết quả cũ
            body.innerHTML = compareHtml(d);
        } catch {
            if (mine !== seq) return;
            body.innerHTML = `<p class="hint">Số liệu đối chứng: ${UPDATING.toLowerCase()}.</p><p class="hint">${esc(NOTE_INDEPENDENT)}</p>`;
        } finally {
            if (mine === seq) body.removeAttribute('aria-busy');
        }
    });
}
