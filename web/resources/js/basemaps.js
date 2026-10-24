import L from 'leaflet';

const STORAGE_KEY = 'webgis_basemap';
/**
 * Pane riêng dưới tilePane: nền được thêm sau cùng (tải bất đồng bộ) mà vẫn nằm dưới các lớp WMS chuyên đề.
 * Chung pane mặc định thì lớp thêm sau đè lên, che mất transect/đường bờ.
 */
export const BASEMAP_PANE = 'basemap';

/**
 * Nền tự phục vụ: layer group nen_ban_do trên GeoServer của dự án (geoserver/basemap/build_basemap.py), lấy qua
 * GeoWebCache (cache sẵn, metatile nên nhãn không bị cắt giữa các tile). Dữ liệu Natural Earth bản quan điểm Việt Nam
 * (Hoàng Sa, Trường Sa thuộc Việt Nam, không có "đường lưỡi bò"), 34 tỉnh sau sáp nhập 2025 và OpenStreetMap quanh vùng nghiên cứu.
 */
function selfHosted() {
    const { url, workspace } = window.WEBGIS.geoserver;
    const params = `service=WMTS&request=GetTile&version=1.0.0&layer=${workspace}:nen_ban_do&style=&format=image/png`
        + '&tilematrixset=EPSG:900913&tilematrix=EPSG:900913:{z}&tilerow={y}&tilecol={x}';
    return L.tileLayer(`${url}/gwc/service/wmts?${params}`, {
        attribution: 'Nền: <a href="https://www.naturalearthdata.com" rel="noopener">Natural Earth</a> (quan điểm Việt Nam), '
            + '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>',
        maxZoom: 20,
        pane: BASEMAP_PANE,
    });
}

const SELF_HOSTED = { 'Bản đồ nền (GeoServer)': selfHosted };

const esri = (service, attribution) => L.tileLayer(`https://server.arcgisonline.com/ArcGIS/rest/services/${service}/MapServer/tile/{z}/{y}/{x}`, {
    attribution: `Tiles &copy; Esri &mdash; ${attribution}`,
    maxNativeZoom: 19,
    maxZoom: 20,
    pane: BASEMAP_PANE,
});

/**
 * Chỉ dùng nền thể hiện đúng chủ quyền Hoàng Sa, Trường Sa. Không dùng OpenStreetMap/CARTO (vẽ "đường lưỡi bò", tên chữ Hán
 * trên Biển Đông) và Google (chỉ ghi "quần đảo Hoàng Sa/Trường Sa", "East Sea", không ghi thuộc Việt Nam).
 * Ảnh vệ tinh Esri không có nhãn hay ranh giới.
 */
const ESRI_IMAGERY = { 'Ảnh vệ tinh (Esri)': () => esri('World_Imagery', 'Esri, Maxar, Earthstar Geographics') };

/** Mặc định là nền tự phục vụ; có API key thì thêm nền VIETMAP (tải thư viện GL theo nhu cầu), lỗi tải thì chỉ dùng nền tự phục vụ + ảnh vệ tinh. */
async function basemapDefs(vietmapKey) {
    if (vietmapKey) {
        try {
            const { vietmapBasemaps } = await import('./vietmap');
            return { ...SELF_HOSTED, ...vietmapBasemaps(vietmapKey), ...ESRI_IMAGERY };
        } catch (e) {
            console.warn('Không tải được nền VIETMAP, chỉ dùng nền GeoServer và ảnh vệ tinh.', e);
        }
    }
    return { ...SELF_HOSTED, ...ESRI_IMAGERY };
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
