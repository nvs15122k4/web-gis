import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { countUp, dismissPanel, fadeIn, reducedMotion, revealPanel } from '../../resources/js/motion.js';

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

    it('dismissPanel ẩn ngay và gọi done khi giảm chuyển động', () => {
        mockMotion(true);
        const el = document.createElement('div');
        const done = vi.fn();
        dismissPanel(el, done);
        expect(el.hidden).toBe(true);
        expect(done).toHaveBeenCalledOnce();
    });

    it('dismissPanel gắn .is-closing rồi ẩn khi animationend', () => {
        mockMotion(false);
        const el = document.createElement('div');
        const done = vi.fn();
        dismissPanel(el, done);
        expect(el.classList.contains('is-closing')).toBe(true);
        expect(el.hidden).toBe(false);
        el.dispatchEvent(new Event('animationend'));
        expect(el.hidden).toBe(true);
        expect(el.classList.contains('is-closing')).toBe(false);
        expect(done).toHaveBeenCalledOnce();
    });

    it('dismissPanel tự ẩn bằng timeout khi animationend không bắn', () => {
        mockMotion(false);
        vi.useFakeTimers();
        const el = document.createElement('div');
        dismissPanel(el);
        vi.advanceTimersByTime(450);
        expect(el.hidden).toBe(true);
    });

    it('revealPanel giữa lúc đang đóng thì huỷ lượt đóng', () => {
        mockMotion(false);
        vi.useFakeTimers();
        const el = document.createElement('div');
        const done = vi.fn();
        dismissPanel(el, done);
        revealPanel(el);
        vi.advanceTimersByTime(450);
        expect(el.hidden).toBe(false);
        expect(el.classList.contains('is-closing')).toBe(false);
        expect(done).not.toHaveBeenCalled();
    });

    it('dismissPanel bỏ qua phần tử đã ẩn hoặc thiếu', () => {
        mockMotion(false);
        const el = document.createElement('div');
        el.hidden = true;
        const done = vi.fn();
        dismissPanel(el, done);
        dismissPanel(null, done);
        expect(done).not.toHaveBeenCalled();
    });
});

describe('motion.css', () => {
    const css = readFileSync('resources/css/motion.css', 'utf8');
    const keyframes = [...css.matchAll(/@keyframes (panel|drop|page)[\w-]* \{[^\n]*\}/g)].map((m) => m[0]);

    it('hoạt ảnh panel/trang chỉ đổi opacity và transform (không gây layout)', () => {
        expect(keyframes.length).toBeGreaterThanOrEqual(7);
        for (const k of keyframes) expect(k).not.toMatch(/\b(width|height|top|left|right|bottom|margin|padding)\s*:/);
    });

    it('hoạt ảnh vào/ra của bảng nằm trong khối prefers-reduced-motion: no-preference', () => {
        const rm = css.indexOf('@media (prefers-reduced-motion: no-preference) {');
        for (const sel of ['.inspector {', '.inspector.is-closing', 'body.page main#main-content']) {
            expect(css.indexOf(sel), sel).toBeGreaterThan(rm);
        }
    });
});
