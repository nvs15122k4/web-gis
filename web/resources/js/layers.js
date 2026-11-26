import L from 'leaflet';
import colors from './colors.json';
import { api } from './api';
import { setStatus, UPDATING } from './status';

const { geoserver, years } = window.WEBGIS;
/** Có GEOSERVER_URL thì vẽ bằng WMS (như cũ); không có thì vẽ GeoJSON từ /api/* bằng Leaflet. */
export const USE_WMS = Boolean(geoserver?.url);
const wmsUrl = USE_WMS ? `${geoserver.url}/${geoserver.workspace}/wms` : null;

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
export const TRANSECT_LAYER = USE_WMS ? `${geoserver.workspace}:transects` : null;

// Thang màu tuần tự (plasma): năm càng gần đây càng sáng, như DEA Coastlines.
const YEAR_COLORS = ['#0d0887', '#47039f', '#7301a8', '#9c179e', '#bd3786', '#d8576b', '#ed7953', '#fb9f3a', '#e8b800'];
const COMPARE_COLORS = { a: '#1f6feb', b: '#f0883e' };

const hex = (c) => c.replace('#', '');

function shorelineLayer(year, color) {
    if (USE_WMS) return wms('shorelines', { cql_filter: `year=${year}`, env: `color:${hex(color)}`, pane: SHORELINE_PANE });
    // Chế độ GeoJSON: tải đường bờ của đúng năm khi lớp được tạo, vẽ vào lớp rỗng đã trả về.
    const layer = L.geoJSON(null, { pane: SHORELINE_PANE, style: { color, weight: 2, opacity: 0.95, className: 'shoreline-path' }, interactive: false });
    api.shorelines(year).then((fc) => layer.addData(fc)).catch(() => {
        setStatus(`Dữ liệu đường bờ năm ${year}: ${UPDATING.toLowerCase()}.`);
    });
    return layer;
}

/** Callback khi bấm một transect trong chế độ GeoJSON (transect-panel gán, tránh import vòng). */
export const handlers = { onTransectClick: null };

/**
 * Lớp transect GeoJSON: tô theo change_class, lọc "chỉ có ý nghĩa thống kê" ở phía trình duyệt.
 * Dùng canvas với vùng bấm rộng vì transect mảnh.
 */
function geoJsonTransects() {
    const layer = L.geoJSON(null, {
        renderer: L.canvas({ tolerance: 6 }),
        style: (f) => ({ color: colors[f.properties.change_class] ?? '#888888', weight: 3, opacity: 0.9 }),
        onEachFeature: (f, l) => l.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            handlers.onTransectClick?.(f, e.latlng);
        }),
    });
    let all = null;
    let significantOnly = false;
    const redraw = () => {
        layer.clearLayers();
        if (!all) return;
        layer.addData({ ...all, features: all.features.filter((f) => !significantOnly || f.properties.is_significant) });
    };
    layer.setSignificantOnly = (flag) => {
        significantOnly = flag;
        redraw();
    };
    api.transects().then((fc) => {
        all = fc;
        redraw();
    }).catch(() => setStatus(`Dữ liệu transect: ${UPDATING.toLowerCase()}.`));
    return layer;
}

/** Thang màu phân kỳ theo LRR (m/năm): đỏ = lùi bờ, xanh = bồi, xám nhạt = ổn định (kiểu DEA Coastlines). */
export const RATE_STOPS = [
    { max: -2, color: '#b2182b', label: '< −2' },
    { max: -1, color: '#ef6c4d', label: '−2 … −1' },
    { max: -0.3, color: '#f6b48e', label: '−1 … −0,3' },
    { max: 0.3, color: '#d9d9d9', label: '−0,3 … +0,3' },
    { max: 1, color: '#9ecae1', label: '+0,3 … +1' },
    { max: 2, color: '#4a98c9', label: '+1 … +2' },
    { max: Infinity, color: '#08519c', label: '> +2' },
];
export function rateColor(lrr) {
    if (lrr === null || lrr === undefined || Number.isNaN(Number(lrr))) return '#9e9e9e';
    return RATE_STOPS.find((s) => Number(lrr) < s.max)?.color ?? RATE_STOPS[RATE_STOPS.length - 1].color;
}

/** Từ zoom này mới hiện chấm từng transect; thu nhỏ hơn chỉ còn dải màu để không che đường bờ. */
const DOT_MIN_ZOOM = 16;
/** Hai transect liền kề cách nhau quá xa (đứt đoạn) thì không nối dải màu. */
const MAX_LINK_M = 150;
export const SHORELINE_PANE = 'shorelines';

/**
 * Lớp tốc độ biến động (kiểu DEA Coastlines): một DẢI MÀU chạy dọc bờ, mỗi đoạn tô theo LRR của hai transect kề nhau,
 * nằm DƯỚI các đường bờ theo năm (pane riêng) nên không bao giờ che đường bờ. Phóng to (>= zoom 16) mới thêm chấm
 * từng transect; chấm to, viền đậm khi có ý nghĩa thống kê.
 */
function ratePointsLayer(map) {
    const ribbon = L.featureGroup();
    const dots = L.featureGroup();
    const group = L.featureGroup([ribbon]);
    const click = (f) => (e) => {
        L.DomEvent.stopPropagation(e);
        handlers.onTransectClick?.(f, e.latlng);
    };
    const syncDots = () => {
        const want = map.getZoom() >= DOT_MIN_ZOOM && group.hasLayer(ribbon);
        if (want && !group.hasLayer(dots)) group.addLayer(dots);
        if (!want && group.hasLayer(dots)) group.removeLayer(dots);
    };
    map.on('zoomend', syncDots);
    const render = (fc) => {
        const feats = [...fc.features].sort((a, b) => String(a.properties.transect_code).localeCompare(String(b.properties.transect_code)));
        const renderer = L.canvas({ tolerance: 5 });
        feats.forEach((f, i) => {
            const [lng, lat] = f.geometry.coordinates;
            const ll = L.latLng(lat, lng);
            const sig = f.properties.is_significant;
            const r = sig ? 5 + Math.min(Math.abs(f.properties.lrr_m_per_year ?? 0), 3) : 3.5;
            L.circleMarker(ll, {
                renderer, radius: r, fillColor: rateColor(f.properties.lrr_m_per_year), fillOpacity: sig ? 0.95 : 0.75,
                color: '#fff', weight: sig ? 1.5 : 1, opacity: 1,
            }).on('click', click(f)).addTo(dots);
            const next = feats[i + 1];
            if (!next) return;
            const [lng2, lat2] = next.geometry.coordinates;
            const ll2 = L.latLng(lat2, lng2);
            if (ll.distanceTo(ll2) > MAX_LINK_M) return;
            const lrr = ((f.properties.lrr_m_per_year ?? 0) + (next.properties.lrr_m_per_year ?? 0)) / 2;
            // Viền trắng mờ bên dưới để dải màu tách khỏi nền ảnh vệ tinh.
            L.polyline([ll, ll2], { renderer, color: '#fff', weight: 16, opacity: 0.55, lineCap: 'round', interactive: false }).addTo(ribbon);
            L.polyline([ll, ll2], { renderer, color: rateColor(lrr), weight: 12, opacity: 0.95, lineCap: 'round', lineJoin: 'round' })
                .on('click', click(f)).addTo(ribbon);
        });
        syncDots();
    };
    api.ratePoints().then(render).catch(() => setStatus(`Dữ liệu tốc độ biến động: ${UPDATING.toLowerCase()}.`));
    // Phục vụ kiểm thử/áp độ trong suốt: gom các lớp con.
    group.ribbon = ribbon;
    group.dots = dots;
    group.on('add', syncDots);
    return group;
}

/** Trạng thái các lớp, để bật/tắt và để kiểm thử truy cập. */
export const layers = {
    transects: null,
    rates: null,
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

let mapRef = null;

export function initLayers(map) {
    mapRef = map;
    // Đường bờ theo năm nằm trên dải màu tốc độ để luôn nhìn thấy, kể cả khi thu nhỏ.
    map.createPane(SHORELINE_PANE).style.zIndex = 450;
    // Transect dày đặc trông như mã vạch: mặc định tắt, thay bằng điểm tốc độ biến động trên đường bờ.
    layers.transects = USE_WMS ? warnOnTileError(wms('transects', { styles: '' })) : geoJsonTransects();
    layers.rates = ratePointsLayer(map).addTo(map);
    layers.hotspots.addTo(map);

    bindToggle('layer-transects', map, () => layers.transects);
    bindToggle('layer-rates', map, () => layers.rates);
    bindSignificanceFilter();
    bindToggle('layer-hotspots', map, () => layers.hotspots);
    bindReference(map);
    initYearList(map);
    // Mặc định hiện toàn bộ đường bờ các năm có dữ liệu để thấy ngay xu hướng dịch chuyển.
    years.available.forEach((y) => {
        const box = document.getElementById(`year-${y}`);
        if (box) { box.checked = true; box.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    initCompare(map);
    initHotspotFilter();
}

function bindToggle(id, map, getLayer) {
    document.getElementById(id).addEventListener('change', (e) => {
        e.target.checked ? getLayer().addTo(map) : map.removeLayer(getLayer());
    });
}

/** "Chỉ transect có ý nghĩa thống kê" — như bộ lọc p-value của DEA Coastlines; tiêu chí |LRR| > t(0,975; n−2)·SE do pipeline tính (phân phối Student t). */
function bindSignificanceFilter() {
    document.getElementById('only-significant').addEventListener('change', (e) => {
        layers.transectFilter = e.target.checked ? 'is_significant=true' : null;
        if (!USE_WMS) {
            layers.transects.setSignificantOnly(e.target.checked);
            return;
        }
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
        label.className = 'year-chip';
        label.innerHTML = `<input type="checkbox" id="year-${year}" data-year="${year}">${year}<span class="year-swatch" style="background:${YEAR_COLORS[i % YEAR_COLORS.length]}"></span>`;
        if (!hasData(year)) {
            label.classList.add('is-empty');
            label.title = `Năm ${year}: ${UPDATING.toLowerCase()}`;
        }
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
    document.querySelectorAll('[data-compare]').forEach((el) => {
        el.style.background = COMPARE_COLORS[el.dataset.compare];
    });

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
    const drawn = [];
    L.geoJSON(fc, {
        style: (f) => ({ color: colors.severe_erosion, weight: 3 + Math.min(Number(f.properties.severity) || 0, 8), opacity: 0.85 }),
        onEachFeature: (f, layer) => layer.bindPopup(`<strong>${f.properties.notes ?? 'Hotspot'}</strong><br>Tốc độ lùi bờ: ${f.properties.severity} m/năm`),
    }).eachLayer((l) => {
        drawn.push(l);
        layers.hotspots.addLayer(l);
    });

    const count = `${fc.features.length} đoạn từ ${minRate ?? 0} m/năm`;
    document.getElementById('hotspot-count').textContent = fc.features.length ? count : `${count} — ${UPDATING.toLowerCase()}.`;
    const list = document.getElementById('hotspot-list');
    list.innerHTML = fc.features
        .map((f, i) => `<li data-severity="${f.properties.severity}"><button type="button" data-index="${i}">
            <span>${f.properties.notes ?? 'Hotspot'}</span><span class="rate num">${f.properties.severity} m/năm</span></button></li>`)
        .join('');
    // Bấm một đoạn trong danh sách → phóng tới và mở popup trên bản đồ.
    list.querySelectorAll('button').forEach((btn) => btn.addEventListener('click', () => {
        const layer = drawn[Number(btn.dataset.index)];
        if (!layer || !mapRef) return;
        if (!mapRef.hasLayer(layers.hotspots)) {
            layers.hotspots.addTo(mapRef);
            document.getElementById('layer-hotspots').checked = true;
        }
        if (layer.getBounds) mapRef.fitBounds(layer.getBounds(), { maxZoom: 16, padding: [40, 40] });
        else mapRef.setView(layer.getLatLng(), 16);
        layer.openPopup();
    }));
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
    // Kéo/zoom liên tiếp chỉ gọi API một lần sau khi dừng 250 ms.
    let timer = null;
    map.on('moveend', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 250);
    });
}
