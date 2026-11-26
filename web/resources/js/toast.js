/** Thông báo nổi ngắn (toast) ở góc dưới: tự đóng, có hoạt ảnh vào/ra bằng CSS, đọc được bằng trình đọc màn hình. */
import { reducedMotion } from './motion.js';

const HOLD_MS = 3200;

function region() {
    let box = document.getElementById('toasts');
    if (!box) {
        box = document.createElement('div');
        box.id = 'toasts';
        box.className = 'toasts';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        document.body.append(box);
    }
    return box;
}

/** Hiện một toast; `kind` là 'ok' | 'error' | 'info'. Trả về hàm đóng sớm. */
export function showToast(text, { kind = 'info', duration = HOLD_MS } = {}) {
    if (!text) return () => {};
    const el = document.createElement('div');
    el.className = `toast toast-${kind}`;
    el.textContent = text;
    region().append(el);

    let timer;
    const close = () => {
        clearTimeout(timer);
        if (!el.isConnected) return;
        if (reducedMotion()) { el.remove(); return; }
        el.classList.add('is-closing');
        const drop = () => el.remove();
        el.addEventListener('animationend', drop, { once: true });
        setTimeout(drop, 400); // phòng khi animationend không bắn
    };
    timer = setTimeout(close, duration);
    el.addEventListener('click', close);
    return close;
}
