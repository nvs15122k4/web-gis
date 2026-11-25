import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { initTimeline } from '../../resources/js/timeline';

const markup = (years) => `
<div id="timeline"><button id="timeline-play" aria-pressed="false"><span id="timeline-play-label">Phát</span></button><ol id="timeline-ticks"></ol></div>
<div id="year-list">${years.map((y) => `<label><input type="checkbox" id="year-${y}"><span class="year-swatch" style="background:rgb(1, 2, 3)"></span></label>`).join('')}</div>`;

describe('initTimeline', () => {
    beforeEach(() => {
        window.WEBGIS = { years: { all: [2018, 2019, 2020], available: [2018, 2020] } };
        document.body.innerHTML = markup([2018, 2019, 2020]);
    });
    afterEach(() => vi.useRealTimers());

    it('an toàn khi thiếu #timeline hoặc #year-list', () => {
        document.body.innerHTML = '';
        expect(initTimeline()).toBeNull();
    });

    it('vẽ mỗi năm một mốc, năm chưa có dữ liệu bị làm mờ', () => {
        initTimeline();
        const ticks = document.querySelectorAll('.tick');
        expect(ticks).toHaveLength(3);
        expect(document.querySelector('.tick[data-year="2019"]').classList.contains('is-empty')).toBe(true);
    });

    it('bấm mốc thì bật hộp kiểm năm đó và đồng bộ ngược lại', () => {
        initTimeline();
        const box = document.getElementById('year-2020');
        const onChange = vi.fn();
        box.addEventListener('change', onChange);
        document.querySelector('.tick[data-year="2020"]').click();
        expect(box.checked).toBe(true);
        expect(onChange).toHaveBeenCalledTimes(1);
        expect(document.querySelector('.tick[data-year="2020"]').getAttribute('aria-pressed')).toBe('true');
        box.checked = false;
        box.dispatchEvent(new Event('change', { bubbles: true }));
        expect(document.querySelector('.tick[data-year="2020"]').getAttribute('aria-pressed')).toBe('false');
    });

    it('Phát lần lượt từng năm có dữ liệu rồi dừng khi bấm lại', () => {
        vi.useFakeTimers();
        initTimeline();
        const play = document.getElementById('timeline-play');
        play.click();
        expect(document.getElementById('year-2018').checked).toBe(true);
        vi.advanceTimersByTime(1200);
        expect(document.getElementById('year-2018').checked).toBe(false);
        expect(document.getElementById('year-2020').checked).toBe(true);
        play.click();
        expect(play.getAttribute('aria-pressed')).toBe('false');
        vi.advanceTimersByTime(5000);
        expect(document.getElementById('year-2020').checked).toBe(true);
    });
});
