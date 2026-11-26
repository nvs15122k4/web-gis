import L from 'leaflet';
import { createAttrTable } from './attr-table';
import { initBasemaps } from './basemaps';
import { initGraticule } from './graticule';
import { initLayers, layers } from './layers';
import { initLayerTree } from './layer-tree';
import { applyLayerSettings } from './layer-settings';
import { initPermalink, readPermalink } from './permalink';
import { initPrint } from './print';
import { initSearch } from './search';
import { smoothGeoJSON } from './smooth';
import { initSwipe } from './swipe';
import { loadSummary } from './summary';
import { initTabs } from './tabs';
import { initTimeline } from './timeline';
import { dismissPanel, revealPanel } from './motion';
import { initToolbar } from './toolbar';
import { initTransectPanel, showTransect, zoomToTransect } from './transect-panel';

const DEFAULT_CENTER = [10.545, 105.29];
const DEFAULT_ZOOM = 13;
/** Thu nhỏ tối đa ra toàn Việt Nam (nền ghi Hoàng Sa, Trường Sa, Biển Đông). */
const MIN_ZOOM = 4;
/** Cho kéo tới cả Hoàng Sa, Trường Sa (Việt Nam) nhưng không ra khỏi khu vực Biển Đông. */
const VIET_NAM = L.latLngBounds([4.5, 100.0], [24.5, 119.5]);

export function initMap() {
    const link = readPermalink();
    const map = L.map('map', { zoomControl: false, minZoom: MIN_ZOOM, maxBounds: VIET_NAM, maxBoundsViscosity: 1 })
        .setView(link.view?.center ?? DEFAULT_CENTER, link.view?.zoom ?? DEFAULT_ZOOM);

    initBasemaps(map, window.WEBGIS.vietmapKey);

    // Người dùng (hoặc liên kết chia sẻ) đã chọn khung nhìn thì AOI tải xong sau cũng không kéo bản đồ về.
    let moved = Boolean(link.view);
    map.once('movestart', () => {
        moved = true;
    });

    // AOI do pipeline sinh (pipeline/s6_reference.py): ranh giới xã + vùng nghiên cứu dọc bờ sông.
    fetch('/data/aoi.json')
        .then((r) => r.json())
        .then((aoi) => {
            // Chỉ làm mượt phần vẽ (Chaikin 2 vòng, nét tròn đầu); khung nhìn vẫn tính từ dữ liệu gốc.
            const layer = L.geoJSON(smoothGeoJSON(aoi, 2), {
                style: (f) => (f.properties.role === 'commune'
                    ? { color: '#555', weight: 1.5, dashArray: '2 5', fill: false, lineCap: 'round', lineJoin: 'round' }
                    : { color: '#333', weight: 2, dashArray: '6 4', fill: false, lineCap: 'round', lineJoin: 'round' }),
                interactive: false,
            }).addTo(map);
            const study = aoi.features.filter((f) => f.properties.role !== 'commune');
            const bounds = L.geoJSON(study.length ? { type: 'FeatureCollection', features: study } : aoi).getBounds();
            // Không animate: hiệu ứng zoom dở dang sẽ ghi đè khung nhìn nếu người dùng di chuyển ngay sau đó.
            if (!moved) map.fitBounds(bounds, { animate: false });
            addHomeControl(map, bounds);
        })
        .catch(() => {});

    const permalink = initPermalink(map);
    initLayers(map);
    initTransectPanel(map, permalink);
    initTabs(document.getElementById('sidebar'));
    initTimeline(map);
    initToolbar(map);
    initGraticule(map);
    initLayerTree(map);
    applyLayerSettings(map);
    initPrint();
    initSwipe(map, window.WEBGIS.years);
    initAttrTable(map);
    const sidebar = initSidebarToggle(map);

    const select = (id) => {
        sidebar.collapseOnMobile(); // màn hình nhỏ: nhường chỗ cho bản đồ và thẻ chi tiết
        return showTransect(id).then(() => zoomToTransect(map, id)).catch(() => {});
    };
    initSearch(map, select);
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
        // Thanh bên trượt ra ngoài màn hình trên điện thoại: chặn Tab/trình đọc màn hình vào phần đang ẩn.
        sidebar.inert = collapsed && mobile.matches;
    };
    if (mobile.matches) {
        setCollapsed(true);
        document.getElementById('legend')?.removeAttribute('open');
    }
    toggle.addEventListener('click', () => setCollapsed(!sidebar.classList.contains('collapsed')));
    map.invalidateSize();
    // Thanh bên trượt mượt nên kích thước bản đồ đổi liên tục: báo cho Leaflet để lớp nền không bị lệch.
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(map.getContainer());
    return {
        collapseOnMobile: () => mobile.matches && setCollapsed(true),
    };
}

/** Nút ⌂ dưới nút phóng to/thu nhỏ: quay về khung ranh giới khu vực nghiên cứu sau khi phóng to/thu nhỏ. */
function addHomeControl(map, bounds) {
    const Home = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            const bar = L.DomUtil.create('div', 'leaflet-bar');
            const a = L.DomUtil.create('a', 'home-control', bar);
            a.href = '#';
            a.role = 'button';
            a.title = 'Về khu vực nghiên cứu';
            a.setAttribute('aria-label', a.title);
            a.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/></svg>';
            L.DomEvent.disableClickPropagation(bar);
            L.DomEvent.on(a, 'click', (e) => {
                L.DomEvent.preventDefault(e);
                map.fitBounds(bounds);
            });
            return bar;
        },
    });
    new Home().addTo(map);
}

/** Bảng thuộc tính đáy bản đồ: mở/đóng bằng nút trên bản đồ; chọn dòng ↔ chọn transect trên bản đồ. */
function initAttrTable(map) {
    const root = document.getElementById('attr-table');
    const toggle = document.getElementById('attr-toggle');
    if (!root || !toggle) return;
    const table = createAttrTable({
        map,
        root,
        onSelect: (id) => showTransect(id).catch(() => {}),
    });
    const set = (open) => {
        if (open) revealPanel(root); else dismissPanel(root);
        toggle.setAttribute('aria-pressed', String(open));
        document.getElementById('main-content').classList.toggle('has-attr', open);
        map.invalidateSize({ pan: false });
        if (open) table.render();
    };
    toggle.addEventListener('click', () => set(root.hidden || root.classList.contains('is-closing')));
    document.getElementById('attr-close')?.addEventListener('click', () => set(false));
    return table;
}
