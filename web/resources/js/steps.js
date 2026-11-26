/** Trang Phương pháp: đánh dấu bước đang ở giữa màn hình (`is-active`) khi cuộn. Không có JS thì mọi bước vẫn đọc được (CSS chỉ làm mờ khi JS đã chạy). */

/** Chọn bước có tâm gần giữa khung nhìn nhất; trả về phần tử hoặc null. */
export function pickActive(steps, viewportHeight) {
    const mid = viewportHeight / 2;
    let best = null;
    let bestDist = Infinity;
    for (const el of steps) {
        const r = el.getBoundingClientRect();
        if (r.bottom < 0 || r.top > viewportHeight) continue;
        const dist = Math.abs((r.top + r.bottom) / 2 - mid);
        if (dist < bestDist) { bestDist = dist; best = el; }
    }
    return best;
}

export function initSteps(root = document) {
    const steps = [...root.querySelectorAll('.step-list .step')];
    if (!steps.length) return () => {};
    root.documentElement?.classList.add('steps-js');
    let queued = false;
    const update = () => {
        queued = false;
        const active = pickActive(steps, window.innerHeight);
        for (const el of steps) el.classList.toggle('is-active', el === active);
    };
    const onScroll = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    update();
    return () => { window.removeEventListener('scroll', onScroll); window.removeEventListener('resize', onScroll); };
}

if (typeof document !== 'undefined') initSteps();
