import { readFileSync, readdirSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const css = readdirSync('resources/css').filter((f) => f.endsWith('.css'));
const views = ['map/index', 'auth/login', 'admin/index', 'report/index'];

describe('chỉ còn giao diện sáng', () => {
    it('không CSS nào dùng prefers-color-scheme hay [data-theme]', () => {
        for (const f of css) {
            const src = readFileSync(`resources/css/${f}`, 'utf8');
            expect(src, f).not.toMatch(/prefers-color-scheme|data-theme/);
        }
    });
    it('token khai báo color-scheme: light', () => {
        expect(readFileSync('resources/css/tokens.css', 'utf8')).toMatch(/color-scheme:\s*light/);
    });
    it('các trang không có nút/ script đổi theme', () => {
        for (const v of views) expect(readFileSync(`resources/views/${v}.blade.php`, 'utf8')).not.toMatch(/theme-toggle|data-theme|localStorage/);
    });
});
