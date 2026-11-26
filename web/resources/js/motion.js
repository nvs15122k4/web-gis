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
