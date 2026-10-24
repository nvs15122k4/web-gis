# Pipeline xử lý ảnh Sentinel-2 — biến động bờ sông Hậu, xã Bình Mỹ

Khối (a) của đề cương (Mục 2.3–2.5): ảnh Sentinel-2 L2A → đường bờ từng năm → transect → NSM/EPR/LRR/R²,
độ bất định → phân loại → GeoJSON để web nạp bằng `php artisan webgis:import`.

## Chạy

```bash
cd pipeline
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
.venv/bin/python run.py                  # ~15 phút lần đầu (tải ảnh từ Microsoft Planetary Computer, không cần tài khoản)
.venv/bin/python run.py --skip-download  # chạy lại các bước sau khi đã có composite
.venv/bin/python s8_crosscheck.py          # đối chiếu chéo với sản phẩm phủ bề mặt độc lập
python3 qgis_maps.py                     # bản đồ chuyên đề (PyQGIS hệ thống)
cp output/aoi.geojson ../web/public/data/aoi.geojson
cd ../web && php artisan webgis:import ../pipeline/output
```

`quicklook.py <năm> <ảnh.png> [lớp.geojson:màu ...]` vẽ ảnh composite kèm lớp vector để kiểm tra/minh hoạ báo cáo.

## Các bước

| Script | Việc | Đầu ra |
|---|---|---|
| `s1_composite.py` | Tìm ảnh L2A tháng 1–3 mỗi năm (ô 48PWS); bỏ pixel SCL mây/bóng mây/tuyết/bão hoà; loại ảnh có >30% pixel bị che; trừ offset 1000 cho ảnh processing baseline ≥ 04.00 (từ 25/01/2022); SWIR 20 m → 10 m song tuyến; composite trung vị | `data/composite/<năm>.tif`, `scenes.json` |
| `s2_water.py` | NDWI (McFeeters 1996), MNDWI (Xu 2006), AWEI<sub>nsh</sub> (Feyisa 2014); ngưỡng Otsu trên ranh giới xã + đệm 1 km | `data/water/<năm>_<chỉ số>.tif`, `thresholds.json` |
| `s3_shoreline.py` | Mở hình thái (bỏ kênh < ~100 m), giữ vùng nước liên thông lớn nhất, lấp lỗ < 5 ha; contour mức 0,5 (dưới pixel); cắt theo ranh giới xã; đơn giản hoá 5 m | `data/shoreline/<năm>.geojson` |
| `s4_transects.py` | Baseline = đường bờ năm đầu làm trơn, dịch 250 m vào đất; transect vuông góc mỗi 50 m, dài 600 m; bỏ transect cắt nhau ở chỗ cong gắt; khoảng cách tới giao điểm gần baseline nhất | `data/transects.geojson`, `data/baseline.geojson` |
| `s5_stats.py` | Despike (lệch > 50 m so với trung vị chuỗi); NSM, EPR, LRR (OLS), SE, R², Unc; phân loại Mục 2.5a; gom transect lùi mạnh liền kề thành hotspot | `output/*.geojson`, `output/summary.json` |
| `s6_reference.py` | Số hoá vùng sạt lở tham chiếu 2019–2020 (nguồn báo chí/UBND tỉnh), AOI cho web | `output/reference.geojson`, `output/aoi.geojson` |
| `gt_kit.py`, `gt_qgis_project.py` | Bộ số hoá ground truth: 5 ô mẫu × 2018/2022/2026, ảnh màu giả, project QGIS `data/ground_truth/so_hoa_ground_truth.qgz` | `data/ground_truth/` |
| `s7_evaluate.py` | Precision/Recall/F1/IoU từng chỉ số nước, RMSE đường bờ trên ô mẫu đã số hoá | `output/evaluation.json` |
| `s8_crosscheck.py` | Đối chiếu chéo với ESA WorldCover 2020–2021 và Impact Observatory LULC 2018–2023 (10 m): F1/IoU từng chỉ số, sai lệch đường bờ trên transect — bổ sung, không thay ground truth | `output/crosscheck.json`, `data/crosscheck/` |
| `qgis_maps.py` | Thành lập 5 bản đồ chuyên đề bằng QGIS (vị trí, đường bờ, phân loại, chi tiết điểm nóng, đối chiếu 2021) — chạy bằng `python3` hệ thống có PyQGIS | `../docs/ban_do/*.png\|pdf`, `data/ban_do/ket_qua_song_hau.qgz` |

Mọi tham số nằm trong `config.py`.

## Việc còn phải làm (ghi rõ trong báo cáo)

1. **Ground truth (Mục 2.4)** — số hoá thủ công trong QGIS (`data/ground_truth/so_hoa_ground_truth.qgz`), chạy `s7_evaluate.py`, rồi:
   - đổi `DEFAULT_INDEX` sang chỉ số có F1 cao nhất;
   - điền `U_CLASSIFY` = RMSE từng năm. Hiện đang dùng tạm `U_CLASSIFY_DEFAULT = 10 m` (`summary.json` có cờ `u_classify_is_placeholder`).
2. **U_georef** — đã chốt 12 m theo đặc tả ESA (định vị tuyệt đối L1C < 12 m, độ tin cậy 95,5%); còn trích số liệu đo
   trong Data Quality Report đúng giai đoạn ảnh để dẫn chứng thêm.
3. **Vị trí điểm sạt lở** — nguồn công khai chỉ ghi lý trình Km 89+272–89+336 (2019) và Km 89+076 (2020), không có toạ độ;
   hiện số hoá cả vùng cảnh báo từ vàm Cây Dương đến bến phà Năng Gù. Có toạ độ thực địa thì cập nhật `s6_reference.py`.

## Ghi chú diễn giải

- Trong vùng sạt lở 2019–2020 (66 transect) không có transect nào "lùi": 36 bồi, 30 ổn định; khoảng cách trung bình
  giảm nhẹ 2018→2019 rồi tăng đều tới 2026. Giả thuyết cần kiểm chứng: công trình gia cố sau sự cố (bao cát ~26.000 m³,
  kè) đẩy mép nước ra sông; còn vết sạt 85 m chỉ ≈ 2 transect và vài mét — xấp xỉ độ bất định ±16,4 m. Nêu rõ trong Chương 3.
- Các đoạn "lùi bờ mạnh" hiện ngắn (1–2 transect); tiêu chí Mục 2.5 không đặt độ dài tối thiểu nên giữ nguyên.
