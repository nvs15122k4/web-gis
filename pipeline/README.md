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
cp output/aoi.json ../web/public/data/aoi.json
cd ../web && php artisan webgis:import ../pipeline/output
```

`quicklook.py <năm> <ảnh.png> [lớp.json:màu ...]` vẽ ảnh composite kèm lớp vector để kiểm tra/minh hoạ báo cáo.

## Các bước

| Script | Việc | Đầu ra |
|---|---|---|
| `s1_composite.py` | Tìm ảnh L2A tháng 1–3 mỗi năm (ô 48PWS); bỏ pixel SCL mây/bóng mây/tuyết/bão hoà; loại ảnh có >30% pixel bị che; trừ offset 1000 cho ảnh processing baseline ≥ 04.00 (từ 25/01/2022); SWIR 20 m → 10 m song tuyến; composite trung vị | `data/composite/<năm>.tif`, `scenes.json` |
| `s2_water.py` | NDWI (McFeeters 1996), MNDWI (Xu 2006), AWEI<sub>nsh</sub> (Feyisa 2014); ngưỡng Otsu trên ranh giới xã + đệm 1 km | `data/water/<năm>_<chỉ số>.tif`, `thresholds.json` |
| `s3_shoreline.py` | Mở hình thái (bỏ kênh < ~100 m), giữ vùng nước liên thông lớn nhất, lấp lỗ < 5 ha; contour mức 0,5 (dưới pixel); cắt theo ranh giới xã; đơn giản hoá 5 m | `data/shoreline/<năm>.json` |
| `s4_transects.py` | Baseline = đường bờ năm đầu làm trơn, dịch 250 m vào đất; transect vuông góc mỗi 50 m, dài 600 m; bỏ transect cắt nhau ở chỗ cong gắt; khoảng cách tới giao điểm gần baseline nhất | `data/transects.json`, `data/baseline.json` |
| `s5_stats.py` | Despike (lệch > 50 m so với trung vị chuỗi); NSM, EPR, LRR (OLS), SE, R², Unc; phân loại Mục 2.5a; gom transect lùi mạnh liền kề thành hotspot | `output/*.json`, `output/summary.json` |
| `s6_reference.py` | Số hoá vùng sạt lở tham chiếu 2019–2020 (nguồn báo chí/UBND tỉnh), AOI cho web | `output/reference.json`, `output/aoi.json` |
| `gt_kit.py`, `gt_qgis_project.py` | Bộ số hoá ground truth: 5 ô mẫu × 2018/2022/2026, ảnh màu giả, project QGIS `data/ground_truth/so_hoa_ground_truth.qgz` | `data/ground_truth/` |
| `s7_evaluate.py` | Precision/Recall/F1/IoU từng chỉ số nước, RMSE đường bờ từng chỉ số trên ô mẫu đã số hoá; chọn chỉ số bằng `select_index` (F1, hoà thì RMSE) | `output/evaluation.json` |
| `s9_validate.py` | Đối chiếu hotspot với vùng sạt lở tham chiếu 2019–2020: khoảng cách tới hotspot gần nhất và thống kê transect trong vùng (chỉ kiểm chứng) | `output/validation.json` |
| `s8_crosscheck.py` | Đối chiếu chéo với ESA WorldCover 2020–2021 và Impact Observatory LULC 2018–2023 (10 m): F1/IoU từng chỉ số, sai lệch đường bờ trên transect — bổ sung, không thay ground truth | `output/crosscheck.json`, `data/crosscheck/` |
| `qgis_maps.py` | Thành lập 5 bản đồ chuyên đề bằng QGIS (vị trí, đường bờ, phân loại, chi tiết điểm nóng, đối chiếu 2021) — chạy bằng `python3` hệ thống có PyQGIS | `../docs/ban_do/*.png\|pdf`, `data/ban_do/ket_qua_song_hau.qgz` |

Mọi tham số nằm trong `config.py`.

## Việc còn phải làm (ghi rõ trong báo cáo)

1. **Ground truth (Mục 2.4)** — *đã hoàn thành*: số hoá trong QGIS (`data/ground_truth/so_hoa_ground_truth.qgz`), `s7_evaluate.py` cho
   `output/evaluation.json`, `U_CLASSIFY` = RMSE từng năm (15,1 / 18,39 / 19,99 m; năm khác dùng 20 m). F1 trung bình của AWEI và MNDWI
   gần như hoà (0,9457 so với 0,9456), nên `s7_evaluate.select_index` xét tiếp RMSE đường bờ trung bình (MNDWI 17,83 m; NDWI 28,11 m; AWEI 62,54 m) và chọn `DEFAULT_INDEX = "mndwi"`.
   Ngưỡng hoà `SELECT_TIE_F1 = 0,005` đặt sau khi đã thấy chênh F1 là 0,0001 (mọi giá trị ≥ 0,0001 cho cùng kết quả); đây là điều cần nói rõ trong báo cáo.
2. **U_georef** — đặc tả ESA: định vị tuyệt đối L1C < 12 m ở độ tin cậy 95,5% (2σ), quy về 1σ = 6 m để cùng chuẩn với U_pixel/U_classify (phép quy đổi là suy luận của đồ án, không có nguồn nói rõ; Clerc et al. đo 2σ thực tế 11,6 m cho S2A và 13,5 m cho S2B); còn trích số liệu đo
   trong Data Quality Report đúng giai đoạn ảnh để dẫn chứng thêm.
3. **Vị trí điểm sạt lở** — nguồn công khai chỉ ghi lý trình Km 89+272–89+336 (2019) và Km 89+076 (2020), không có toạ độ;
   hiện số hoá cả vùng cảnh báo từ vàm Cây Dương đến bến phà Năng Gù. Có toạ độ thực địa thì cập nhật `s6_reference.py`.

## Ghi chú diễn giải

- Trong bán kính 300 m quanh vùng sạt lở 2019–2020 (73 transect) không có transect nào "lùi": 50 bồi, 23 ổn định; khoảng cách trung bình
  giảm nhẹ 2018→2019 rồi tăng đều tới 2026. Giả thuyết cần kiểm chứng: công trình gia cố sau sự cố (bao cát ~26.000 m³,
  kè) đẩy mép nước ra sông; còn vết sạt 85 m chỉ ≈ 2 transect và vài mét — nhỏ hơn độ bất định đường bờ (≈ 20–24 m). Nêu rõ trong Chương 3.
- Các đoạn "lùi bờ mạnh" hiện ngắn (1–2 transect); tiêu chí Mục 2.5 không đặt độ dài tối thiểu nên giữ nguyên.


## Đối chiếu sạt lở tham chiếu (`s9_validate.py`, `output/validation.json`)

Kết quả chạy trên dữ liệu hiện có (vùng cảnh báo dài 3.271 m, 2 sự kiện dùng chung một hình học nên **không độc lập**):

- Hotspot gần nhất cách vùng cảnh báo **5.234 m** (> 500 m): **0/2 sự kiện khớp**. (Báo cáo cũ ghi 5.236 m; chênh 2 m do cách đo trên đa giác hotspot trong UTM.)
- 69 transect nằm trong vùng đệm 100 m quanh vùng cảnh báo: 41 bồi, 28 ổn định, 0 lùi mạnh/lùi nhẹ; LRR trung bình +1,14 m/năm; chỉ 1,4% transect có LRR < 0 và không transect nào lùi có ý nghĩa thống kê.

Đây là kết quả không khớp, phải nêu thẳng trong báo cáo. Các giải thích dưới đây là **giả thuyết chưa kiểm chứng**, không phải kết luận:
1. Đường bờ 2018 làm mốc đầu, còn sạt lở 2019–2020 (sạt dài 85 m, ăn sâu 25 m theo nguồn tin) là biến cố cục bộ, và việc xử lý sau đó (nếu có) có thể khiến bờ ổn định/bồi trở lại trong chuỗi 2018–2026.
2. Ảnh 10 m (độ bất định U_shoreline ≈ 17–21 m) khó thấy sạt lở cục bộ nhỏ; LRR ≤ −2 m/năm theo chuỗi 9 mốc làm loãng biến cố một lần.
3. Vùng cảnh báo chỉ là đoạn bờ dài, không phải vị trí điểm sạt; chưa có toạ độ thực địa.
Muốn kiểm tra giả thuyết cần toạ độ sạt lở từ cơ quan quản lý và/hoặc phân tích chuỗi 2018→2019/2020 riêng. Không chỉnh ngưỡng cho khớp.

## Ghi chú về hai ngưỡng

- `config.SEVERE_LRR = -2` m/năm: **phân loại** lùi bờ mạnh của pipeline (quyết định hotspot nào tồn tại).
- `min_erosion_rate` (web, mặc định 1,0): chỉ là bộ lọc **hiển thị** theo `severity` của hotspot (trung bình −LRR). Mọi hotspot pipeline sinh ra đã có severity ≥ 2 nên ngưỡng 1,0 không ẩn hotspot nào; admin đặt cao hơn mới ẩn bớt. Không thay đổi dữ liệu hay phân loại.
