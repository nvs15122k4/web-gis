import L from 'leaflet';
import colors from './colors.json';
import { api } from './api';
import { renderHistoryChart } from './chart';
import { setStatus, UPDATING } from './status';
import { WMS_URL, TRANSECT_LAYER, layers } from './layers';

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

/** Click bản đồ → WMS GetFeatureInfo trên lớp transect → nạp chi tiết từ API Laravel. */
export function initTransectPanel(map, link = null) {
    permalink = link;
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
    transectIndex ??= api.transects().then((fc) => new Map(fc.features.map((f) => [f.id, f.geometry])));
    const geometry = (await transectIndex).get(id);
    if (geometry) map.fitBounds(L.geoJSON(geometry).getBounds(), { maxZoom: 17, padding: [40, 40] });
}

export async function showTransect(id) {
    const [detail, history] = await Promise.all([api.transect(id), api.transectHistory(id)]);
    const s = detail.stats ?? {};
    const cls = s.change_class;
    const years = history.map((h) => h.year);
    // Mục 2.5 đề cương: R² < 0,3 vẫn giữ nếu có ý nghĩa thống kê, nhưng phải ghi chú diễn giải thận trọng.
    const weakFit = s.is_significant && !missing(s.r_squared) && s.r_squared < 0.3;
    document.getElementById('transect-detail').innerHTML = `
        <p><strong id="transect-code">${detail.transect_code}</strong>
           <span class="class-badge" id="stat-class" style="background:${colors[cls] ?? '#888'}">${CLASS_LABEL[cls] ?? UPDATING}</span></p>
        <dl>
            <dt>NSM</dt><dd id="stat-nsm">${val(s.nsm_m, ' m')}</dd>
            <dt>EPR</dt><dd id="stat-epr">${val(s.epr_m_per_year, ' m/năm')}</dd>
            <dt>LRR</dt><dd id="stat-lrr">${val(s.lrr_m_per_year, ' m/năm')}</dd>
            <dt>R²</dt><dd id="stat-r2">${val(s.r_squared, '', 3)}</dd>
            <dt>Độ bất định</dt><dd id="stat-u">${missing(s.u_shoreline_avg) ? val(null) : `±${fmt(s.u_shoreline_avg, 1)} m (SE LRR ${fmt(s.se_lrr, 3)})`}</dd>
            <dt>Số giai đoạn lùi liên tiếp</dt><dd id="stat-periods">${val(s.consecutive_erosion_periods, '', 0)}</dd>
            <dt>Ý nghĩa thống kê</dt><dd id="stat-sig">${missing(s.is_significant) ? val(null) : s.is_significant ? 'Có (|LRR| &gt; 1,96·SE)' : 'Không — chưa đủ cơ sở kết luận'}</dd>
            <dt>KTC 95% của LRR</dt><dd id="stat-ci">${missing(s.se_lrr) ? val(null) : `±${fmt(1.96 * s.se_lrr)} m/năm`}</dd>
            <dt>Số năm quan trắc</dt><dd id="stat-obs">${years.length ? `${years.length} năm (${years[0]}–${years[years.length - 1]})` : val(null)}</dd>
        </dl>
        ${weakFit ? '<p class="note" id="stat-note">Xu hướng tuyến tính chưa rõ ràng (R² &lt; 0,3), cần diễn giải thận trọng.</p>' : ''}`;
    permalink?.setTransect(id);
    renderHistoryChart(document.getElementById('history-chart'), history);
}
