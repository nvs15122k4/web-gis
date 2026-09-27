import { execSync } from 'node:child_process';
import path from 'node:path';

/** Nạp lại dữ liệu cố định vào CSDL E2E trước mỗi lần chạy, để test không phụ thuộc lần chạy trước. */
export default async function globalSetup() {
    const cwd = path.resolve(__dirname, '../webgis');
    execSync('php artisan migrate --env=e2e --force', { cwd, stdio: 'inherit' });
    execSync('php artisan db:seed --class=E2eFixtureSeeder --env=e2e --force', { cwd, stdio: 'inherit' });

    const gs = process.env.GEOSERVER_URL ?? 'http://127.0.0.1:8080/geoserver';
    const res = await fetch(`${gs}/songhau_e2e/wms?service=WMS&request=GetCapabilities`).catch(() => null);
    if (!res?.ok) throw new Error(`GeoServer không phản hồi tại ${gs} (workspace songhau_e2e). Xem README mục GeoServer.`);
}
