// Wrapper fetch cho /api/*
//
// Mỗi lần gọi API là một chuyến tới PostGIS qua mạng (chậm, nhất là trên serverless), nên:
//  - yêu cầu GET giống hệt nhau đang bay thì dùng chung một lời hứa (vd. /api/transects bị bảng thuộc tính,
//    tìm kiếm và thẻ chi tiết cùng hỏi);
//  - dữ liệu pipeline ít đổi được nhớ 60 giây trong bộ nhớ trang (khớp max-age=60 của máy chủ), nên bật/tắt lại
//    một năm hay lọc lại cùng ngưỡng hiện ra ngay, không chờ mạng.
const inflight = new Map();
const memo = new Map();
const TTL_MS = 60_000;

const keyOf = (path, params) => `${path}?${new URLSearchParams(params)}`;

async function request(path, params) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`/api${path}${qs ? `?${qs}` : ''}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`API ${path} → ${res.status}`);
    return res.json();
}

/** GET JSON. opts.ttl (ms) > 0: nhớ kết quả; mặc định chỉ gộp các yêu cầu đang chạy cùng lúc. */
async function get(path, params = {}, { ttl = 0 } = {}) {
    const key = keyOf(path, params);
    const hit = memo.get(key);
    if (hit && hit.until > Date.now()) return hit.value;
    if (inflight.has(key)) return inflight.get(key);
    const p = request(path, params)
        .then((value) => {
            if (ttl > 0) memo.set(key, { value, until: Date.now() + ttl });
            return value;
        })
        .finally(() => inflight.delete(key));
    inflight.set(key, p);
    return p;
}

/** Xoá bộ nhớ tạm (kiểm thử, hoặc sau khi dữ liệu vừa đổi). */
export function clearApiCache() {
    memo.clear();
    inflight.clear();
}

const cached = { ttl: TTL_MS };

/**
 * Tải đường bờ của nhiều năm bằng MỘT yêu cầu rồi chia theo thuộc tính `year`, nạp sẵn cho api.shorelines(năm).
 * Khởi động trang bật mọi năm cùng lúc: thay vì N yêu cầu song song tới PostGIS chỉ còn 1.
 */
export function prefetchShorelines(years) {
    const list = [...new Set(years)].filter((y) => !memo.has(keyOf('/shorelines', { year: y })) && !inflight.has(keyOf('/shorelines', { year: y })));
    if (list.length < 2) return Promise.resolve();
    const batch = request('/shorelines', list.map((y) => ['year[]', y]));
    for (const y of list) {
        const key = keyOf('/shorelines', { year: y });
        const p = batch
            .then((fc) => ({ type: 'FeatureCollection', features: fc.features.filter((f) => Number(f.properties?.year) === Number(y)) }))
            .then((value) => {
                memo.set(key, { value, until: Date.now() + TTL_MS });
                return value;
            })
            .finally(() => inflight.delete(key));
        inflight.set(key, p);
        p.catch(() => {}); // lỗi do nơi gọi api.shorelines(năm) tự xử lý
    }
    return batch.then(() => {}, () => {});
}

export const api = {
    shorelines: (year) => get('/shorelines', year ? { year } : {}, cached),
    transect: (id) => get(`/transects/${id}`, {}, cached),
    transectHistory: (id) => get(`/transects/${id}/history`, {}, cached),
    hotspots: (minRate) => get('/erosion-hotspots', minRate != null && minRate !== '' ? { min_rate: minRate } : {}, cached),
    referenceEvents: (bbox) => get('/reference-events', bbox ? { bbox } : {}),
    thresholds: () => get('/config/thresholds'),
    layerSettings: () => get('/config/layers'),
    transects: () => get('/transects', {}, cached),
    yearlyStats: () => get('/yearly-stats', {}, cached),
    ratePoints: () => get('/rate-points', {}, cached),
    summary: () => get('/summary', {}, cached),
};

export { get };
