import L from 'leaflet';
import colors from './colors.json';
import { api, get } from './api';
import { createAttrTable } from './attr-table';
import { UPDATING } from './status';
import { initBasemaps } from './basemaps';

const LABELS = { shorelines: 'Đường bờ (đối tượng)', transects: 'Transect', hotspots: 'Hotspot', reference_events: 'Sạt lở tham chiếu' };

/** Bảng "Dữ liệu trong hệ thống": số bản ghi mỗi lớp lấy từ /api/status; không có số thì ghi "Đang cập nhật". */
export function statusRows(status) {
    const rows = Object.entries(LABELS).map(([k, label]) => {
        const n = status?.data?.[k];
        return [label, n === null || n === undefined ? UPDATING : String(n)];
    });
    const flag = (v) => (v === null || v === undefined ? UPDATING : v ? 'Hoạt động' : 'Không kết nối');
    rows.push(['MySQL', flag(status?.mysql)], ['PostGIS', flag(status?.postgis)], ['GeoServer', status?.geoserver === null ? 'Chế độ GeoJSON' : flag(status?.geoserver)]);
    return rows;
}

async function renderStatus() {
    const el = document.getElementById('admin-status');
    if (!el) return;
    let status = null;
    try {
        status = await get('/status');
    } catch { /* hiện "Đang cập nhật" */ }
    el.innerHTML = statusRows(status).map(([k, v]) => `<dt>${k}</dt><dd>${v}</dd>`).join('');
}

export function initAdminMap() {
    const el = document.getElementById('admin-map');
    if (!el) return null;
    const map = L.map(el, { zoomControl: true }).setView([10.545, 105.29], 12);
    initBasemaps(map, window.WEBGIS?.vietmapKey ?? null).catch(() => {});

    api.transects().then((fc) => {
        const layer = L.geoJSON(fc, {
            style: (f) => ({ color: colors[f.properties.change_class] ?? '#666', weight: 3, opacity: 0.9 }),
            onEachFeature: (f, l) => l.on('click', () => table.select(f.id, { zoom: false })),
        }).addTo(map);
        if (fc.features.length) map.fitBounds(layer.getBounds(), { padding: [20, 20] });
    }).catch(() => {});

    const table = createAttrTable({ map, root: document.getElementById('attr-table'), onSelect: () => {} });
    document.getElementById('attr-table').hidden = false;
    renderStatus();
    return { map, table };
}

if (document.getElementById('admin-map')) initAdminMap();
