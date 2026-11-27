import { beforeEach, describe, expect, it } from 'vitest';
import { HINT_KEY, initFirstHint } from '../../resources/js/first-hint';

const html = '<aside id="first-hint" hidden><button id="first-hint-close"></button></aside>';

describe('initFirstHint', () => {
    beforeEach(() => {
        localStorage.clear();
        document.body.innerHTML = html;
    });

    it('hiện gợi ý ở lần đầu', () => {
        initFirstHint();
        expect(document.getElementById('first-hint').hidden).toBe(false);
    });

    it('bấm Đã hiểu thì ẩn và nhớ', () => {
        initFirstHint();
        document.getElementById('first-hint-close').click();
        expect(document.getElementById('first-hint').hidden).toBe(true);
        expect(localStorage.getItem(HINT_KEY)).toBe('1');
    });

    it('không hiện lại khi đã xem', () => {
        localStorage.setItem(HINT_KEY, '1');
        initFirstHint();
        expect(document.getElementById('first-hint').hidden).toBe(true);
    });

    it('không có phần tử gợi ý thì trả null', () => {
        document.body.innerHTML = '';
        expect(initFirstHint()).toBeNull();
    });
});
