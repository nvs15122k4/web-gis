/**
 * Làm mượt đường viền khi vẽ bằng thuật toán cắt góc Chaikin (chỉ ở phía hiển thị, dữ liệu gốc giữ nguyên).
 * Mỗi đoạn P0-P1 được thay bằng hai điểm Q (1/4) và R (3/4); đường hở giữ nguyên hai đầu mút.
 */

/** Một vòng Chaikin cho dãy tọa độ [lng, lat]; `closed` = vòng khép kín (điểm đầu trùng điểm cuối). */
export function chaikin(coords, closed = false) {
    const pts = closed ? coords.slice(0, -1) : coords;
    if (pts.length < 3) return coords.slice();
    const out = [];
    const n = pts.length;
    const last = closed ? n : n - 1;
    for (let i = 0; i < last; i++) {
        const a = pts[i];
        const b = pts[(i + 1) % n];
        out.push([0.75 * a[0] + 0.25 * b[0], 0.75 * a[1] + 0.25 * b[1]]);
        out.push([0.25 * a[0] + 0.75 * b[0], 0.25 * a[1] + 0.75 * b[1]]);
    }
    if (closed) {
        out.push(out[0].slice());
        return out;
    }
    return [pts[0].slice(), ...out, pts[n - 1].slice()];
}

/** Áp `iterations` vòng Chaikin lên một dãy tọa độ. */
export function smoothCoords(coords, closed = false, iterations = 2) {
    let result = coords;
    for (let i = 0; i < iterations; i++) result = chaikin(result, closed);
    return result;
}

function smoothGeometry(geometry, iterations) {
    if (!geometry) return geometry;
    const { type, coordinates } = geometry;
    switch (type) {
        case 'LineString':
            return { type, coordinates: smoothCoords(coordinates, false, iterations) };
        case 'MultiLineString':
            return { type, coordinates: coordinates.map((l) => smoothCoords(l, false, iterations)) };
        case 'Polygon':
            return { type, coordinates: coordinates.map((r) => smoothCoords(r, true, iterations)) };
        case 'MultiPolygon':
            return { type, coordinates: coordinates.map((p) => p.map((r) => smoothCoords(r, true, iterations))) };
        default:
            return geometry;
    }
}

/** Bản sao FeatureCollection/Feature với hình học đã làm mượt; thuộc tính giữ nguyên. */
export function smoothGeoJSON(data, iterations = 2) {
    if (data?.type === 'FeatureCollection') {
        return { ...data, features: data.features.map((f) => smoothGeoJSON(f, iterations)) };
    }
    if (data?.type === 'Feature') {
        return { ...data, geometry: smoothGeometry(data.geometry, iterations) };
    }
    return smoothGeometry(data, iterations);
}
