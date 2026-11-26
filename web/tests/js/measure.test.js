import { describe, expect, it } from 'vitest';
import { distance, formatArea, formatLength, pathLength, polygonArea } from '../../resources/js/measure.js';

describe('measure', () => {
    it('đo khoảng cách ~111,2 km cho 1 độ vĩ', () => {
        expect(distance([10, 105], [11, 105])).toBeGreaterThan(111000);
        expect(distance([10, 105], [11, 105])).toBeLessThan(111400);
    });
    it('chiều dài đường gấp khúc bằng tổng các đoạn', () => {
        const p = [[10, 105], [10.01, 105], [10.01, 105.01]];
        expect(pathLength(p)).toBeCloseTo(distance(p[0], p[1]) + distance(p[1], p[2]), 6);
        expect(pathLength([[10, 105]])).toBe(0);
    });
    it('diện tích ô 0,01 độ xấp xỉ 1,2 km2 và 0 khi chưa đủ 3 điểm', () => {
        const a = polygonArea([[10, 105], [10, 105.01], [10.01, 105.01], [10.01, 105]]);
        expect(a).toBeGreaterThan(1.15e6);
        expect(a).toBeLessThan(1.25e6);
        expect(polygonArea([[10, 105], [10, 106]])).toBe(0);
    });
    it('định dạng đơn vị', () => {
        expect(formatLength(250)).toBe('250 m');
        expect(formatLength(2500)).toBe('2.50 km');
        expect(formatArea(500)).toContain('m²');
        expect(formatArea(2e6)).toContain('km²');
    });
});
