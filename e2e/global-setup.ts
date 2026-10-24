import { execSync } from 'node:child_process';
import path from 'node:path';

/** Nạp lại dữ liệu cố định vào CSDL E2E trước mỗi lần chạy, để test không phụ thuộc lần chạy trước. */
export default async function globalSetup() {
    // Laravel không ghi đè biến môi trường đã có: DB_* export sẵn trong shell (vd `set -a; . ./.env`) sẽ đè .env.e2e
    // và seeder ghi đè dữ liệu tổng hợp lên CSDL phát triển. Dừng ngay nếu CSDL không phải *_e2e.
    const leaked = ['DB_DATABASE', 'DB_PGSQL_DATABASE'].filter((k) => process.env[k] && !process.env[k]!.endsWith('_e2e'));
    if (leaked.length) throw new Error(`Biến ${leaked.join(', ')} trong môi trường trỏ tới CSDL không phải *_e2e — mở shell mới rồi chạy lại.`);

    const cwd = path.resolve(__dirname, '../web');
    execSync('php artisan migrate --env=e2e --force', { cwd, stdio: 'inherit' });
    execSync('php artisan db:seed --class=E2eFixtureSeeder --env=e2e --force', { cwd, stdio: 'inherit' });

    const gs = process.env.GEOSERVER_URL ?? 'http://127.0.0.1:8080/geoserver';
    const res = await fetch(`${gs}/songhau_e2e/wms?service=WMS&request=GetCapabilities`).catch(() => null);
    if (!res?.ok) throw new Error(`GeoServer không phản hồi tại ${gs} (workspace songhau_e2e). Xem README mục GeoServer.`);
}
