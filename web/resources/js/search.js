import L from 'leaflet';
import { api } from './api';

/** "10.54, 105.29" hoặc "10.54 105.29" → [vĩ độ, kinh độ]; null nếu không phải toạ độ nằm trong Việt Nam. */
export function parseCoords(q) {
    const m = q.trim().match(/^(-?\d+(?:[.,]\d+)?)\s*[,;\s]\s*(-?\d+(?:[.,]\d+)?)$/);
    if (!m) return null;
    const lat = Number(m[1].replace(',', '.'));
    const lng = Number(m[2].replace(',', '.'));
    return lat >= 4 && lat <= 25 && lng >= 100 && lng <= 120 ? [lat, lng] : null;
}

/** Tìm theo mã transect hoặc toạ độ; chỉ trả đối tượng có trong dữ liệu thật. */
export function matchTransects(features, q, limit = 8) {
    const needle = q.trim().toLowerCase();
    if (!needle) return [];
    return features.filter((f) => String(f.properties.transect_code).toLowerCase().includes(needle)).slice(0, limit);
}

/**
 * Ô tìm kiếm nổi trên bản đồ: mã transect (gợi ý) hoặc toạ độ "vĩ độ, kinh độ".
 * onPick(id): gọi khi chọn một transect.
 */
export function initSearch(map, onPick) {
    const form = document.getElementById('map-search');
    if (!form) return;
    const input = form.querySelector('input');
    const list = form.querySelector('.search-results');
    let features = null;
    let marker = null;
    const load = () => (features ? Promise.resolve(features) : api.transects().then((fc) => (features = fc.features)).catch(() => (features = [])));

    const close = () => { list.hidden = true; list.innerHTML = ''; };
    input.addEventListener('input', async () => {
        const fs = matchTransects(await load(), input.value);
        list.innerHTML = fs.map((f) => `<li><button type="button" data-id="${f.id}">${f.properties.transect_code}</button></li>`).join('');
        list.hidden = !fs.length;
    });
    list.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-id]');
        if (!b) return;
        input.value = b.textContent;
        close();
        onPick(Number(b.dataset.id));
    });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const c = parseCoords(input.value);
        if (c) {
            marker?.remove();
            marker = L.circleMarker(c, { radius: 7, color: '#e94b35', weight: 2, fillOpacity: 0.2 }).addTo(map);
            map.setView(c, Math.max(map.getZoom(), 16));
            close();
            return;
        }
        const first = matchTransects(await load(), input.value, 1)[0];
        if (first) { close(); onPick(first.id); } else { list.innerHTML = '<li class="none">Không tìm thấy trong dữ liệu.</li>'; list.hidden = false; }
    });
    document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
}
