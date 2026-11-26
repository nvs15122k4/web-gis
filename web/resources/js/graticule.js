/**
 * Lưới toạ độ ở rìa bản đồ (kiểu bản đồ in): nhãn vĩ độ dọc mép trái, kinh độ dọc mép dưới.
 * Chỉ vẽ nhãn và vạch ngắn ở rìa, không vẽ lưới lên bản đồ để không che dữ liệu.
 */

/** Các bước chia hợp lý, tính bằng giây cung. */
const STEPS_SEC = [1, 2, 5, 10, 15, 30, 60, 120, 300, 600, 1200, 1800, 3600, 7200, 18000, 36000];

/** Chọn bước chia (độ) sao cho khoảng [min, max] có xấp xỉ `target` vạch. */
export function niceStep(min, max, target = 5) {
    const raw = ((max - min) * 3600) / Math.max(1, target);
    const sec = STEPS_SEC.find((s) => s >= raw) ?? STEPS_SEC[STEPS_SEC.length - 1];
    return sec / 3600;
}

/** Các giá trị (độ) là bội của bước chia nằm trong [min, max]. */
export function graticuleTicks(min, max, target = 5) {
    if (!(max > min)) return [];
    const stepSec = Math.round(niceStep(min, max, target) * 3600);
    const out = [];
    for (let s = Math.ceil((min * 3600) / stepSec) * stepSec; s <= max * 3600 + 1e-6; s += stepSec) out.push(s / 3600);
    return out;
}

/** "10°32′N", "105°17′24″E": độ, phút (giây khi bước chia nhỏ hơn 1 phút). */
export function formatCoord(value, axis, stepDeg = 1 / 60) {
    const hemi = axis === 'lat' ? (value >= 0 ? 'N' : 'S') : (value >= 0 ? 'E' : 'W');
    const total = Math.round(Math.abs(value) * 3600);
    const d = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const withSeconds = Math.round(stepDeg * 3600) % 60 !== 0;
    return `${d}°${String(m).padStart(2, '0')}′${withSeconds ? `${String(s).padStart(2, '0')}″` : ''}${hemi}`;
}

export function initGraticule(map) {
    const root = document.createElement('div');
    root.className = 'graticule';
    root.setAttribute('aria-hidden', 'true');
    root.innerHTML = '<div class="g-left"></div><div class="g-bottom"></div><div class="g-corner"></div>';
    map.getContainer().appendChild(root);
    const left = root.querySelector('.g-left');
    const bottom = root.querySelector('.g-bottom');

    const render = () => {
        if (!root.offsetParent) return; // đang ẩn (điện thoại)
        const size = map.getSize();
        const b = map.getBounds();
        const cs = getComputedStyle(root);
        const gl = parseFloat(cs.getPropertyValue('--grat-l')) || 40;
        const mid = b.getCenter();
        const latStep = niceStep(b.getSouth(), b.getNorth(), Math.max(2, Math.floor(size.y / 120)));
        const lngStep = niceStep(b.getWest(), b.getEast(), Math.max(2, Math.floor((size.x - gl) / 150)));
        left.innerHTML = graticuleTicks(b.getSouth(), b.getNorth(), Math.max(2, Math.floor(size.y / 120)))
            .map((v) => `<div class="g-lat" style="top:${map.latLngToContainerPoint([v, mid.lng]).y.toFixed(1)}px"><span>${formatCoord(v, 'lat', latStep)}</span></div>`).join('');
        bottom.innerHTML = graticuleTicks(b.getWest(), b.getEast(), Math.max(2, Math.floor((size.x - gl) / 150)))
            .map((v) => `<div class="g-lng" style="left:${(map.latLngToContainerPoint([mid.lat, v]).x - gl).toFixed(1)}px">${formatCoord(v, 'lng', lngStep)}</div>`).join('');
    };
    map.on('move zoom resize', render);
    render();
    return { render };
}
