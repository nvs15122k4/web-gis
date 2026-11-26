import { layers } from './layers';

/** Áp độ trong suốt (0–1) cho mọi kiểu lớp Leaflet: ô ảnh WMS, nhóm vector, điểm đánh dấu. */
export function applyOpacity(layer, value) {
    if (!layer) return;
    if (layer.setOpacity) layer.setOpacity(value);
    else if (layer.setStyle) layer.setStyle({ opacity: value, fillOpacity: value * 0.7 });
    if (layer.eachLayer) layer.eachLayer((l) => applyOpacity(l, value));
}

/**
 * Cây lớp: mỗi lớp có thanh độ trong suốt và nút đổi thứ tự (đưa lên trên/xuống dưới).
 * Phần tử `[data-layer]` trong panel khai báo khoá lớp ('transects' | 'hotspots' | 'reference').
 */
export function initLayerTree(map, root = document) {
    root.querySelectorAll('[data-layer]').forEach((row) => {
        const key = row.dataset.layer;
        const slider = row.querySelector('input[type=range]');
        const out = row.querySelector('output');
        let opacity = 1;
        const apply = () => applyOpacity(layers[key], opacity);
        slider?.addEventListener('input', () => {
            opacity = Number(slider.value) / 100;
            if (out) out.textContent = `${slider.value}%`;
            apply();
        });
        // Hotspot/sạt lở nạp lại đối tượng khi lọc hoặc kéo bản đồ: giữ nguyên độ trong suốt đã chọn.
        const group = layers[key];
        group?.on?.('layeradd', (e) => opacity !== 1 && applyOpacity(e.layer, opacity));
        row.querySelectorAll('[data-order]').forEach((btn) => btn.addEventListener('click', () => {
            const l = layers[key];
            if (!l || !map.hasLayer(l)) return;
            const up = btn.dataset.order === 'up';
            const bring = up ? 'bringToFront' : 'bringToBack';
            if (l[bring]) l[bring]();
            else if (l.eachLayer) l.eachLayer((x) => x[bring]?.());
        }));
    });
}
