# Web — ứng dụng WebGIS (Laravel)

Thành phần web của dự án (xem [README gốc](../README.md)): Laravel (MVC) · PostgreSQL/PostGIS · MySQL · Leaflet + Chart.js; **GeoServer (WMS + WFS) là tuỳ chọn**
(không đặt `GEOSERVER_URL` thì bản đồ vẽ GeoJSON từ `/api/*`, đủ để deploy Vercel + Supabase). Dịch vụ nền chạy nhanh bằng
`docker compose up -d` (MySQL :3307, PostGIS :5432, GeoServer :8080).

Hệ thống **chỉ đọc**: NSM/EPR/LRR/R² và mức biến động được pipeline Python tính sẵn; Laravel không tính lại.

## Yêu cầu

PHP ≥ 8.4 (`pdo_mysql`, `pdo_pgsql`), Composer, Node ≥ 20, MySQL 8+, PostgreSQL 14+ với **PostGIS**, `curl`; Java 17 + GeoServer 2.27 chỉ khi dùng chế độ GeoServer (tuỳ chọn).

## 1. Cài đặt

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # chỉnh DB_* (MySQL) và DB_PGSQL_* (PostGIS)
```

Tạo sẵn 2 CSDL rỗng: MySQL `webgis_app`, PostgreSQL `webgis_spatial` (migration tự `CREATE EXTENSION postgis`,
tài khoản cần đủ quyền).

**Lớp bản đồ:** không đặt `GEOSERVER_URL` thì transect, đường bờ theo năm, hotspot, sạt lở tham chiếu và ranh giới AOI đều là GeoJSON
từ `/api/*` (Leaflet; canvas cho transect), nền là VIETMAP nếu có `VIETMAP_API_KEY`, không thì OpenStreetMap + ảnh vệ tinh Esri
(OSM ở mức thu nhỏ vẽ "đường lưỡi bò"; dự phòng này chỉ dùng khi không có nền VIETMAP/GeoServer, nên đặt `VIETMAP_API_KEY` khi công bố).
Đặt `GEOSERVER_URL` thì quay lại chế độ WMS/WFS như mô tả dưới đây.

**Bản đồ nền (chế độ GeoServer):** nền **tự phục vụ trên GeoServer của dự án** (layer group `nen_ban_do`, mục 3) — không lấy tile
bên thứ ba, đã bỏ mọi nền có vẽ "đường lưỡi bò". Dữ liệu: Natural Earth 1:10m bản theo quan điểm Việt Nam (quần đảo Hoàng Sa
thuộc TP Đà Nẵng, quần đảo Trường Sa thuộc tỉnh Khánh Hòa, ghi "Biển Đông"), 34 tỉnh sau sáp nhập 01/07/2025 (đối chiếu
63 → 34 tỉnh từ [provinces.open-api.vn](https://provinces.open-api.vn), lưu ở `geoserver/basemap/provinces_2025.csv`) và
OpenStreetMap quanh vùng nghiên cứu (đường, sông/kênh, mặt nước, địa danh; © OpenStreetMap contributors, ODbL).
Nền phụ: ảnh vệ tinh Esri (không nhãn, không ranh giới). Đã bỏ nền Google vì chỉ ghi "quần đảo Hoàng Sa/Trường Sa",
"East Sea" mà không thể hiện thuộc Việt Nam. Điền `VIETMAP_API_KEY=` trong `.env` (key tại
[maps.vietmap.vn](https://maps.vietmap.vn), nên giới hạn theo domain vì key lộ ra trình duyệt) để thêm nền VIETMAP.
Ở chế độ GeoServer không dùng nền OpenStreetMap/CARTO dạng tile có sẵn vì ở mức thu nhỏ chúng vẽ "đường lưỡi bò".
File GeoJSON trong repo lưu đuôi `.json` (không dùng `.geojson`) vì GitHub tự vẽ file `.geojson` lên bản đồ nền có "đường lưỡi bò".
Khung nhìn neo vào phạm vi đề tài (đoạn sông Hậu – xã Bình Mỹ, hằng `AREA` trong `resources/js/map.js`): không kéo ra ngoài
được, kể cả khi mở liên kết chia sẻ trỏ ra xa, nhưng vẫn phóng to/thu nhỏ tự do (thu nhỏ tối đa ra toàn Việt Nam).

## 2. Migrate & seed

```bash
php artisan migrate            # bảng ứng dụng + 7 bảng PostGIS + view transects_styled (MySQL nếu DB_CONNECTION=mysql, ngược lại cùng Postgres)
php artisan db:seed            # admin@webgis.test / password + ngưỡng mặc định (đổi mật khẩu khi triển khai thật)
```

Nạp output pipeline (thư mục GeoJSON: `baseline`, `shorelines`, `transects`, `hotspots`, `reference` — định dạng
ở đầu `app/Console/Commands/ImportPipelineOutput.php`); chạy lại nhiều lần an toàn (UPSERT, ID không đổi):

```bash
php artisan webgis:import /duong/dan/output --dry-run   # kiểm tra
php artisan webgis:import /duong/dan/output
php artisan webgis:compare-reference                    # đối chiếu hotspot ↔ sạt lở tham chiếu 2019–2020
```

Chưa có dữ liệu thật? Nạp dữ liệu **tổng hợp** để xem giao diện: `php artisan db:seed --class=E2eFixtureSeeder`
(ghi đè bảng không gian — chỉ dùng trên DB dev/test).

## 3. GeoServer (tuỳ chọn)

Bỏ qua mục này nếu không dùng GeoServer (xem đầu mục 1).

```bash
python3 geoserver/enable-cors.py $GEOSERVER_HOME     # bật CORS cho Leaflet, rồi (re)start GeoServer
python3 geoserver/basemap/build_basemap.py         # dữ liệu nền → schema basemap (tải Natural Earth, OSM, danh mục tỉnh)
geoserver/provision.sh songhau webgis_spatial        # workspace + PostGIS store + SLD + 4 layer + nền nen_ban_do (idempotent)
```

`build_basemap.py` đọc `DB_PGSQL_*` từ môi trường (`set -a && . ./.env && set +a`), cache file tải về ở
`geoserver/basemap/.cache/`; thêm `--refresh` để tải lại OSM và bảng đối chiếu tỉnh. Với CSDL E2E:
`DB_PGSQL_DATABASE=webgis_spatial_e2e python3 geoserver/basemap/build_basemap.py` rồi `provision.sh songhau_e2e webgis_spatial_e2e`.

Biến `GEOSERVER_URL`, `GEOSERVER_AUTH` (mặc định `admin:geoserver` — đổi khi triển khai thật), `DB_PGSQL_*` được script đọc
từ môi trường. Layer `transects` publish từ view `transects_styled` (có `change_class` để tô màu).
Màu 4 mức biến động có **một nguồn duy nhất** `resources/js/colors.json`; `npm run colors` sinh `colors.css` và các
SLD trong `geoserver/styles/` (chạy tự động trong `npm run build`). Sau khi đổi màu, chạy lại `provision.sh`.
Lọc năm dùng `CQL_FILTER=year=…`.

## 4. Chạy

```bash
npm run build && php artisan serve      # http://127.0.0.1:8000  (trang quản trị ngưỡng: /admin)
```

AOI (`public/data/aoi.json`) là vùng nghiên cứu — đoạn bờ sông Hậu thuộc xã Bình Mỹ, sinh ra từ `pipeline/s6_reference.py`.

## 5. Kiểm thử

```bash
# PHPUnit (hơn 1.000 test: Feature, Unit, bảo mật, import). Cần DB test đã migrate sẵn, mỗi test chạy trong transaction:
#   tạo webgis_app_test (MySQL) và webgis_spatial_test (PostGIS), rồi:
php artisan migrate --env=testing
php artisan test
```

```bash
# Playwright E2E (163 kiểm thử × 2 trình duyệt) trên CSDL riêng *_e2e + workspace GeoServer songhau_e2e
php artisan migrate --env=e2e && geoserver/provision.sh songhau_e2e webgis_spatial_e2e   # một lần
cd ../e2e && npm install && npx playwright install && npx playwright test
```

`.env.testing` / `.env.e2e` mang thông tin kết nối riêng (không commit). Global setup của Playwright tự nạp lại
fixture cố định (`E2eFixtureSeeder`: năm 2022 thiếu composite, transect T05 thiếu 2020–2021, sạt lở tham chiếu chỉ ở phía bắc).
WebKit trên Linux cần thư viện hệ thống (`sudo npx playwright install-deps`). Trên Ubuntu mới hơn 24.04 (vd. 26.04) bản WebKit của Playwright không khởi chạy được vì thiếu `libicu74`/`libjxl0.8`; khi đó chạy `cd e2e && npm run test:no-webkit` (chỉ Chromium và Firefox).

## 6. Triển khai: Supabase + Vercel + UptimeRobot

Sản phẩm chạy với **một** CSDL Supabase Postgres + PostGIS (kết nối `pgsql`; bảng người dùng/ngưỡng và bảng không gian cùng nằm trong đó). Các bước dưới đây do chủ dự án tự làm trên dashboard; mã nguồn đã sẵn `vercel.json`, `api/index.php` và route `/healthz`.

**Supabase**
1. Tạo project, vào *Database > Extensions* bật `postgis` (hoặc để migration tự `CREATE EXTENSION`).
2. *Project Settings > Database > Connection pooling*: lấy host/user của pooler **Transaction**, cổng **6543** (`DB_PGSQL_HOST`, `DB_PGSQL_USERNAME` dạng `postgres.<ref>`, `DB_PGSQL_PASSWORD`).
3. Migrate/seed một lần từ máy cá nhân với `.env` trỏ tới Supabase: `php artisan migrate --force && php artisan db:seed --force`, rồi `php artisan webgis:import ../pipeline/output`. Đổi mật khẩu `admin@webgis.test` ngay sau khi seed.
4. Khác biệt so với MySQL: tìm email/khóa ngưỡng phân biệt hoa thường.

**Vercel** (import repo GitHub, *Root Directory* = `web`, nhánh production = `main`). Build dùng `npm run build`; runtime PHP là `vercel-php` (khai trong `vercel.json`); session/cache/log đã đặt `cookie`/`array`/`stderr`. Biến môi trường cần nhập (*Settings > Environment Variables*):

| Biến | Giá trị |
|---|---|
| `APP_KEY` | kết quả `php artisan key:generate --show` |
| `APP_URL` | URL Vercel (https) |
| `DB_PGSQL_HOST`, `DB_PGSQL_PORT=6543`, `DB_PGSQL_DATABASE`, `DB_PGSQL_USERNAME`, `DB_PGSQL_PASSWORD` | thông tin pooler Supabase (hoặc chỉ `DB_PGSQL_URL`) |
| `DB_PGSQL_SCHEMA` | `public,extensions` |
| `VIETMAP_API_KEY` | tuỳ chọn (không có thì nền dự phòng OpenStreetMap + Esri) |
| `GEOSERVER_URL`, `GEOSERVER_WORKSPACE` | **không bắt buộc**; chỉ đặt khi có GeoServer host ngoài Vercel (HTTPS, bật CORS cho domain Vercel) |

`APP_ENV`, `APP_DEBUG`, `LOG_CHANNEL`, `SESSION_DRIVER`, `CACHE_STORE`, `DB_CONNECTION`, `DB_PGSQL_EMULATE_PREPARES`, `DB_PGSQL_SSLMODE` đã có trong `vercel.json`. GeoServer **không** chạy trên Vercel; để trống `GEOSERVER_URL` thì ẩn liên kết WMS/WFS ở `/gioi-thieu` và `/api/status` trả `geoserver: null` (không báo lỗi).

**UptimeRobot** (giữ hàm/CSDL không ngủ và báo khi sập)
1. *Add New Monitor* > loại **HTTP(s)**, URL `https://<ten-mien>/healthz`.
2. Chu kỳ 5 phút; *Keyword* tuỳ chọn: `"status":"ok"`.
3. `/healthz` trả 200 `{"status":"ok"}` khi `SELECT 1` thành công, 503 `{"status":"error"}` khi mất CSDL; hỗ trợ cả GET và HEAD.

## Ghi chú

- `GET /api/*` trả GeoJSON EPSG:4326; khi có GeoServer, GeoServer reproject sang 3857 khi cần.
- Chỉ 1 vai trò phụ `admin` (sửa ngưỡng hiển thị `min_erosion_rate`), token Sanctum; mọi thay đổi ngưỡng ghi vào `activity_logs`.
