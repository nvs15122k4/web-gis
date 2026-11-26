import { afterEach, describe, expect, it, vi } from 'vitest';
import { showToast } from '../../resources/js/toast.js';
import { initRipple } from '../../resources/js/motion.js';

describe('toast', () => {
    afterEach(() => { document.body.innerHTML = ''; delete window.matchMedia; vi.useRealTimers(); });

    it('tạo vùng thông báo có role=status và thêm toast', () => {
        showToast('Xong', { kind: 'ok' });
        const box = document.getElementById('toasts');
        expect(box.getAttribute('role')).toBe('status');
        expect(box.querySelector('.toast-ok').textContent).toBe('Xong');
    });

    it('không tạo toast khi nội dung rỗng', () => {
        showToast('');
        expect(document.getElementById('toasts')).toBeNull();
    });

    it('giảm chuyển động thì gỡ ngay khi hết giờ', () => {
        vi.useFakeTimers();
        showToast('a', { duration: 100 });
        vi.advanceTimersByTime(110);
        expect(document.querySelector('.toast')).toBeNull();
    });

    it('cho phép chuyển động thì gắn .is-closing rồi gỡ', () => {
        vi.useFakeTimers();
        window.matchMedia = vi.fn().mockReturnValue({ matches: false });
        showToast('a', { duration: 100 });
        vi.advanceTimersByTime(110);
        expect(document.querySelector('.toast').classList.contains('is-closing')).toBe(true);
        vi.advanceTimersByTime(450);
        expect(document.querySelector('.toast')).toBeNull();
    });

    it('bấm vào toast thì đóng sớm', () => {
        vi.useFakeTimers();
        showToast('a');
        document.querySelector('.toast').click();
        expect(document.querySelector('.toast')).toBeNull();
    });
});

describe('ripple', () => {
    afterEach(() => { document.body.innerHTML = ''; delete window.matchMedia; });
    const press = (el) => el.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true, clientX: 5, clientY: 5 }));

    it('thêm .ripple vào nút khi cho phép chuyển động', () => {
        window.matchMedia = vi.fn().mockReturnValue({ matches: false });
        document.body.innerHTML = '<div id="r"><button class="btn">A</button></div>';
        initRipple(document.getElementById('r'));
        press(document.querySelector('.btn'));
        expect(document.querySelectorAll('.btn .ripple')).toHaveLength(1);
    });

    it('bỏ qua khi giảm chuyển động hoặc nút bị vô hiệu hoá', () => {
        document.body.innerHTML = '<div id="r"><button class="btn">A</button><button class="btn" disabled>B</button></div>';
        initRipple(document.getElementById('r'));
        press(document.querySelector('.btn'));
        expect(document.querySelector('.ripple')).toBeNull();
        window.matchMedia = vi.fn().mockReturnValue({ matches: false });
        press(document.querySelector('.btn[disabled]'));
        expect(document.querySelector('.ripple')).toBeNull();
    });
});
