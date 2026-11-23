import { describe, expect, it } from 'vitest';
import { readableTextOn } from '../../resources/js/contrast.js';

describe('readableTextOn', () => {
    it('chọn chữ tối trên nền sáng/trung bình và chữ trắng trên nền đậm', () => {
        expect(readableTextOn('#8a8a8a')).toBe('#111111');
        expect(readableTextOn('#2e9d4f')).toBe('#111111');
        expect(readableTextOn('#b8862b')).toBe('#111111');
        expect(readableTextOn('#d0312d')).toBe('#ffffff');
        expect(readableTextOn('#0c2d3f')).toBe('#ffffff');
    });
});
