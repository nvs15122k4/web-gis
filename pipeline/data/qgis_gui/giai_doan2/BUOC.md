# Giai đoạn 2 — Baseline, transect, khoảng cách, thống kê, đối chiếu, bản đồ: làm bằng giao diện QGIS 3.40.15

Project: `giai_doan2.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/giai_doan2/` (tên file nêu bên dưới;
một số số thứ tự bị trùng nên tra theo tên). Mọi phép tính đều dùng Processing Toolbox / Field Calculator / Print Layout;
không dùng Python console. Đầu vào: 9 file `pipeline/data/qgis_gui/Y/shoreline_Y.gpkg` (Y = 2018…2026).

## Kết quả chính
| Chỉ số | Bản GUI (giai đoạn 2) | Bản cũ (`output/transects.json`) |
|---|---|---|
| Số transect | **270** (không có cặp nào cắt nhau, không phải bỏ transect) | 266 |
| Transect ghép được theo `transect_code` | 261 (9 mã không có ở bản cũ) | |
| Chênh LRR (GUI − cũ), m/năm | trung bình 0,080; trung vị 0,035; độ lệch chuẩn 1,387; min −8,61; max +4,28; **RMSE 1,389** | |
| RMSE EPR | 1,564 m/năm | |
| Cùng nhóm `change_class` | 164/261 (62,8 %) | |
| Phân nhóm | stable 209, accretion 52, mild_erosion 8, severe_erosion 1 | stable 181, accretion 71, mild 10, severe 4 |

## Các bước
1. **Project + nạp dữ liệu** — project mới, CRS 32648 (`01_project-crs-32648`); Data Source Manager nạp 9 gpkg một lần (`02_nap-9-shoreline-gpkg`), đổi tên lớp `sl_2018…sl_2026` bằng F2 (`03_doi-ten-lop-sl_2018-2026`). Cột `year` không cần ở từng lớp; lúc gộp để xuất web thì tạo từ tên lớp (bước 8).
2. **Baseline** (từ `sl_2018`): Multipart to singleparts → Extract by expression `$length = maximum($length)` (đoạn dài nhất) → Simplify Douglas-Peucker 150 m → Smooth (4 lần, offset 0,25) → Offset lines **+250 m** (dương = lệch sang TRÁI hướng vẽ). Phía đất xác định bằng cách xem bản đồ: baseline nằm phía tây/nam, tức phía đất, sông Hậu ở phía đông/bắc (`14_ban-do-baseline-pha-dat`). Ảnh `04…13`; file `bl_1…bl_4_*.gpkg`, `baseline.gpkg`.
3. **Transect**: Points along geometry (cách 50 m, bắt đầu lệch 25 m → 270 điểm; `15`,`16`) rồi Geometry by expression (kiểu Line) dựng đoạn thẳng dài 600 m, vuông góc với hướng baseline ước lượng trên cửa sổ ±100 m (như `s4_transects.py`), hướng sang phải = ra sông (`17`,`18`; biểu thức đầy đủ ở dưới). Extract by expression `NOT overlay_crosses('tr_2_transect_tho')` để bỏ transect cắt nhau — kết quả loại 0 transect (kiểm tra lại bằng SQL ST_Intersects ngoài QGIS cũng ra 0) (`19`,`20`). Lưu thành `transects.gpkg` (Export > Save Features As, `20_transects-save-features-as`). Field Calculator: `transect_code = 'T'||lpad(to_string(to_int(("distance"-25)/50)+1),4,'0')` (T0001 ↔ chainage 25 m, đúng quy ước bản cũ) và `chainage_m = "distance"` (`21`,`22`).
4. **Khoảng cách theo năm** — Field Calculator (cột thập phân `d2018`…`d2026`), mỗi năm:
   `with_variable('p', intersection($geometry, aggregate('sl_Y','collect',$geometry)), if(is_empty_or_null(@p), NULL, distance(start_point($geometry), closest_point(@p, start_point($geometry)))))`
   tức giao điểm GẦN gốc transect nhất; không giao thì NULL (`23`,`24`). Mỗi cột là một lần Field Calculator (chức năng Apply, không dùng "Line intersections" riêng).
5. **Thống kê** (đều bằng biểu thức QGIS, `25`…`31`; biểu thức sinh bằng một đoạn văn bản rồi dán vào hộp thoại):
   - Khử đột biến như `DESPIKE_M = 50`: `med_d` = trung vị các d không NULL; `c2018…c2026 = if(abs(d − med_d) <= 50, d, NULL)`; `n_obs`, `n_despiked`.
   - Với chuỗi c (bỏ NULL, cần ≥ 3 điểm): `first_year`, `last_year`, `nsm = d_cuối − d_đầu`, `epr = nsm/(năm cuối − năm đầu)`, `lrr` = Sxy/Sxx (hồi quy bình phương tối thiểu viết bằng `array_*`, `array_foreach`, `generate_series`), `r2 = Sxy²/(Sxx·Syy)`, `se_lrr = sqrt((Syy − Sxy²/Sxx)/(n−2)/Sxx)`.
   - `t_crit` = t(0,975; n−2) tra bảng nhúng trong biểu thức (12,706; 4,303; 3,182; 2,776; 2,571; 2,447; 2,365 cho n = 3…9), `is_sig = |lrr| > t_crit·se_lrr`.
   - `consec_dec` = số giai đoạn giảm liên tục dài nhất (chuỗi cờ '1'/'0' → `string_to_array` → `array_max(length)`).
   - `change_class` đúng `s5_stats.py`: không có ý nghĩa → `stable`; LRR > 0 → `accretion`; LRR ≤ −2 và consec_dec ≥ 2 → `severe_erosion`; còn lại `mild_erosion`; thiếu dữ liệu → `no_data`. R² chỉ là cờ, không đổi nhóm (cờ `low_r2_caution` và `unc_epr/unc_lrr` chưa tính ở bản GUI).
6. **Đối chiếu bản cũ** — nạp `pipeline/output/transects.json` (đổi tên `transects_cu`, `32`), Join attributes by field value theo `transect_code` với tiền tố `cu_` (`33`, 261/270 ghép được; lần chạy đầu chọn nhầm trường `fid` nên 0 ghép, đã chạy lại), Field Calculator `lrr_cu`, `epr_cu`, `class_cu` lấy từ `cu_stats` bằng `map_get`, `d_lrr = lrr − lrr_cu`, `d_epr`, `khop_lop` (`34`), RMSE/trung bình tính bằng biểu thức aggregate trong bản xem trước Field Calculator (`35`), Basic statistics for fields trên `d_lrr` (`36`; file `so_sanh_ban_cu.gpkg`, `thong_ke_chenh_lrr.gpkg`).
7. **Bản đồ (Print Layout)** — nền `pipeline/data/qgis_gui/2026/green_median_2026.tif`. Tạo 3 map theme (`A_9_duong_bo`, `B_transect_lrr`, `C_so_sanh_lrr`) và 3 layout (map + tiêu đề + chú giải lọc theo bản đồ + thước tỉ lệ + mũi tên Bắc), xuất PNG 300 dpi và PDF:
   `ban_do_1_vi_tri_9_duong_bo` (shorelines phân loại theo năm, bảng màu Turbo, + baseline; `42`),
   `ban_do_2_transect_theo_lrr` (transect tô graduated theo `lrr`, 5 lớp quantile, bảng Spectral; `43`),
   `ban_do_3_so_sanh_lrr` (transect tô theo `d_lrr` GUI − cũ; `44`).
8. **Xuất cho web** (`export/`, GeoJSON, EPSG:4326, toạ độ 7 chữ số thập phân; ảnh `39`,`40`,`41`):
   - `shorelines.geojson`: Merge vector layers 9 lớp `sl_*` → `shorelines_9_nam.gpkg`; Field Calculator `year = to_int(right("layer",4))`, `water_index = 'mndwi'` (chữ thường, bản cũ web dùng 'MNDWI'; `38`); chỉ xuất `year`, `water_index`.
   - `transects.geojson`: mọi cột trừ `fid, DN, distance, angle` (có `transect_code`, `chainage_m`, `d2018…d2026` là GIÁ TRỊ THÔ, `med_d`, `c2018…c2026` là giá trị đã khử đột biến, `n_obs`, `n_despiked`, `first_year`, `last_year`, `nsm`, `epr`, `lrr`, `r2`, `se_lrr`, `t_crit`, `consec_dec`, `is_sig`, `change_class`).
   - `baseline.geojson`: không có thuộc tính.
   - Mỗi lần xuất QGIS còn ghi kèm `*.qmd` (siêu dữ liệu), không dùng được cho web, có thể xoá.

### Biểu thức dựng transect (Geometry by expression, kiểu Line)
```
with_variable('b', geometry(get_feature_by_id('baseline',1)),
 with_variable('az', azimuth(line_interpolate_point(@b, max("distance"-100,0)),
                             line_interpolate_point(@b, min("distance"+100, length(@b)))),
  make_line($geometry, project($geometry, 600, @az + pi()/2))))
```

## Khác biệt / giả định / hạn chế
- **Hướng ra sông** xác định bằng quy tắc hình học (phía phải hướng vẽ baseline = sông, vì baseline offset sang trái) và kiểm tra bằng mắt trên ảnh, KHÔNG dùng mặt nạ nước như `s4_transects.py`.
- **Bỏ transect cắt nhau**: bản cũ bỏ lần lượt (tham lam, 4 transect); bản GUI dùng `overlay_crosses` một lần và kết quả là 0 transect cắt nhau, nên 270 thay vì 266 và 9 mã không có ở bản cũ.
- **Baseline khác bản cũ** (Smooth của QGIS ≠ Chaikin của Python, và QGIS cắt đoạn dài nhất khác một chút); khoảng cách baseline→bờ ở GUI ~340–380 m ở đoạn giữa và ~240 m ở đầu tuyến, bản cũ ~320–340 m. Vì vậy NSM/EPR so sánh được theo xu hướng (hiệu số theo thời gian), không so tuyệt đối d.
- **Đầu tuyến phía tây bắc** (T0001–T0007) đường bờ có đoạn gấp khúc song song với transect nên giao điểm gần nhất nhảy giữa ~115 m và ~350 m giữa các năm; khử đột biến loại nhiều quan sát ở đó (n_despiked lên tới 6) và các transect này có R², nhóm không ổn định. Cũng chính là nguồn các chênh LRR lớn (max |Δ| = 8,6 m/năm).
- Không tính `unc_epr`, `unc_lrr`, `low_r2_caution`, hotspot (không nằm trong yêu cầu giai đoạn này). Giá trị t Student tra bảng 3 chữ số thập phân (sai khác < 0,001 so với scipy).
- Lớp trung gian lưu thành file `.gpkg` trong thư mục này (không dùng lớp tạm) để dễ kiểm tra; `transects.gpkg-wal/-shm` là file phụ của SQLite sinh ra khi QGIS đang mở.
- Một số ảnh có số thứ tự trùng (20, 34, 37, 38) do chụp trong lúc thử lại; tra theo tên.
