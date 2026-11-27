const STORAGE_KEY = 'webgis_tab';

/** Tab trong sidebar theo mẫu ARIA tablist: bấm hoặc dùng phím ←/→ để chuyển. */
export function initTabs(root = document) {
    const tabs = [...root.querySelectorAll('[role=tab]')];
    if (!tabs.length) return null;

    // Gạch chân trượt: một thanh duy nhất chạy tới tab đang chọn (chỉ khi tab nằm trong .rail).
    const rail = tabs[0].closest('.rail');
    const moveIndicator = () => {
        const on = tabs.find((t) => t.getAttribute('aria-selected') === 'true');
        if (!rail || !on || !on.offsetWidth) return;
        rail.style.setProperty('--ind-x', `${on.offsetLeft}px`);
        rail.style.setProperty('--ind-w', `${on.offsetWidth}px`);
        rail.classList.add('has-indicator');
    };

    const select = (tab, focus = false) => {
        tabs.forEach((t) => {
            const on = t === tab;
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
            document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
        moveIndicator();
        if (focus) tab.focus();
        try {
            localStorage.setItem(STORAGE_KEY, tab.dataset.tab);
        } catch {
            /* trình duyệt chặn storage: bỏ qua */
        }
    };

    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', (e) => {
            const step = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
            if (!step) return;
            e.preventDefault();
            select(tabs[(i + step + tabs.length) % tabs.length], true);
        });
    });

    let saved = null;
    try {
        saved = localStorage.getItem(STORAGE_KEY);
    } catch {
        /* bỏ qua */
    }
    select(tabs.find((t) => t.dataset.tab === saved) ?? tabs[0]);
    window.addEventListener('resize', moveIndicator);
    return { select: (name) => select(tabs.find((t) => t.dataset.tab === name)) };
}
