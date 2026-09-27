import L from 'leaflet';
import '@vietmap/vietmap-gl-js/dist/vietmap-gl.css';
import '@vietmap/vietmap-gl-leaflet';

const ATTRIBUTION = 'Bản đồ &copy; <a href="https://maps.vietmap.vn" rel="noopener">VIETMAP</a>';

/**
 * Nền VIETMAP (bản đồ Việt Nam, thể hiện đúng chủ quyền Hoàng Sa, Trường Sa).
 * Bản đồ đường là vector (style.json) vẽ qua plugin @vietmap/vietmap-gl-leaflet; ảnh vệ tinh là tile raster.
 * Tài liệu: https://maps.vietmap.vn/docs/map-api/tilemap-leaflet/
 */
export function vietmapBasemaps(key) {
    const apikey = encodeURIComponent(key);
    const vector = (style) => () => L.vietmapGL({
        style: `https://maps.vietmap.vn/maps/styles/${style}/style.json?apikey=${apikey}`,
        attribution: ATTRIBUTION,
    });
    return {
        'Bản đồ đường (Vietmap)': vector('tm'),
        'Nền sáng (Vietmap)': vector('lm'),
        'Ảnh vệ tinh (Vietmap)': () => L.tileLayer(`https://maps.vietmap.vn/maps/tiles/st/{z}/{x}/{y}.png?apikey=${apikey}`, {
            attribution: ATTRIBUTION,
            maxZoom: 20,
        }),
    };
}
