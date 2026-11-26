import { api } from './api';
import { layers } from './layers';

const CHECKBOX = { transects: 'layer-transects', rates: 'layer-rates', hotspots: 'layer-hotspots', reference: 'layer-reference' };

/**
 * Áp cấu hình lớp do admin đặt (bật/tắt mặc định, độ trong suốt, thứ tự chồng lớp) lên bản đồ công khai.
 * Không lấy được cấu hình thì giữ mặc định của trang.
 */
export async function applyLayerSettings(map, root = document) {
    let list;
    try {
        list = await api.layerSettings();
    } catch {
        return null;
    }
    for (const s of list) {
        const box = root.getElementById ? root.getElementById(CHECKBOX[s.key]) : root.querySelector(`#${CHECKBOX[s.key]}`);
        if (box && box.checked !== s.visible) {
            box.checked = s.visible;
            box.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const slider = root.querySelector(`[data-layer="${s.key}"] input[type=range]`);
        if (slider && Number(slider.value) !== s.opacity) {
            slider.value = s.opacity;
            slider.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    // Thứ tự: sort_order nhỏ nằm dưới, lớn nằm trên. Lớp nạp dữ liệu muộn thì xếp lại khi đối tượng được thêm vào.
    const order = list.map((s) => s.key);
    const arrange = () => order.forEach((key) => {
        const l = layers[key];
        if (!l || !map.hasLayer(l)) return;
        if (l.bringToFront) l.bringToFront();
        else l.eachLayer?.((x) => x.bringToFront?.());
    });
    let queued = false;
    const later = () => {
        if (queued) return;
        queued = true;
        setTimeout(() => { queued = false; arrange(); }, 300);
    };
    arrange();
    order.forEach((key) => layers[key]?.on?.('layeradd', later));
    return list;
}
