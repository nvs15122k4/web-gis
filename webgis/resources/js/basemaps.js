import L from 'leaflet';

const STORAGE_KEY = 'webgis_basemap';

/** Nền bản đồ chọn được (như DEA Maps/USGS): bản đồ đường, ảnh vệ tinh, nền sáng làm nổi lớp chuyên đề. */
const BASEMAPS = {
    'Bản đồ đường (OSM)': () => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }),
    'Ảnh vệ tinh (Esri)': () => L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Esri, Maxar, Earthstar Geographics',
        maxZoom: 19,
    }),
    'Nền sáng (CARTO)': () => L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
    }),
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
