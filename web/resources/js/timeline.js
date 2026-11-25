/**
 * Thanh thời gian (kiểu Global Fishing Watch): mỗi năm là một mốc, bấm để bật/tắt đường bờ năm đó.
 * Không giữ trạng thái riêng: điều khiển hộp kiểm #year-<năm> ở thanh bên (nguồn sự thật duy nhất, do layers.js xử lý)
 * và đồng bộ lại khi hộp kiểm đổi.
 */
const STEP_MS = 1200;

export function initTimeline(map = null) {
    const root = document.getElementById('timeline');
    const ticksEl = document.getElementById('timeline-ticks');
    const list = document.getElementById('year-list');
    const years = window.WEBGIS?.years;
    if (!root || !ticksEl || !list || !years?.all?.length) {
        if (root) root.hidden = true;
        return null;
    }
    const available = new Set(years.available ?? []);
    const box = (y) => document.getElementById(`year-${y}`);
    const color = (y) => box(y)?.parentElement?.querySelector('.year-swatch')?.style.background ?? '';

    ticksEl.innerHTML = years.all.map((y) => `<li><button type="button" class="tick${available.has(y) ? '' : ' is-empty'}" data-year="${y}" aria-pressed="false"
        aria-label="Bật đường bờ năm ${y}"${available.has(y) ? '' : ` title="Năm ${y}: đang cập nhật"`}>
        <i class="tick-mark"></i><span class="tick-year">${y}</span></button></li>`).join('');
    const buttons = [...ticksEl.querySelectorAll('.tick')];

    const sync = () => {
        buttons.forEach((b) => {
            const y = Number(b.dataset.year);
            const on = Boolean(box(y)?.checked);
            b.setAttribute('aria-pressed', String(on));
            b.setAttribute('aria-label', `${on ? 'Tắt' : 'Bật'} đường bờ năm ${y}`);
            b.classList.toggle('is-on', on);
            b.style.setProperty('--tick-color', on ? color(y) : 'transparent');
        });
    };
    const set = (y, on) => {
        const el = box(y);
        if (!el || el.checked === on) return;
        el.checked = on;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    buttons.forEach((b) => b.addEventListener('click', () => {
        stop();
        const y = Number(b.dataset.year);
        set(y, !box(y)?.checked);
    }));
    list.addEventListener('change', sync);

    /* ---- Phát: lần lượt từng năm có dữ liệu, mỗi lúc một năm ---- */
    const play = document.getElementById('timeline-play');
    const label = document.getElementById('timeline-play-label');
    const seq = years.all.filter((y) => available.has(y));
    let timer = null;
    let index = -1;
    const show = (y) => seq.forEach((s) => set(s, s === y));
    function stop() {
        if (timer === null) return;
        clearInterval(timer);
        timer = null;
        play?.setAttribute('aria-pressed', 'false');
        if (label) label.textContent = 'Phát';
    }
    function start() {
        if (!seq.length) return;
        play.setAttribute('aria-pressed', 'true');
        label.textContent = 'Dừng';
        index = -1;
        const step = () => {
            index = (index + 1) % seq.length;
            show(seq[index]);
        };
        step();
        timer = setInterval(step, STEP_MS);
    }
    play?.addEventListener('click', () => (timer === null ? start() : stop()));
    if (!seq.length && play) play.disabled = true;

    sync();
    return { sync, stop, map };
}
