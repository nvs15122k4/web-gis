import { get } from './api';

export const UPDATING = 'Đang cập nhật';

/** Thông báo trạng thái ngắn ở đầu sidebar (ví dụ "không có dữ liệu"). */
export function setStatus(message = '') {
    const el = document.getElementById('status');
    if (el) el.textContent = message;
}

const LAYER_LABEL = {
    shorelines: 'đường bờ',
    transects: 'transect',
    hotspots: 'hotspot',
    reference_events: 'sạt lở tham chiếu',
};

/**
 * Hỏi /api/status rồi hiện banner liệt kê phần dữ liệu còn "đang cập nhật"
 * (chưa kết nối CSDL, bảng rỗng, hoặc GeoServer chưa chạy).
 */
export async function loadDataStatus() {
    let status;
    try {
        status = await get('/status');
    } catch {
        status = { mysql: false, postgis: false, geoserver: false, data: {} };
    }

    const pending = Object.entries(LAYER_LABEL).filter(([key]) => !status.data?.[key]).map(([, label]) => label);
    const notes = [];
    if (pending.length) notes.push(`Dữ liệu ${pending.join(', ')}: ${UPDATING.toLowerCase()}.`);
    if (!status.geoserver) notes.push(`Lớp bản đồ GeoServer: ${UPDATING.toLowerCase()}.`);

    const banner = document.getElementById('data-banner');
    if (banner) {
        banner.hidden = notes.length === 0;
        banner.innerHTML = notes.map((n) => `<div>${n}</div>`).join('');
    }
    return status;
}
