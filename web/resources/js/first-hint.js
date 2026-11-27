export const HINT_KEY = 'webgis_hint_seen';

/** Gợi ý lần đầu: hiện một lần, bấm "Đã hiểu" thì nhớ trong localStorage (không có storage thì hiện mỗi lần). */
export function initFirstHint(root = document) {
    const box = root.getElementById ? root.getElementById('first-hint') : root.querySelector('#first-hint');
    if (!box) return null;
    let seen = false;
    try {
        seen = localStorage.getItem(HINT_KEY) === '1';
    } catch {
        /* storage bị chặn: coi như chưa xem */
    }
    if (seen) return box;
    box.hidden = false;
    box.querySelector('#first-hint-close')?.addEventListener('click', () => {
        box.hidden = true;
        try {
            localStorage.setItem(HINT_KEY, '1');
        } catch {
            /* bỏ qua */
        }
    });
    return box;
}
