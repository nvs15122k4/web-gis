/** Phép đo trên mặt cầu cho công cụ đo của thanh công cụ nổi. Điểm vào/ra dạng [vĩ độ, kinh độ]. */
const R = 6371008.8; // bán kính trung bình Trái Đất (m)
const rad = (d) => (d * Math.PI) / 180;

/** Khoảng cách haversine giữa hai điểm, đơn vị mét. */
export function distance(a, b) {
    const dLat = rad(b[0] - a[0]);
    const dLng = rad(b[1] - a[1]);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** Tổng chiều dài đường gấp khúc, mét. */
export function pathLength(points) {
    let total = 0;
    for (let i = 1; i < points.length; i += 1) total += distance(points[i - 1], points[i]);
    return total;
}

/** Diện tích đa giác trên mặt cầu (công thức Chamberlain–Duquette), mét vuông. */
export function polygonArea(points) {
    if (points.length < 3) return 0;
    let sum = 0;
    for (let i = 0; i < points.length; i += 1) {
        const p = points[i];
        const q = points[(i + 1) % points.length];
        sum += rad(q[1] - p[1]) * (2 + Math.sin(rad(p[0])) + Math.sin(rad(q[0])));
    }
    return Math.abs((sum * R * R) / 2);
}

export function formatLength(m) {
    return m < 1000 ? `${Math.round(m)} m` : `${(m / 1000).toFixed(2)} km`;
}

export function formatArea(m2) {
    return m2 < 1e6 ? `${Math.round(m2).toLocaleString('vi-VN')} m²` : `${(m2 / 1e6).toFixed(2)} km² (${(m2 / 1e4).toLocaleString('vi-VN', { maximumFractionDigits: 1 })} ha)`;
}
