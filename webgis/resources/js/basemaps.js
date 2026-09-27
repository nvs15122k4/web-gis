import L from 'leaflet';

const STORAGE_KEY = 'webgis_basemap';

const esri = (service, attribution) => L.tileLayer(`https://server.arcgisonline.com/ArcGIS/rest/services/${service}/MapServer/tile/{z}/{y}/{x}`, {
    attribution: `Tiles &copy; Esri &mdash; ${attribution}`,
    maxNativeZoom: 19,
    maxZoom: 20,
});

/**
 * Nền bản đồ chọn được (như DEA Maps/USGS): bản đồ đường, địa hình, ảnh vệ tinh.
 * Không dùng nền OpenStreetMap/CARTO vì ở mức thu nhỏ chúng vẽ ranh giới yêu sách "đường lưỡi bò"
 * và tên chữ Hán trên Biển Đông; các nền Esri dưới đây không vẽ ranh giới đó.
 */
const BASEMAPS = {
    'Bản đồ đường (Esri)': () => esri('World_Street_Map', 'Esri, HERE, Garmin, USGS, NGA'),
    'Bản đồ địa hình (Esri)': () => esri('World_Topo_Map', 'Esri, HERE, Garmin, FAO, NOAA, USGS'),
    'Ảnh vệ tinh (Esri)': () => esri('World_Imagery', 'Esri, Maxar, Earthstar Geographics'),
};

function remembered() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

export function initBasemaps(map) {
    const layers = Object.fromEntries(Object.entries(BASEMAPS).map(([name, make]) => [name, make()]));
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

    L.control.scale({ imperial: false, position: 'bottomleft' }).addTo(map);
    initCoordinates(map);
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
