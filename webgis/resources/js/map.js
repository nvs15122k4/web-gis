import L from 'leaflet';
import { initBasemaps } from './basemaps';
import { initLayers, layers } from './layers';
import { initPermalink, readPermalink } from './permalink';
import { loadSummary } from './summary';
import { initTabs } from './tabs';
import { initTransectPanel, showTransect, zoomToTransect } from './transect-panel';

const DEFAULT_CENTER = [10.578, 105.208];
const DEFAULT_ZOOM = 13;

export function initMap() {
    const link = readPermalink();
    const map = L.map('map').setView(link.view?.center ?? DEFAULT_CENTER, link.view?.zoom ?? DEFAULT_ZOOM);

    initBasemaps(map);

    // Người dùng (hoặc liên kết chia sẻ) đã chọn khung nhìn thì AOI tải xong sau cũng không kéo bản đồ về.
    let moved = Boolean(link.view);
    map.once('movestart', () => {
        moved = true;
    });

    // Ranh giới AOI (khung tạm, xem public/data/aoi.geojson).
    fetch('/data/aoi.geojson')
        .then((r) => r.json())
        .then((aoi) => {
            const layer = L.geoJSON(aoi, { style: { color: '#333', weight: 2, dashArray: '6 4', fill: false }, interactive: false }).addTo(map);
            // Không animate: hiệu ứng zoom dở dang sẽ ghi đè khung nhìn nếu người dùng di chuyển ngay sau đó.
            if (!moved) map.fitBounds(layer.getBounds(), { animate: false });
        })
        .catch(() => {});

    const permalink = initPermalink(map);
    initLayers(map);
    initTransectPanel(map, permalink);
    initTabs(document.getElementById('sidebar'));
    const sidebar = initSidebarToggle(map);

    const select = (id) => {
        sidebar.collapseOnMobile(); // màn hình nhỏ: nhường chỗ cho bản đồ và thẻ chi tiết
        return showTransect(id).then(() => zoomToTransect(map, id)).catch(() => {});
    };
    loadSummary(select);
    if (link.transectId) showTransect(link.transectId).catch(() => {});

    // Điểm truy cập cho kiểm thử E2E.
    window.__webgis = { map, layers, showTransect };
    return map;
}

function initSidebarToggle(map) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebar-toggle');
    const mobile = window.matchMedia('(max-width: 640px)');
    const setCollapsed = (collapsed) => {
        sidebar.classList.toggle('collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));
    };
    if (mobile.matches) {
        setCollapsed(true);
        document.getElementById('legend')?.removeAttribute('open');
    }
    toggle.addEventListener('click', () => setCollapsed(!sidebar.classList.contains('collapsed')));
    map.invalidateSize();
    return {
        collapseOnMobile: () => mobile.matches && setCollapsed(true),
    };
}
