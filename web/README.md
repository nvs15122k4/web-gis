# Web — ứng dụng WebGIS (Laravel)

Thành phần web của dự án (xem [README gốc](../README.md)): Laravel (MVC) · PostgreSQL/PostGIS · MySQL · GeoServer (WMS) ·
Leaflet + Chart.js. Dịch vụ nền chạy nhanh bằng `docker compose up -d` (MySQL :3307, PostGIS :5432, GeoServer :8080).

Hệ thống **chỉ đọc**: NSM/EPR/LRR/R² và mức biến động được pipeline Python tính sẵn; Laravel không tính lại.

## Yêu cầu

PHP ≥ 8.4 (`pdo_mysql`, `pdo_pgsql`), Composer, Node ≥ 20, MySQL 8+, PostgreSQL 14+ với **PostGIS**, Java 17 + GeoServer 2.27, `curl`.

## 1. Cài đặt

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # chỉnh DB_* (MySQL) và DB_PGSQL_* (PostGIS)
```

Tạo sẵn 2 CSDL rỗng: MySQL `webgis_app`, PostgreSQL `webgis_spatial` (migration tự `CREATE EXTENSION postgis`,
tài khoản cần đủ quyền).

**Bản đồ nền:** mặc định là nền **tự phục vụ trên GeoServer của dự án** (layer group `nen_ban_do`, mục 3) — không lấy tile
bên thứ ba, đã bỏ mọi nền có vẽ "đường lưỡi bò". Dữ liệu: Natural Earth 1:10m bản theo quan điểm Việt Nam (quần đảo Hoàng Sa
thuộc TP Đà Nẵng, quần đảo Trường Sa thuộc tỉnh Khánh Hòa, ghi "Biển Đông"), 34 tỉnh sau sáp nhập 01/07/2025 (đối chiếu
63 → 34 tỉnh từ [provinces.open-api.vn](https://provinces.open-api.vn), lưu ở `geoserver/basemap/provinces_2025.csv`) và
OpenStreetMap quanh vùng nghiên cứu (đường, sông/kênh, mặt nước, địa danh; © OpenStreetMap contributors, ODbL).
Nền phụ: Google bản Việt Nam (`gl=VN`) và ảnh vệ tinh Esri; điền `VIETMAP_API_KEY=` trong `.env` (key tại
[maps.vietmap.vn](https://maps.vietmap.vn), nên giới hạn theo domain vì key lộ ra trình duyệt) để thêm nền VIETMAP.
Không dùng nền OpenStreetMap/CARTO dạng tile có sẵn vì ở mức thu nhỏ chúng vẽ "đường lưỡi bò".
Bản đồ khoá khung nhìn trong phạm vi đề tài (đoạn sông Hậu – xã Bình Mỹ, hằng `AREA` trong `resources/js/map.js`): không kéo,
thu nhỏ ra ngoài được, kể cả khi mở liên kết chia sẻ trỏ ra xa.

## 2. Migrate & seed

```bash
php artisan migrate            # 3 bảng MySQL + 7 bảng PostGIS + view transects_styled
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

## 3. GeoServer

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

AOI hiện là **khung tạm** (`public/data/aoi.geojson`) — thay bằng ranh giới hành chính xã Bình Mỹ.

## 5. Kiểm thử

```bash
# PHPUnit (Feature test cho 9 endpoint + import). Cần DB test đã migrate sẵn, mỗi test chạy trong transaction:
#   tạo webgis_app_test (MySQL) và webgis_spatial_test (PostGIS), rồi:
php artisan migrate --env=testing
php artisan test
```

```bash
# Playwright E2E (21 kịch bản) trên CSDL riêng *_e2e + workspace GeoServer songhau_e2e
php artisan migrate --env=e2e && geoserver/provision.sh songhau_e2e webgis_spatial_e2e   # một lần
cd ../e2e && npm install && npx playwright install && npx playwright test
```

`.env.testing` / `.env.e2e` mang thông tin kết nối riêng (không commit). Global setup của Playwright tự nạp lại
fixture cố định (`E2eFixtureSeeder`: năm 2022 thiếu composite, transect T05 thiếu 2020–2021, sạt lở tham chiếu chỉ ở phía bắc).
WebKit trên Linux cần thư viện hệ thống (`sudo npx playwright install-deps`).

## Ghi chú

- `GET /api/*` trả GeoJSON EPSG:4326; GeoServer reproject sang 3857 khi cần.
- Chỉ 1 vai trò phụ `admin` (sửa ngưỡng hiển thị `min_erosion_rate`), token Sanctum; mọi thay đổi ngưỡng ghi vào `activity_logs`.
