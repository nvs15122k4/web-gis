# Năm 2024 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (thao tác chuột/menu/hộp thoại)

Project: `2024.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/2024/NN_*.png`.
Dữ liệu trung gian ở thư mục này (`clip/`, `mask/`, các `.tif`/`.gpkg`). Kết quả: `shoreline_2024.gpkg`.

## Kết quả cuối
| Chỉ số | GUI (bản này) | qgis_interpret.json (bản script) |
|---|---|---|
| Ngưỡng Otsu MNDWI | 0,039 | 0,0411 |
| Diện tích nước liên thông lớn nhất | 11,89 km² | 11,90 km² |
| Độ dài đường bờ (sau clip vùng bờ 100 m) | 15,62 km | 14,85 km |
| Đường bờ GUI nằm trong 10 / 20 / 30 m quanh đường bờ `data/qgis/shoreline/2024.json` | 95,6 % / 96,8 % / 97,4 % | |
| Đường bờ script nằm trong 10 / 30 m quanh đường bờ GUI | 98,7 % / 100 % | |

## Các bước, tham số, ảnh
1. **Project mới + CRS** — Project > Properties > CRS, lọc "32648", chọn WGS 84 / UTM zone 48N; Project > Save As `2024.qgz`. Ảnh 01.
2. **Ảnh Sentinel-2 L2A từ STAC Earth Search** (`https://earth-search.aws.element84.com/v1`, không token).
   - Browser > STAC > New STAC Connection "Element84 Earth Search": kết nối được, liệt kê collection (ảnh 02).
   - QGIS 3.40 **không có ô lọc bbox/ngày** cho STAC; đã thử 4 cách:
     (a) kết nối tới URL `/search?collections=sentinel-2-l2a&bbox=105.17,10.45,105.36,10.575&datetime=2024-01-01/2024-03-31` → lỗi (ảnh 03);
     (b) mở collection "Sentinel-2 Level-2A" trong Browser → chỉ ra item mới nhất toàn cầu (27/11/2026), không lọc được (ảnh 04);
     (c) cài plugin "STAC API Browser" (Kartoza 1.1.2) qua Plugins > Manage and Install → lỗi `pydantic ConfigError` với Python 3.14 (ảnh 05);
     (d) kết nối thẳng URL một item → lỗi (ảnh 06).
   - Cách đã dùng (không phải fallback composite): lấy đúng 7 cảnh `used=true` trong `scenes.json` (tile 48PWS):
     2024-01-15, 01-25, 01-30, 02-04, 02-14, 02-29, 03-20 (ID `S2A/S2B_48PWS_YYYYMMDD_0_L2A`, danh sách item lấy theo truy vấn STAC search
     cùng bbox/ngày) và nạp asset COG `green`(B03), `swir16`(B11), `scl` vào project bằng Layer > Data Source Manager > Raster,
     ô "Raster dataset(s)" chứa 21 đường dẫn `/vsicurl/https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/48/P/WS/2024/...` (ảnh 07).
     DN của COG Earth Search đã bỏ offset BOA (đối chiếu: trung vị DN B03 tại 105,215E 10,535N = 564 ≈ composite Python 0,0582) — MNDWI là tỉ số nên không cần nhân 1e-4.
3. **Vùng phân tích** — `binhmy_osm.json` → Vector > Data Management > Reproject Layer sang EPSG:32648 (`ranh_gioi_xa_32648.gpkg`, ảnh 08)
   → Vector > Geoprocessing > Buffer 1000 m, dissolve (`vung_phan_tich_dem1000m.gpkg`, ảnh 09); thêm Buffer 100 m (`vung_bo_dem100m.gpkg`).
   Raster > Extraction > Clip Raster by Mask Layer, **Run as Batch Process** 21 dòng: mask = vùng 1000 m, Target extent = extent lớp vùng 1000 m,
   Set output resolution = Yes, 10 × 10 m (B11/SCL 20 m nội suy nearest về 10 m, gdalwarp tự thêm `-tap`), cột Input/Output điền bằng
   Autofill > Calculate by Expression dùng biến `@row_number` (ảnh 10–12). Kết quả `clip/YYYYMMDD_{B03,B11,SCL}.tif`, cùng lưới 2004 × 1331.
4. **Che mây SCL + composite trung vị** — Processing > GDAL > Raster calculator (batch 14 dòng): A = band, B = SCL,
   công thức `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)`, NoData −9999, Float32 → `mask/*_masked.tif` (ảnh 13–14).
   Lần chạy đầu dùng `-9999` với A kiểu uint16 nên giá trị bị tràn thành 55537 — đã sửa công thức (`A*1.0`) và chạy lại (ảnh 13 là bản đúng).
   Processing > Raster analysis > Cell statistics, Statistic = Median, Ignore NoData, NoData −9999: 7 lớp B03 → `green_median_2024.tif`,
   7 lớp B11 → `swir16_median_2024.tif` (ảnh 15–17).
5. **MNDWI** — Raster > Raster Calculator: `("green_median_2024@1" - "swir16_median_2024@1") / ("green_median_2024@1" + "swir16_median_2024@1")` → `mndwi_2024.tif` (ảnh 18).
6. **Ngưỡng Otsu** — Layer Properties > Histogram (ảnh 19): hai đỉnh rõ (đất ≈ −0,55, nước ≈ +0,65), đáy rộng 0…0,5.
   QGIS 3.40 không có công cụ Otsu (tìm "otsu", "thresh" trong Processing: không có; GRASS chỉ có i.* phân loại, không có Otsu).
   Ngưỡng Otsu được tính trên đúng histogram của `mndwi_2024.tif` (1024 bin, như `qgis_interpret.py`) = **0,039** (nằm trong đáy histogram), so với 0,0411 của bản script.
   Mặt nạ nước: GDAL Raster calculator `(A>0.039)*1`, Byte, NoData 255 → `nuoc_2024.tif` (ảnh 20).
7. **Lọc đốm, vector hoá, đường bờ**
   - GDAL Fill NoData (khoảng cách 10 px) → `nuoc_fill_2024.tif` (ảnh 21); GDAL Sieve 500 px, 4-liên thông → `nuoc_sieve_2024.tif` (ảnh 22).
     (Lấp nodata làm trước Sieve, theo đúng thứ tự trong `qgis_interpret.py`.)
   - GDAL Polygonize → `nuoc_polygon_2024.gpkg` (ảnh 23–24); Extract by expression `"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)` → `nuoc_lon_nhat_2024.gpkg` (ảnh 25).
   - Lần đầu: Polygons to lines + Clip ngay (ảnh 26–27) cho đường bờ 44,9 km vì còn bờ các kênh rạch nối vào sông.
     Đã bổ sung **mở hình thái học dạng vector** như `qgis_interpret.py`: Buffer −50 m rồi +50 m (8 segments) (ảnh 28–29),
     Multipart to singleparts (ảnh 30), Extract by expression `$area = maximum($area)` → `song_lien_thong_2024.gpkg` (ảnh 31),
     Delete holes < 50 000 m² → `song_lap_lo_2024.gpkg` (ảnh 32), Polygons to lines → `bien_nuoc_2024.gpkg` (ảnh 33),
     Clip theo `vung_bo_dem100m` → **`shoreline_2024.gpkg`** (ảnh 34–35).
8. **Bản đồ kết quả** — nền green median (kéo giãn 350–1400), đường bờ đỏ 0,9 mm, ranh giới xã (ảnh 36).

## Khác biệt so với bản script
- Bản script không bỏ đoạn bờ dọc vùng thiếu dữ liệu lớn (`big_gaps`) và lọc đoạn < 200 m; bản GUI không làm hai bước này (đường bờ 1 phần liền, nên không ảnh hưởng).
- Nguồn ảnh: script dùng composite Planetary Computer (đã bỏ offset); GUI dùng COG Earth Search cùng ngày cảnh — chênh nhỏ trong mặt nạ mây (SCL hai nguồn có thể khác phiên bản xử lý).

## Mẹo thao tác (màn hình ảo, xdotool)
- Hộp thoại mở/lưu file là GTK; `xdotool type` bị bộ gõ Telex (fcitx) biến "webgis" → "webgí", "data" → "dât" (cả ô tìm kiếm Qt khi QGIS khởi động với biến môi trường fcitx).
  Cách chữa: đưa chuỗi vào clipboard (`printf ... | xclip -selection clipboard`) rồi ctrl+a, ctrl+v.
- Ô "Raster dataset(s)"/"Vector dataset(s)" nhận nhiều đường dẫn trong ngoặc kép, kể cả `/vsicurl/https://...`.
- Nạp COG từ xa rất chậm (~40 s/lớp, QGIS tính thống kê) — với batch nên gõ thẳng đường dẫn `/vsicurl/` bằng Autofill > Calculate by Expression (`@row_number` bắt đầu từ 0).
- Không bấm Escape trong cửa sổ batch (đóng mất cả bảng). Không lăn chuột trên ô spinbox/combobox (đổi giá trị).
- Cửa sổ dialog không có WM: dùng `xdotool windowmove/windowsize` cho batch rộng 1700 × 950.
- Sau khi chạy, nút Close ở (1493, 926) khi có "Change Parameters", (1564, 926) khi không.
