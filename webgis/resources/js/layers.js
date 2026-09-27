import L from 'leaflet';
import colors from './colors.json';
import { api } from './api';
import { setStatus, UPDATING } from './status';

const { geoserver, years } = window.WEBGIS;
const wmsUrl = `${geoserver.url}/${geoserver.workspace}/wms`;

/** Layer WMS từ GeoServer; lọc năm bằng CQL_FILTER (không tạo layer riêng mỗi năm). */
export function wms(name, params = {}) {
    return L.tileLayer.wms(wmsUrl, {
        layers: `${geoserver.workspace}:${name}`,
        format: 'image/png',
        transparent: true,
        version: '1.1.1',
        ...params,
    });
}

export const WMS_URL = wmsUrl;
export const TRANSECT_LAYER = `${geoserver.workspace}:transects`;

const YEAR_COLORS = ['#6a3d9a', '#1f78b4', '#33a02c', '#e31a8c', '#ff7f00', '#a6761d', '#00838f', '#5c6bc0', '#546e7a'];
const COMPARE_COLORS = { a: '#1f6feb', b: '#f0883e' };

const hex = (c) => c.replace('#', '');

function shorelineLayer(year, color) {
    return wms('shorelines', { cql_filter: `year=${year}`, env: `color:${hex(color)}` });
}

/** Trạng thái các lớp, để bật/tắt và để kiểm thử truy cập. */
export const layers = {
    transects: null,
    /** CQL_FILTER đang áp cho lớp transect (dùng chung cho WMS GetMap và GetFeatureInfo). */
    transectFilter: null,
    hotspots: L.layerGroup(),
    reference: L.layerGroup(),
    years: new Map(),
    compare: { a: null, b: null },
};

/** GeoServer chưa chạy/chưa publish layer: báo một lần thay vì để bản đồ trống không lời giải thích. */
function warnOnTileError(layer) {
    let warned = false;
    return layer.on('tileerror', () => {
        if (warned) return;
        warned = true;
        setStatus(`Lớp bản đồ GeoServer ${UPDATING.toLowerCase()}.`);
    });
}

export function initLayers(map) {
    layers.transects = warnOnTileError(wms('transects', { styles: '' })).addTo(map);
    layers.hotspots.addTo(map);

    bindToggle('layer-transects', map, () => layers.transects);
    bindSignificanceFilter();
    bindToggle('layer-hotspots', map, () => layers.hotspots);
    bindReference(map);
    initYearList(map);
    initCompare(map);
    initHotspotFilter();
}

function bindToggle(id, map, getLayer) {
    document.getElementById(id).addEventListener('change', (e) => {
        e.target.checked ? getLayer().addTo(map) : map.removeLayer(getLayer());
    });
}

/** "Chỉ transect có ý nghĩa thống kê" — như bộ lọc p-value của DEA Coastlines; tiêu chí |LRR| > 1,96·SE do pipeline tính. */
function bindSignificanceFilter() {
    document.getElementById('only-significant').addEventListener('change', (e) => {
        layers.transectFilter = e.target.checked ? 'is_significant=true' : null;
        // GeoServer bỏ qua CQL_FILTER rỗng không được: gửi INCLUDE để tắt lọc.
        layers.transects.setParams({ cql_filter: layers.transectFilter ?? 'INCLUDE' });
    });
}

/* ---- Đường bờ theo năm ---- */

function hasData(year) {
    return years.available.includes(year);
}

function initYearList(map) {
    const list = document.getElementById('year-list');
    years.all.forEach((year, i) => {
        const label = document.createElement('label');
        label.innerHTML = `<input type="checkbox" id="year-${year}" data-year="${year}"><span class="year-swatch" style="background:${YEAR_COLORS[i % YEAR_COLORS.length]}"></span>${year}`;
        if (!hasData(year)) label.title = `Năm ${year}: ${UPDATING.toLowerCase()}`;
        list.appendChild(label);
        label.querySelector('input').addEventListener('change', (e) => {
            if (!e.target.checked) {
                const layer = layers.years.get(year);
                if (layer) map.removeLayer(layer);
                setStatus('');
                return;
            }
            if (!hasData(year)) {
                e.target.checked = false;
                setStatus(`Không có dữ liệu đường bờ năm ${year} — ${UPDATING.toLowerCase()}.`);
                return;
            }
            setStatus('');
            if (!layers.years.has(year)) layers.years.set(year, warnOnTileError(shorelineLayer(year, YEAR_COLORS[i % YEAR_COLORS.length])));
            layers.years.get(year).addTo(map);
        });
    });
}

function initCompare(map) {
    const opts = years.all.map((y) => `<option value="${y}">${y}</option>`).join('');
    const a = document.getElementById('compare-a');
    const b = document.getElementById('compare-b');
    a.innerHTML = opts;
    b.innerHTML = opts;
    a.value = String(years.all[0]);
    b.value = String(years.all[years.all.length - 1]);

    const clear = () => {
        for (const k of ['a', 'b']) {
            if (layers.compare[k]) map.removeLayer(layers.compare[k]);
            layers.compare[k] = null;
        }
    };

    document.getElementById('compare-clear').addEventListener('click', () => {
        clear();
        setStatus('');
    });

    document.getElementById('compare-apply').addEventListener('click', () => {
        clear();
        const missing = [];
        for (const [k, sel] of [['a', a], ['b', b]]) {
            const year = Number(sel.value);
            if (!hasData(year)) {
                missing.push(year);
                continue;
            }
            layers.compare[k] = warnOnTileError(shorelineLayer(year, COMPARE_COLORS[k])).addTo(map);
        }
        setStatus(missing.length ? `Không có dữ liệu đường bờ năm ${missing.join(', ')} — ${UPDATING.toLowerCase()}.` : '');
    });
}

/* ---- Hotspot (GeoJSON từ API, lọc theo ngưỡng) ---- */

export async function loadHotspots(minRate) {
    const fc = await api.hotspots(minRate);
    layers.hotspots.clearLayers();
    L.geoJSON(fc, {
        style: (f) => ({ color: colors.severe_erosion, weight: 3 + Math.min(Number(f.properties.severity) || 0, 8), opacity: 0.85 }),
        onEachFeature: (f, layer) => layer.bindPopup(`<strong>${f.properties.notes ?? 'Hotspot'}</strong><br>Tốc độ lùi bờ: ${f.properties.severity} m/năm`),
    }).eachLayer((l) => layers.hotspots.addLayer(l));

    const count = `${fc.features.length} đoạn từ ${minRate ?? 0} m/năm`;
    document.getElementById('hotspot-count').textContent = fc.features.length ? count : `${count} — ${UPDATING.toLowerCase()}.`;
    document.getElementById('hotspot-list').innerHTML = fc.features
        .map((f) => `<li data-severity="${f.properties.severity}">${f.properties.notes ?? 'Hotspot'} — ${f.properties.severity} m/năm</li>`)
        .join('');
    return fc;
}

async function initHotspotFilter() {
    const input = document.getElementById('min-rate');
    try {
        const cfg = await api.thresholds();
        input.value = cfg.find((c) => c.key === 'min_erosion_rate')?.value ?? '';
    } catch {
        input.placeholder = UPDATING;
    }
    const apply = () => loadHotspots(input.value === '' ? undefined : input.value).catch(() => {
        layers.hotspots.clearLayers();
        document.getElementById('hotspot-count').textContent = `Dữ liệu hotspot: ${UPDATING.toLowerCase()}.`;
        document.getElementById('hotspot-list').innerHTML = '';
    });
    document.getElementById('apply-filter').addEventListener('click', apply);
    input.addEventListener('keydown', (e) => e.key === 'Enter' && apply());
    apply();
}

/* ---- Sạt lở tham chiếu: chỉ tải sự kiện trong khung nhìn ---- */

/** Mức độ theo QĐ 01/2011/QĐ-TTg — cách bản đồ sạt lở ĐBSCL của Tổng cục PCTT phân cấp. */
export const DANGER = {
    dac_biet_nguy_hiem: { label: 'Đặc biệt nguy hiểm', color: '#b71c1c' },
    nguy_hiem: { label: 'Nguy hiểm', color: '#ef6c00' },
    binh_thuong: { label: 'Bình thường', color: '#7b3fe4' },
};
const DANGER_UNKNOWN = { label: 'Chưa phân loại', color: '#9e9e9e' };

function referencePopup(p) {
    const or = (v, suffix = '') => (v === null || v === undefined || v === '' ? `<span class="updating">${UPDATING}</span>` : `${v}${suffix}`);
    const level = DANGER[p.danger_level];
    return `<strong>Sạt lở tham chiếu</strong>
        <table class="popup-table">
            <tr><th>Mức độ</th><td>${level ? `<span class="dot" style="background:${level.color}"></span>${level.label}` : or(null)}</td></tr>
            <tr><th>Ngày</th><td>${or(p.event_date)}</td></tr>
            <tr><th>Địa điểm</th><td>${or(p.location)}</td></tr>
            <tr><th>Chiều dài</th><td>${or(p.length_m === null || p.length_m === undefined ? null : Math.round(p.length_m), ' m')}</td></tr>
        </table>
        ${p.description ? `<p>${p.description}</p>` : ''}
        <small>Nguồn: ${or(p.source)}</small>`;
}

function bindReference(map) {
    const box = document.getElementById('layer-reference');
    const statusEl = document.getElementById('reference-status');

    let seq = 0;
    const refresh = async () => {
        if (!box.checked) return;
        const b = map.getBounds();
        const mine = ++seq;
        try {
            const fc = await api.referenceEvents([b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(','));
            if (mine !== seq) return; // đã có yêu cầu mới hơn (bản đồ vừa di chuyển): bỏ kết quả cũ
            layers.reference.clearLayers();
            L.geoJSON(fc, {
                pointToLayer: (f, ll) => {
                    const c = DANGER[f.properties.danger_level]?.color ?? DANGER_UNKNOWN.color;
                    return L.circleMarker(ll, { radius: 8, color: c, fillColor: c, fillOpacity: 0.7 });
                },
                style: (f) => ({ color: DANGER[f.properties.danger_level]?.color ?? DANGER_UNKNOWN.color, weight: 5 }),
                onEachFeature: (f, layer) => layer.bindPopup(referencePopup(f.properties)),
            }).eachLayer((l) => layers.reference.addLayer(l));
            statusEl.textContent = fc.features.length ? `${fc.features.length} sự kiện trong khung nhìn` : 'Không có sự kiện tham chiếu trong khung nhìn hiện tại.';
        } catch {
            if (mine !== seq) return;
            statusEl.textContent = `Dữ liệu sạt lở tham chiếu: ${UPDATING.toLowerCase()}.`;
        }
    };

    box.addEventListener('change', () => {
        if (box.checked) {
            layers.reference.addTo(map);
            refresh();
        } else {
            map.removeLayer(layers.reference);
            layers.reference.clearLayers();
            statusEl.textContent = '';
        }
    });
    map.on('moveend', refresh);
}
