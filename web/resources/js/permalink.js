/**
 * Liên kết chia sẻ (như nút "Share" của USGS Coastal Change Hazards Portal):
 * trạng thái khung nhìn và transect đang chọn lưu trên URL hash dạng  #map=16/10.57800/105.20800&t=12
 */
export function readPermalink() {
    const params = new URLSearchParams(location.hash.slice(1));
    const [z, lat, lng] = (params.get('map') ?? '').split('/').map(Number);
    const view = [z, lat, lng].every(Number.isFinite) && params.get('map') ? { zoom: z, center: [lat, lng] } : null;
    const t = Number(params.get('t'));
    return { view, transectId: Number.isInteger(t) && t > 0 ? t : null };
}

const state = { transectId: null };

function write(map) {
    const c = map.getCenter();
    const params = new URLSearchParams();
    params.set('map', `${map.getZoom()}/${c.lat.toFixed(5)}/${c.lng.toFixed(5)}`);
    if (state.transectId) params.set('t', String(state.transectId));
    history.replaceState(null, '', `#${params.toString().replaceAll('%2F', '/')}`);
}

export function initPermalink(map) {
    map.on('moveend', () => write(map));
    const button = document.getElementById('share-link');
    const msg = document.getElementById('share-message');
    button?.addEventListener('click', async () => {
        write(map);
        try {
            await navigator.clipboard.writeText(location.href);
            msg.textContent = 'Đã sao chép liên kết.';
        } catch {
            msg.textContent = location.href; // không có quyền clipboard: hiện để người dùng tự chép
        }
    });
    return {
        setTransect(id) {
            state.transectId = id;
            write(map);
        },
    };
}
