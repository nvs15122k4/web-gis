/** Tiện ích chuyển động thuần JS (không thư viện). Mọi hàm đều rơi về trạng thái cuối ngay khi người dùng giảm chuyển động. */

/** True khi cần bỏ hoạt ảnh: người dùng chọn giảm chuyển động, hoặc môi trường không có matchMedia (jsdom). */
export function reducedMotion() {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return true;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

const easeOutExpo = (t) => (t === 1 ? 1 : 1 - 2 ** (-10 * t));

/**
 * Đếm số lên từ 0 đến giá trị cuối trong `duration` ms.
 * Giá trị cuối luôn được ghi vào `data-value` và là nội dung sau khi xong; nếu giảm chuyển động thì ghi ngay.
 */
export function countUp(el, to, { duration = 800, decimals = 0, suffix = '', delay = 0 } = {}) {
    if (!el) return;
    const format = (v) => `${v.toFixed(decimals)}${suffix}`;
    const final = format(Number(to));
    el.dataset.value = final;
    if (!Number.isFinite(Number(to)) || reducedMotion() || duration <= 0) {
        el.textContent = final;
        return;
    }
    el.textContent = format(0);
    const run = () => {
        const t0 = performance.now();
        const frame = (now) => {
            const t = Math.min(1, (now - t0) / duration);
            el.textContent = t >= 1 ? final : format(Number(to) * easeOutExpo(t));
            if (t < 1 && el.isConnected) requestAnimationFrame(frame);
            else el.textContent = final;
        };
        requestAnimationFrame(frame);
    };
    delay > 0 ? setTimeout(run, delay) : run();
}

/** Hiện dần phần tử bằng Web Animations API; bỏ qua khi giảm chuyển động hoặc trình duyệt không hỗ trợ. */
export function fadeIn(el, { duration = 220, from = 8 } = {}) {
    if (!el || reducedMotion() || typeof el.animate !== 'function') return null;
    return el.animate(
        [{ opacity: 0, transform: `translateY(${from}px)` }, { opacity: 1, transform: 'none' }],
        { duration, easing: 'cubic-bezier(.16, 1, .3, 1)', fill: 'backwards' },
    );
}

const closing = new WeakMap();

/** Mở bảng: huỷ lượt đóng đang dở (nếu có) rồi hiện; hoạt ảnh vào do CSS đảm nhiệm khi bỏ thuộc tính hidden. */
export function revealPanel(el) {
    if (!el) return;
    closing.get(el)?.(false);
    el.hidden = false;
}

/**
 * Đóng bảng có hoạt ảnh thoát: gắn `.is-closing` để CSS chạy, xong mới đặt `hidden`.
 * Giảm chuyển động hoặc đã ẩn thì ẩn ngay. Mở lại giữa chừng (revealPanel) sẽ huỷ lượt đóng.
 */
export function dismissPanel(el, done) {
    if (!el) return;
    if (el.hidden || closing.has(el)) return;
    if (reducedMotion()) {
        el.hidden = true;
        done?.();
        return;
    }
    let timer;
    const finish = (commit) => {
        clearTimeout(timer);
        el.removeEventListener('animationend', onEnd);
        el.classList.remove('is-closing');
        closing.delete(el);
        if (commit) {
            el.hidden = true;
            done?.();
        }
    };
    const onEnd = (e) => { if (e.target === el) finish(true); };
    closing.set(el, finish);
    el.classList.add('is-closing');
    el.addEventListener('animationend', onEnd);
    timer = setTimeout(() => finish(true), 400); // phòng khi animationend không bắn (tab ẩn, hoạt ảnh bị tắt)
}

/**
 * Gợi ý nhấn kiểu "ripple": một vòng tròn lan ra từ điểm chạm trên nút.
 * Ủy quyền một lần trên `root`; bỏ qua nút bị vô hiệu hoá, và không làm gì khi giảm chuyển động.
 */
export function initRipple(root = document, selector = '.btn, .tool-btn, .icon-btn') {
    root.addEventListener('pointerdown', (e) => {
        const btn = e.target instanceof Element ? e.target.closest(selector) : null;
        if (!btn || btn.disabled || btn.getAttribute('aria-disabled') === 'true' || reducedMotion()) return;
        const rect = btn.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height) * 2;
        const dot = document.createElement('span');
        dot.className = 'ripple';
        dot.setAttribute('aria-hidden', 'true');
        dot.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX - rect.left - size / 2}px;top:${e.clientY - rect.top - size / 2}px`;
        btn.append(dot);
        const drop = () => dot.remove();
        dot.addEventListener('animationend', drop, { once: true });
        setTimeout(drop, 700);
    });
}
