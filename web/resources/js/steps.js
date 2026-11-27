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

/** Bấm ảnh trong quy trình để xem lớn: hộp thoại <dialog> gốc, Esc hoặc bấm nền/nút Đóng để thoát. Không có JS thì ảnh vẫn xem được như cũ. */
export function initZoom(root = document) {
    const imgs = [...root.querySelectorAll('.step-fig img, .more-fig img')];
    if (!imgs.length) return () => {};
    const dlg = root.createElement('dialog');
    dlg.className = 'img-zoom';
    dlg.setAttribute('aria-label', 'Xem ảnh lớn');
    dlg.innerHTML = '<button type="button" class="img-zoom-close" aria-label="Đóng">×</button><img alt=""><p class="img-zoom-cap"></p>';
    root.body.appendChild(dlg);
    const big = dlg.querySelector('img');
    const cap = dlg.querySelector('.img-zoom-cap');
    const close = () => { if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); };
    const open = (img) => {
        big.src = img.currentSrc || img.src;
        big.alt = img.alt;
        cap.textContent = img.closest('figure')?.querySelector('figcaption')?.textContent ?? '';
        if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
    };
    const handlers = imgs.map((img) => {
        const h = () => open(img);
        img.classList.add('zoomable');
        img.tabIndex = 0;
        img.setAttribute('role', 'button');
        img.addEventListener('click', h);
        const k = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); h(); } };
        img.addEventListener('keydown', k);
        return [img, h, k];
    });
    dlg.addEventListener('click', (e) => { if (e.target !== big) close(); });
    return () => {
        handlers.forEach(([img, h, k]) => { img.removeEventListener('click', h); img.removeEventListener('keydown', k); });
        dlg.remove();
    };
}

if (typeof document !== 'undefined') { initSteps(); initZoom(); }
