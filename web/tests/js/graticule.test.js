import { describe, expect, it } from 'vitest';
import { formatCoord, graticuleTicks, niceStep } from '../../resources/js/graticule.js';

describe('graticule', () => {
    it('niceStep chọn bước chia gọn theo phút/giây', () => {
        expect(niceStep(10.5, 10.6, 5)).toBeCloseTo(2 / 60, 6); // 0,1° / 5 = 72 giây, làm tròn lên 120 giây
    });
    it('graticuleTicks trả về bội số của bước chia nằm trong khoảng', () => {
        const ticks = graticuleTicks(10.5, 10.6, 5);
        expect(ticks.length).toBeGreaterThanOrEqual(2);
        for (const t of ticks) {
            expect(t).toBeGreaterThanOrEqual(10.5 - 1e-9);
            expect(t).toBeLessThanOrEqual(10.6 + 1e-9);
        }
        const step = ticks[1] - ticks[0];
        expect(((ticks[0] * 3600) % Math.round(step * 3600))).toBeCloseTo(0, 6);
    });
    it('khoảng rỗng hoặc ngược thì không có vạch', () => {
        expect(graticuleTicks(10, 10)).toEqual([]);
        expect(graticuleTicks(11, 10)).toEqual([]);
    });
    it('formatCoord ghi độ, phút và bán cầu', () => {
        expect(formatCoord(10.5, 'lat', 1 / 60)).toBe('10°30′N');
        expect(formatCoord(105.29, 'lng', 1 / 60)).toBe('105°17′E');
        expect(formatCoord(-3.25, 'lat', 1 / 60)).toBe('3°15′S');
    });
    it('thêm giây khi bước chia nhỏ hơn một phút', () => {
        expect(formatCoord(105 + 17 / 60 + 24 / 3600, 'lng', 30 / 3600)).toBe('105°17′24″E');
    });
});
