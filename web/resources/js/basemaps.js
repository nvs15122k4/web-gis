import L from 'leaflet';
import { initSovereignty, addVietnamButton, ARCHIPELAGOS } from './sovereignty';

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

/** Nền công khai dự phòng khi không có GeoServer và không có khoá VIETMAP. */
const OSM = {
    'OpenStreetMap': () => L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>',
        maxZoom: 19,
        pane: BASEMAP_PANE,
    }),
};

/**
 * Nền vector offline (không cần GeoServer, không cần khoá): Natural Earth 1:10m bản quan điểm Việt Nam, đã cắt khu vực
 * Đông Nam Á (public/data/basemap_vn.geojson). Hoàng Sa, Trường Sa là lãnh thổ Việt Nam, không có "đường lưỡi bò".
 */
const DETAIL_MIN_ZOOM = 8;
/** Pane tile chi tiết nằm trên pane vector (SVG cùng pane sẽ che ảnh tile). */
const DETAIL_PANE = 'basemap-detail';

/**
 * Nền lai: vector Việt Nam luôn nằm dưới; từ zoom 8 trở lên, khi khung nhìn không chạm vùng Hoàng Sa, Trường Sa,
 * chồng thêm tile OpenStreetMap để có sông, đường, địa danh. Khung nhìn có hai quần đảo thì chỉ dùng nền vector + nhãn chủ quyền.
 */
const PROVINCE_STYLE = { color: '#b59a5b', weight: 0.8, dashArray: '3 3', fill: false };
const LAKE_STYLE = { color: '#7fb0cf', weight: 0.6, fillColor: '#bcd9ea', fillOpacity: 1 };
const RIVER_COLOR = '#7fb0cf';
/** Nhãn tỉnh hiện từ zoom này; nhãn sông, hồ hiện từ zoom 7. */
const PROVINCE_LABEL_ZOOM = 6;
const WATER_LABEL_ZOOM = 7;

/** Tạo nhãn chữ (tên tỉnh, sông, hồ) lấy từ thuộc tính ten/lat/lng của dữ liệu GeoJSON; trả về các nhóm theo ngưỡng zoom. */
function nameLabels(provinces, rivers, lakes) {
    const mk = (fc, cls) => L.layerGroup((fc?.features ?? [])
        .filter((f) => f.properties?.ten && f.properties.lat != null)
        .map((f) => L.marker([f.properties.lat, f.properties.lng], {
            pane: BASEMAP_PANE,
            interactive: false,
            keyboard: false,
            icon: L.divIcon({ className: cls, html: `<span>${f.properties.ten}</span>`, iconSize: [0, 0] }),
        })));
    return [
        { minZoom: PROVINCE_LABEL_ZOOM, layer: mk(provinces, 'vn-label vn-label-province') },
        { minZoom: WATER_LABEL_ZOOM, layer: L.layerGroup([mk(rivers, 'vn-label vn-label-water'), mk(lakes, 'vn-label vn-label-water')]) },
    ];
}

const hybridClass = () => L.LayerGroup.extend({
    onAdd(map) {
        L.LayerGroup.prototype.onAdd.call(this, map);
        this._sync = () => this.syncDetail();
        map.on('moveend zoomend', this._sync);
        this.syncDetail();
    },
    onRemove(map) {
        map.off('moveend zoomend', this._sync);
        L.LayerGroup.prototype.onRemove.call(this, map);
    },
    syncDetail() {
        const map = this._map;
        if (!map) return;
        const nearIslands = ARCHIPELAGOS.some((a) => map.getBounds().intersects(a.bounds));
        const want = map.getZoom() >= DETAIL_MIN_ZOOM && !nearIslands;
        const has = this.hasLayer(this.detail);
        if (want && !has) this.addLayer(this.detail);
        if (!want && has) this.removeLayer(this.detail);
        (this.labels ?? []).forEach(({ minZoom, layer }) => {
            const show = map.getZoom() >= minZoom;
            if (show && !this.hasLayer(layer)) this.addLayer(layer);
            if (!show && this.hasLayer(layer)) this.removeLayer(layer);
        });
    },
});

function vectorVietnam() {
    const group = new (hybridClass())();
    group.options = { attribution: 'Nền: <a href="https://www.naturalearthdata.com" rel="noopener">Natural Earth</a> (quan điểm Việt Nam), '
        + '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener">OpenStreetMap</a>' };
    group.detail = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, pane: DETAIL_PANE });
    const load = (url) => fetch(url).then((r) => r.json());
    // Chỉ tải ~750 KB GeoJSON khi nền này thật sự được bật lần đầu (không tải khi người dùng đang dùng nền khác).
    // Tải đồng thời rồi thêm theo thứ tự: nước -> tỉnh -> sông/hồ -> nhãn, để lớp sau luôn nằm trên lớp trước.
    const start = () => Promise.all([
        load('/data/basemap_vn.geojson'),
        load('/data/tinh_vn.geojson').catch(() => null),
        load('/data/ho_vn.geojson').catch(() => null),
        load('/data/song_vn.geojson').catch(() => null),
    ])
        .then(([countries, provinces, lakes, rivers]) => {
            const opts = { pane: BASEMAP_PANE, interactive: false };
            L.geoJSON(countries, {
                ...opts,
                style: (f) => (f.properties.NAME_EN === 'Vietnam'
                    ? { color: '#8a6d3b', weight: 0.8, fillColor: '#f1e9cf', fillOpacity: 1 }
                    : { color: '#9aa5ad', weight: 0.6, fillColor: '#e4e6e1', fillOpacity: 1 }),
            }).addTo(group);
            if (provinces) L.geoJSON(provinces, { ...opts, style: PROVINCE_STYLE }).addTo(group);
            if (lakes) L.geoJSON(lakes, { ...opts, style: LAKE_STYLE }).addTo(group);
            if (rivers) {
                L.geoJSON(rivers, { ...opts, style: (f) => ({ color: RIVER_COLOR, weight: f.properties.scalerank <= 4 ? 1.6 : 1, opacity: 0.9 }) }).addTo(group);
            }
            const labels = nameLabels(provinces, rivers, lakes);
            group.labels = labels;
            group.syncDetail();
        })
        .catch((e) => console.warn('Không tải được nền vector Việt Nam.', e));
    group.once('add', start);
    return group;
}

const VECTOR_VN = { 'Bản đồ Việt Nam (vector)': vectorVietnam };

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

/**
 * Có GeoServer thì nền tự phục vụ đứng đầu (mặc định); không có thì VIETMAP (nếu có API key) rồi OpenStreetMap + ảnh vệ tinh.
 * VIETMAP tải thư viện GL theo nhu cầu; lỗi tải thì rơi về nền còn lại.
 */
async function basemapDefs(vietmapKey) {
    const hasGeoServer = Boolean(window.WEBGIS?.geoserver?.url);
    const base = hasGeoServer ? SELF_HOSTED : {};
    const fallback = hasGeoServer ? {} : { ...VECTOR_VN, ...OSM };
    if (vietmapKey) {
        try {
            const { vietmapBasemaps } = await import('./vietmap');
            return { ...base, ...vietmapBasemaps(vietmapKey), ...fallback, ...ESRI_IMAGERY };
        } catch (e) {
            console.warn('Không tải được nền VIETMAP, dùng nền dự phòng và ảnh vệ tinh.', e);
        }
    }
    return { ...base, ...fallback, ...ESRI_IMAGERY };
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
    map.createPane(DETAIL_PANE).style.zIndex = 152;
    L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
    map.getContainer().classList.add('has-sea-bg');
    initCoordinates(map);
    initSovereignty(map);
    addVietnamButton(map);

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
