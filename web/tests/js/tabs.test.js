import { beforeEach, describe, expect, it } from 'vitest';
import { initTabs } from '../../resources/js/tabs';

const html = `
<div id="root">
  <button role="tab" data-tab="a" aria-controls="pa"></button>
  <button role="tab" data-tab="b" aria-controls="pb"></button>
  <button role="tab" data-tab="c" aria-controls="pc"></button>
  <div id="pa"></div><div id="pb"></div><div id="pc"></div>
</div>`;

const tab = (name) => document.querySelector(`[data-tab=${name}]`);
const key = (el, k) => el.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }));

describe('initTabs', () => {
    beforeEach(() => {
        localStorage.clear();
        document.body.innerHTML = html;
    });

    it('không có tab nào thì trả về null', () => {
        document.body.innerHTML = '<div></div>';
        expect(initTabs()).toBeNull();
    });

    it('mặc định chọn tab đầu, ẩn các panel còn lại', () => {
        initTabs();
        expect(tab('a').getAttribute('aria-selected')).toBe('true');
        expect(tab('b').getAttribute('aria-selected')).toBe('false');
        expect(document.getElementById('pa').hidden).toBe(false);
        expect(document.getElementById('pb').hidden).toBe(true);
        expect(tab('a').tabIndex).toBe(0);
        expect(tab('b').tabIndex).toBe(-1);
    });

    it('bấm tab chuyển panel và ghi nhớ vào localStorage', () => {
        initTabs();
        tab('b').click();
        expect(document.getElementById('pb').hidden).toBe(false);
        expect(document.getElementById('pa').hidden).toBe(true);
        expect(localStorage.getItem('webgis_tab')).toBe('b');
    });

    it('khôi phục tab đã lưu khi khởi tạo lại', () => {
        localStorage.setItem('webgis_tab', 'c');
        initTabs();
        expect(tab('c').getAttribute('aria-selected')).toBe('true');
    });

    it('tab đã lưu không còn tồn tại thì quay về tab đầu', () => {
        localStorage.setItem('webgis_tab', 'khong-co');
        initTabs();
        expect(tab('a').getAttribute('aria-selected')).toBe('true');
    });

    it('phím ←/→ chuyển tab theo vòng và chuyển focus', () => {
        initTabs();
        key(tab('a'), 'ArrowLeft'); // vòng về cuối
        expect(tab('c').getAttribute('aria-selected')).toBe('true');
        expect(document.activeElement).toBe(tab('c'));
        key(tab('c'), 'ArrowRight'); // vòng về đầu
        expect(tab('a').getAttribute('aria-selected')).toBe('true');
    });

    it('phím khác không đổi tab và không bị chặn mặc định', () => {
        initTabs();
        const ev = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
        tab('a').dispatchEvent(ev);
        expect(ev.defaultPrevented).toBe(false);
        expect(tab('a').getAttribute('aria-selected')).toBe('true');
    });

    it('API select(name) chọn tab theo tên', () => {
        const api = initTabs();
        api.select('c');
        expect(document.getElementById('pc').hidden).toBe(false);
    });

    it('storage bị chặn thì vẫn chuyển tab bình thường', () => {
        const orig = Storage.prototype.setItem;
        Storage.prototype.setItem = () => { throw new Error('blocked'); };
        try {
            initTabs();
            expect(() => tab('b').click()).not.toThrow();
            expect(tab('b').getAttribute('aria-selected')).toBe('true');
        } finally {
            Storage.prototype.setItem = orig;
        }
    });
});
