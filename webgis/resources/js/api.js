// Wrapper fetch cho /api/*
async function get(path, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`/api${path}${qs ? `?${qs}` : ''}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`API ${path} → ${res.status}`);
    return res.json();
}

export const api = {
    shorelines: (year) => get('/shorelines', year ? { year } : {}),
    transect: (id) => get(`/transects/${id}`),
    transectHistory: (id) => get(`/transects/${id}/history`),
    hotspots: (minRate) => get('/erosion-hotspots', minRate != null && minRate !== '' ? { min_rate: minRate } : {}),
    referenceEvents: (bbox) => get('/reference-events', bbox ? { bbox } : {}),
    thresholds: () => get('/config/thresholds'),
    transects: () => get('/transects'),
    summary: () => get('/summary'),
};

export { get };
