// Wrapper fetch cho /api/*
async function get(path, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`/api${path}${qs ? `?${qs}` : ''}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`API ${path} → ${res.status}`);
    return res.json();
}

export const api = {
    shorelines: (year, source) => get('/shorelines', { ...(year ? { year } : {}), ...(source ? { source } : {}) }),
    altSources: () => get('/alt-sources'),
    transectCompare: (id) => get(`/transects/${id}/compare`),
    transect: (id) => get(`/transects/${id}`),
    transectHistory: (id) => get(`/transects/${id}/history`),
    hotspots: (minRate) => get('/erosion-hotspots', minRate != null && minRate !== '' ? { min_rate: minRate } : {}),
    referenceEvents: (bbox) => get('/reference-events', bbox ? { bbox } : {}),
    thresholds: () => get('/config/thresholds'),
    layerSettings: () => get('/config/layers'),
    transects: () => get('/transects'),
    yearlyStats: () => get('/yearly-stats'),
    ratePoints: () => get('/rate-points'),
    summary: () => get('/summary'),
};

export { get };
