import { afterEach, describe, expect, it, vi } from 'vitest';
import { countUp, fadeIn, reducedMotion } from '../../resources/js/motion.js';

const mockMotion = (reduce) => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: reduce });
};

describe('motion', () => {
    afterEach(() => { delete window.matchMedia; vi.useRealTimers(); });

    it('không có matchMedia (jsdom) thì coi như giảm chuyển động', () => {
        expect(reducedMotion()).toBe(true);
    });

    it('countUp ghi ngay giá trị cuối khi giảm chuyển động', () => {
        mockMotion(true);
        const el = document.createElement('b');
        countUp(el, 7.5, { decimals: 1, suffix: '%' });
        expect(el.textContent).toBe('7.5%');
        expect(el.dataset.value).toBe('7.5%');
    });

    it('countUp bắt đầu từ 0 rồi kết thúc đúng giá trị cuối', async () => {
        mockMotion(false);
        const raf = vi.spyOn(globalThis, 'requestAnimationFrame').mockImplementation((cb) => setTimeout(() => cb(performance.now() + 10000), 0));
        const el = document.createElement('b');
        document.body.append(el);
        countUp(el, 267);
        expect(el.textContent).toBe('0');
        await new Promise((r) => setTimeout(r, 10));
        expect(el.textContent).toBe('267');
        expect(raf).toHaveBeenCalled();
    });

    it('countUp bỏ qua phần tử thiếu hoặc giá trị không phải số', () => {
        mockMotion(false);
        expect(() => countUp(null, 5)).not.toThrow();
        const el = document.createElement('b');
        countUp(el, NaN);
        expect(el.textContent).toBe('NaN');
    });

    it('fadeIn không làm gì khi giảm chuyển động, gọi WAAPI khi được phép', () => {
        const el = document.createElement('div');
        el.animate = vi.fn().mockReturnValue({});
        mockMotion(true);
        expect(fadeIn(el)).toBeNull();
        mockMotion(false);
        fadeIn(el);
        expect(el.animate).toHaveBeenCalledOnce();
    });
});
