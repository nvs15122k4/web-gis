import L from 'leaflet';

const STORAGE_KEY = 'webgis_basemap';
/**
 * Pane riêng dưới tilePane: nền được thêm sau cùng (tải bất đồng bộ) mà vẫn nằm dưới các lớp WMS chuyên đề.
 * Chung pane mặc định thì lớp thêm sau đè lên, che mất transect/đường bờ.
 */
export const BASEMAP_PANE = 'basemap';

// Ẩn điểm quan tâm (chợ, cửa hàng…) để không lấn át lớp chuyên đề.
const HIDE_POI = encodeURIComponent('s.t:2|p.v:off');

/** Nền Google phiên bản Việt Nam (gl=VN, hl=vi): nhãn tiếng Việt, ghi "quần đảo Hoàng Sa/Trường Sa", không vẽ "đường lưỡi bò". */
const google = (lyrs) => L.tileLayer(`https://mt{s}.google.com/vt/lyrs=${lyrs}&hl=vi&gl=VN&x={x}&y={y}&z={z}&apistyle=${HIDE_POI}`, {
    attribution: 'Bản đồ &copy; Google',
    subdomains: '0123',
    maxZoom: 20,
    pane: BASEMAP_PANE,
});

const esri = (service, attribution) => L.tileLayer(`https://server.arcgisonline.com/ArcGIS/rest/services/${service}/MapServer/tile/{z}/{y}/{x}`, {
    attribution: `Tiles &copy; Esri &mdash; ${attribution}`,
    maxNativeZoom: 19,
    maxZoom: 20,
    pane: BASEMAP_PANE,
});

/**
 * Nền dự phòng khi chưa cấu hình VIETMAP_API_KEY. Không dùng OpenStreetMap/CARTO vì ở mức thu nhỏ chúng vẽ
 * ranh giới yêu sách "đường lưỡi bò" và tên chữ Hán trên Biển Đông.
 */
const FALLBACK = {
    'Bản đồ đường (Google)': () => google('m'),
    'Ảnh vệ tinh + nhãn (Google)': () => google('y'),
};

const ESRI_IMAGERY = { 'Ảnh vệ tinh (Esri)': () => esri('World_Imagery', 'Esri, Maxar, Earthstar Geographics') };

/** Có API key thì dùng nền VIETMAP (tải thư viện GL theo nhu cầu); lỗi tải thì quay về nền dự phòng. */
async function basemapDefs(vietmapKey) {
    if (vietmapKey) {
        try {
            const { vietmapBasemaps } = await import('./vietmap');
            return { ...vietmapBasemaps(vietmapKey), ...ESRI_IMAGERY };
        } catch (e) {
            console.warn('Không tải được nền VIETMAP, dùng nền dự phòng.', e);
        }
    }
    return { ...FALLBACK, ...ESRI_IMAGERY };
}

function remembered() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

export async function initBasemaps(map, vietmapKey = null) {
    map.createPane(BASEMAP_PANE).style.zIndex = 150;
    L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
    initCoordinates(map);

    const defs = await basemapDefs(vietmapKey);
    const layers = Object.fromEntries(Object.entries(defs).map(([name, make]) => [name, make()]));
    const names = Object.keys(layers);
    const current = names.includes(remembered()) ? remembered() : names[0];
    layers[current].addTo(map);

    L.control.layers(layers, null, { position: 'topright', collapsed: true }).addTo(map);
    map.on('baselayerchange', (e) => {
        try {
            localStorage.setItem(STORAGE_KEY, e.name);
        } catch {
            /* trình duyệt chặn storage: bỏ qua */
        }
    });
}

/** Toạ độ con trỏ (WGS84) ở góc dưới phải. */
function initCoordinates(map) {
    const Coords = L.Control.extend({
        options: { position: 'bottomright' },
        onAdd() {
            const el = L.DomUtil.create('div', 'leaflet-control coords');
            el.id = 'cursor-coords';
            map.on('mousemove', (e) => {
                el.textContent = `${e.latlng.lat.toFixed(5)}°N, ${e.latlng.lng.toFixed(5)}°E`;
            });
            map.on('mouseout', () => {
                el.textContent = '';
            });
            return el;
        },
    });
    new Coords().addTo(map);
}
