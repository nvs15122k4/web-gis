import fs from 'node:fs';
import path from 'node:path';

/**
 * Môi trường E2E theo "slot". Không đặt E2E_SLOT: cổng 8010, CSDL webgis_*_e2e (như trước).
 * Đặt E2E_SLOT=<tên> (chữ thường/số/_): mỗi slot một cổng + một CSDL MySQL riêng (ngưỡng, người dùng, token),
 * nên nhiều phiên chạy Playwright song song không đè ngưỡng của nhau (nguyên nhân nghi ngờ của ca AD09 flaky).
 * PostGIS (transect, hotspot) vẫn dùng chung webgis_spatial_e2e vì GeoServer workspace songhau_e2e chỉ đọc CSDL đó;
 * dữ liệu này cố định, chỉ nạp khi còn trống.
 * Cổng: E2E_PORT nếu có, không thì 8011–8999 suy từ băm tên slot.
 */
const raw = process.env.E2E_SLOT?.trim() ?? '';
if (raw && !/^[a-z0-9_]{1,16}$/.test(raw)) throw new Error('E2E_SLOT chỉ gồm a-z, 0-9, _ (tối đa 16 ký tự).');

export const SLOT = raw;
export const PORT = Number(process.env.E2E_PORT) || (SLOT ? 8011 + ([...SLOT].reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 7) % 989) : 8010);
export const BASE = `http://127.0.0.1:${PORT}`;
export const DB_MYSQL = SLOT ? `webgis_app_e2e_${SLOT}` : 'webgis_app_e2e';

/** Biến môi trường truyền cho artisan (Laravel không ghi đè biến đã có, nên thắng .env.e2e). */
export const LARAVEL_ENV: Record<string, string> = SLOT
    ? { DB_DATABASE: DB_MYSQL, APP_URL: BASE, E2E_SKIP_PG_SEED: '1' }
    : {};

/** Đọc một khoá từ web/.env.e2e (để tạo CSDL slot bằng đúng tài khoản của môi trường E2E). */
export function e2eEnvFile(): Record<string, string> {
    const file = path.resolve(__dirname, '../web/.env.e2e');
    const out: Record<string, string> = {};
    for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
        const m = line.match(/^([A-Z0-9_]+)=(.*)$/);
        if (m) out[m[1]] = m[2].replace(/^["']|["']$/g, '');
    }
    return out;
}
