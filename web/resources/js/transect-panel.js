import { readableTextOn } from './contrast.js';
import L from 'leaflet';
import colors from './colors.json';
import { api } from './api';
import { setStatus, UPDATING } from './status';
import { fadeIn } from './motion';
import { WMS_URL, TRANSECT_LAYER, USE_WMS, handlers, layers } from './layers';

const CLASS_LABEL = {
    accretion: 'Bồi',
    stable: 'Ổn định',
    mild_erosion: 'Lùi nhẹ',
    severe_erosion: 'Lùi mạnh',
};

const missing = (v) => v === null || v === undefined;
const fmt = (v, d = 2) => (missing(v) ? '—' : Number(v).toFixed(d));
/** Giá trị kèm đơn vị; chưa có số liệu thì ghi "Đang cập nhật". */
const val = (v, unit = '', d = 2) => (missing(v) ? `<span class="updating">${UPDATING}</span>` : `${fmt(v, d)}${unit}`);

let permalink = null;
let transectIndex = null;
let mapRef = null;
const highlight = L.layerGroup();

const geometries = () => (transectIndex ??= api.transects().then((fc) => new Map(fc.features.map((f) => [f.id, f.geometry]))));

/**
 * Chọn transect → nạp chi tiết từ API Laravel. Có GeoServer: click bản đồ → WMS GetFeatureInfo;
 * không có: bấm thẳng vào đường GeoJSON của lớp transect.
 */
export function initTransectPanel(map, link = null) {
    permalink = link;
    mapRef = map;
    highlight.addTo(map);
    document.getElementById('inspector-close')?.addEventListener('click', closeInspector);
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closeInspector());
    if (!USE_WMS) {
        handlers.onTransectClick = async (f, latlng) => {
            const props = { ...f.properties, id: f.id };
            L.popup().setLatLng(latlng).setContent(`<strong>${props.transect_code}</strong><br>${CLASS_LABEL[props.change_class] ?? ''}`).openOn(map);
            try {
                await showTransect(props.id);
            } catch {
                setStatus(`Thông tin transect: ${UPDATING.toLowerCase()}.`);
            }
        };
        return;
    }
    map.on('click', async (e) => {
        if (!map.hasLayer(layers.transects)) return;
        try {
            const props = await featureAt(map, e.latlng);
            if (!props) return;
            L.popup().setLatLng(e.latlng).setContent(`<strong>${props.transect_code}</strong><br>${CLASS_LABEL[props.change_class] ?? ''}`).openOn(map);
            await showTransect(props.id);
        } catch {
            setStatus(`Thông tin transect (GeoServer): ${UPDATING.toLowerCase()}.`);
        }
    });
}

function openInspector() {
    const panel = document.getElementById('inspector');
    if (!panel) return;
    panel.hidden = false;
    document.getElementById('main-content')?.classList.add('inspecting');
}

function closeInspector() {
    const panel = document.getElementById('inspector');
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    document.getElementById('main-content')?.classList.remove('inspecting');
    highlight.clearLayers();
    permalink?.setTransect(null);
}

/** Chừa chỗ cho thẻ chi tiết (bên phải trên máy tính, bottom sheet trên điện thoại) để transect không bị che. */
function paddingAroundInspector(map) {
    const el = document.getElementById('inspector');
    if (!el || el.hidden) return { padding: [40, 40] };
    const sheet = el.offsetWidth >= map.getSize().x - 1;
    return {
        paddingTopLeft: [40, 40],
        paddingBottomRight: sheet ? [40, el.offsetHeight + 24] : [el.offsetWidth + 40, 40],
    };
}

/** Viền nổi bật transect đang chọn để người xem biết thẻ chi tiết đang nói về đoạn nào. */
async function highlightTransect(id) {
    highlight.clearLayers();
    if (!mapRef) return;
    const geometry = (await geometries().catch(() => new Map())).get(id);
    if (!geometry) return;
    L.geoJSON(geometry, { style: { color: '#fff', weight: 9, opacity: 0.9 }, interactive: false }).addTo(highlight);
    const core = L.geoJSON(geometry, { style: { color: '#0c2d3f', weight: 4 }, interactive: false }).addTo(highlight);
    // Vòng Vermilion lan ra từ giữa đoạn được chọn (CSS thuần, tắt khi giảm chuyển động).
    L.marker(core.getBounds().getCenter(), {
        icon: L.divIcon({ className: 'select-ring', html: '<i></i><i></i><i></i><b></b>', iconSize: [0, 0] }),
        interactive: false, keyboard: false, zIndexOffset: 500,
    }).addTo(highlight);
}

const ICON_ALERT = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v5M12 17.5v.01"/></svg>';

/** Trạng thái đang tải trong thẻ chi tiết (chỉ khi thẻ đã mở sẵn, tránh nháy thẻ rỗng). */
function showDetailLoading() {
    const panel = document.getElementById('inspector');
    if (!panel || panel.hidden) return;
    panel.querySelector('.inspector-body')?.setAttribute('aria-busy', 'true');
    document.getElementById('transect-detail').innerHTML = '<div class="detail-skeleton" aria-hidden="true"><i></i><i></i><i></i><i></i></div>';
}

function showDetailError() {
    const panel = document.getElementById('inspector');
    if (!panel || panel.hidden) return;
    panel.querySelector('.inspector-body')?.removeAttribute('aria-busy');
    document.getElementById('transect-detail').innerHTML = `<div class="state-block is-error" role="alert">${ICON_ALERT}<div><strong>Không tải được chi tiết transect</strong>${UPDATING}. Thử chọn lại đoạn này sau ít giây.</div></div>`;
}

async function featureAt(map, latlng) {
    const size = map.getSize();
    const b = map.getBounds();
    const sw = map.options.crs.project(b.getSouthWest());
    const ne = map.options.crs.project(b.getNorthEast());
    const p = map.latLngToContainerPoint(latlng);
    const qs = new URLSearchParams({
        service: 'WMS', version: '1.1.1', request: 'GetFeatureInfo',
        layers: TRANSECT_LAYER, query_layers: TRANSECT_LAYER,
        srs: 'EPSG:3857', bbox: [sw.x, sw.y, ne.x, ne.y].join(','),
        width: size.x, height: size.y, x: Math.round(p.x), y: Math.round(p.y),
        info_format: 'application/json', feature_count: 1, buffer: 8,
    });
    if (layers.transectFilter) qs.set('cql_filter', layers.transectFilter);
    const res = await fetch(`${WMS_URL}?${qs}`);
    if (!res.ok) throw new Error(`GetFeatureInfo ${res.status}`);
    const json = await res.json();
    return json.features?.[0]?.properties ?? null;
}

/** Phóng bản đồ tới một transect (hình học lấy từ /api/transects, nạp một lần). */
export async function zoomToTransect(map, id) {
    const geometry = (await geometries()).get(id);
    if (geometry) map.fitBounds(L.geoJSON(geometry).getBounds(), { maxZoom: 17, ...paddingAroundInspector(map) });
}

export async function showTransect(id) {
    document.dispatchEvent(new CustomEvent('transect:selected', { detail: { id: Number(id) } }));
    showDetailLoading();
    let detail;
    let history;
    try {
        [detail, history] = await Promise.all([api.transect(id), api.transectHistory(id)]);
    } catch (e) {
        showDetailError();
        throw e;
    }
    const s = detail.stats ?? {};
    const cls = s.change_class;
    const years = history.map((h) => h.year);
    // Mục 2.5 đề cương: R² < 0,3 vẫn giữ nếu có ý nghĩa thống kê, nhưng phải ghi chú diễn giải thận trọng.
    const weakFit = s.is_significant && !missing(s.r_squared) && s.r_squared < 0.3;

    document.getElementById('transect-code').textContent = detail.transect_code;
    const badge = document.getElementById('stat-class');
    badge.textContent = CLASS_LABEL[cls] ?? UPDATING;
    const bg = colors[cls] ?? '#888888';
    badge.style.background = bg;
    badge.style.color = readableTextOn(bg);

    const row = (label, id, value) => `<div><dt>${label}</dt><dd id="${id}">${value}</dd></div>`;
    document.getElementById('transect-detail').innerHTML = `
        <div class="stat-tiles">
            <div class="stat-tile" title="Net Shoreline Movement"><span>NSM</span><b id="stat-nsm">${val(s.nsm_m, ' m')}</b></div>
            <div class="stat-tile" title="End Point Rate"><span>EPR</span><b id="stat-epr">${val(s.epr_m_per_year, ' m/năm')}</b></div>
            <div class="stat-tile" title="Linear Regression Rate"><span>LRR</span><b id="stat-lrr">${val(s.lrr_m_per_year, ' m/năm')}</b></div>
        </div>
        <dl class="stat-list">
            ${row('R²', 'stat-r2', val(s.r_squared, '', 3))}
            ${row('KTC 95% của LRR', 'stat-ci', missing(s.unc_lrr) ? val(null) : `±${fmt(s.unc_lrr)} m/năm`)}
            ${row('Ý nghĩa thống kê', 'stat-sig', missing(s.is_significant) ? val(null) : s.is_significant ? 'Có (|LRR| &gt; t·SE, t theo bậc tự do n−2)' : 'Không — chưa đủ cơ sở kết luận')}
            ${row('Độ bất định', 'stat-u', missing(s.u_shoreline_avg) ? val(null) : `±${fmt(s.u_shoreline_avg, 1)} m (SE LRR ${fmt(s.se_lrr, 3)})`)}
            ${row('Số giai đoạn lùi liên tiếp', 'stat-periods', val(s.consecutive_erosion_periods, '', 0))}
            ${row('Số năm quan trắc', 'stat-obs', years.length ? `${years.length} năm (${years[0]}–${years[years.length - 1]})` : val(null))}
        </dl>
        ${weakFit ? '<p class="note" id="stat-note">Xu hướng tuyến tính chưa rõ ràng (R² &lt; 0,3), cần diễn giải thận trọng.</p>' : ''}`;
    document.querySelector('#inspector .inspector-body')?.removeAttribute('aria-busy');
    openInspector();
    fadeIn(document.getElementById('transect-detail'), { duration: 260, from: 6 });
    permalink?.setTransect(id);
    highlightTransect(id);
    // Chart.js (~200 kB) chỉ tải khi người dùng mở transect đầu tiên, không nằm trong bundle khởi động.
    const { renderHistoryChart } = await import('./chart');
    renderHistoryChart(document.getElementById('history-chart'), history);
}
