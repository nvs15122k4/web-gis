import { execSync } from 'node:child_process';
import path from 'node:path';
import { SLOT, DB_MYSQL, LARAVEL_ENV, e2eEnvFile } from './env';

/** Nạp lại dữ liệu cố định vào CSDL E2E trước mỗi lần chạy, để test không phụ thuộc lần chạy trước. */
export default async function globalSetup() {
    // Laravel không ghi đè biến môi trường đã có: DB_* export sẵn trong shell (vd `set -a; . ./.env`) sẽ đè .env.e2e
    // và seeder ghi đè dữ liệu tổng hợp lên CSDL phát triển. Dừng ngay nếu CSDL không phải *_e2e.
    const leaked = ['DB_DATABASE', 'DB_PGSQL_DATABASE'].filter((k) => process.env[k] && !process.env[k]!.match(/_e2e(_[a-z0-9]+)?$/));
    if (leaked.length) throw new Error(`Biến ${leaked.join(', ')} trong môi trường trỏ tới CSDL không phải *_e2e — mở shell mới rồi chạy lại.`);

    if (SLOT) ensureSlotDatabase();

    const cwd = path.resolve(__dirname, '../web');
    const env = { ...process.env, ...LARAVEL_ENV };
    // Slot: bảng migrations nằm ở MySQL riêng của slot nên migration PostGIS (migrations/pgsql) sẽ bị chạy lại vào CSDL dùng chung; chỉ chạy phần MySQL.
    const migrate = SLOT ? 'php artisan migrate --env=e2e --force --path=database/migrations' : 'php artisan migrate --env=e2e --force';
    execSync(migrate, { cwd, stdio: 'inherit', env });
    execSync('php artisan db:seed --class=E2eFixtureSeeder --env=e2e --force', { cwd, stdio: 'inherit', env });
    // Slot song song bỏ qua nạp PostGIS: chỉ nạp (một lần) nếu CSDL dùng chung còn trống.
    if (SLOT && !pgSeeded()) {
        execSync('php artisan db:seed --class=E2eFixtureSeeder --env=e2e --force', { cwd, stdio: 'inherit', env: process.env });
    }

    const gs = process.env.GEOSERVER_URL ?? 'http://127.0.0.1:8080/geoserver';
    const res = await fetch(`${gs}/songhau_e2e/wms?service=WMS&request=GetCapabilities`).catch(() => null);
    if (!res?.ok) throw new Error(`GeoServer không phản hồi tại ${gs} (workspace songhau_e2e). Xem README mục GeoServer.`);
    // Style tô màu đường bờ theo năm do provision.sh nạp: thiếu thì mọi tile đường bờ lỗi StyleNotDefined (ORB chặn) và test bản đồ đỏ khó hiểu.
    const styles = await fetch(`${gs}/rest/workspaces/songhau_e2e/styles/shoreline_by_year.json`, { headers: { Authorization: `Basic ${Buffer.from('admin:geoserver').toString('base64')}` } }).catch(() => null);
    if (styles && styles.status === 404) throw new Error('Workspace songhau_e2e thiếu style shoreline_by_year: chạy lại `geoserver/provision.sh songhau_e2e webgis_spatial_e2e` (xem README mục GeoServer).');
}

/** Tạo CSDL MySQL riêng của slot nếu chưa có; tên đã được env.ts kiểm tra chặt (a-z0-9_). */
function ensureSlotDatabase() {
    const e = e2eEnvFile();
    execSync(
        `mysql -h ${e.DB_HOST} -P ${e.DB_PORT} -u ${e.DB_USERNAME} -e "CREATE DATABASE IF NOT EXISTS ${DB_MYSQL} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`,
        { stdio: 'inherit', env: { ...process.env, MYSQL_PWD: e.DB_PASSWORD ?? '' } },
    );
}

/** PostGIS dùng chung đã có dữ liệu cố định (10 transect) chưa. */
function pgSeeded(): boolean {
    const e = e2eEnvFile();
    const out = execSync(
        `psql -h ${e.DB_PGSQL_HOST} -p ${e.DB_PGSQL_PORT} -U ${e.DB_PGSQL_USERNAME} -d ${e.DB_PGSQL_DATABASE} -tAq -c "SELECT count(*) FROM transects"`,
        { env: { ...process.env, PGPASSWORD: e.DB_PGSQL_PASSWORD ?? '' } },
    ).toString().trim();
    return Number(out) > 0;
}
