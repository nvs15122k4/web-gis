import { afterEach, describe, expect, it } from 'vitest';
import { pickActive, initSteps, initZoom } from '../../resources/js/steps.js';

function mk(rects) {
    document.body.innerHTML = '<ol class="step-list">' + rects.map(() => '<li class="step"></li>').join('') + '</ol>';
    const els = [...document.querySelectorAll('.step')];
    els.forEach((el, i) => { el.getBoundingClientRect = () => ({ top: rects[i][0], bottom: rects[i][1] }); });
    return els;
}

describe('steps', () => {
    afterEach(() => { document.body.innerHTML = ''; document.documentElement.className = ''; });

    it('chọn bước có tâm gần giữa khung nhìn', () => {
        const els = mk([[-700, -100], [100, 700], [800, 1400]]);
        expect(pickActive(els, 800)).toBe(els[1]);
    });

    it('bỏ qua bước nằm ngoài khung nhìn, trả null khi không còn bước nào', () => {
        const els = mk([[-700, -100], [900, 1400]]);
        expect(pickActive(els, 800)).toBeNull();
    });

    it('initSteps đánh dấu is-active và bật steps-js', () => {
        const els = mk([[0, 400], [400, 800]]);
        const off = initSteps(document);
        expect(document.documentElement.classList.contains('steps-js')).toBe(true);
        expect(els.filter((e) => e.classList.contains('is-active'))).toHaveLength(1);
        off();
    });

    it('không có bước nào thì không làm gì', () => {
        document.body.innerHTML = '';
        expect(typeof initSteps(document)).toBe('function');
        expect(document.documentElement.classList.contains('steps-js')).toBe(false);
    });

    it('initZoom: bấm ảnh mở hộp thoại với ảnh và chú thích, bấm nền thì đóng', () => {
        document.body.innerHTML = '<figure class="step-fig"><img src="/a.webp" alt="Mô tả"><figcaption>Chú thích</figcaption></figure>';
        const off = initZoom(document);
        document.querySelector('.step-fig img').click();
        const dlg = document.querySelector('dialog.img-zoom');
        expect(dlg.hasAttribute('open')).toBe(true);
        expect(dlg.querySelector('img').getAttribute('alt')).toBe('Mô tả');
        expect(dlg.querySelector('.img-zoom-cap').textContent).toBe('Chú thích');
        dlg.click();
        expect(dlg.hasAttribute('open')).toBe(false);
        off();
        expect(document.querySelector('dialog.img-zoom')).toBeNull();
    });
});
