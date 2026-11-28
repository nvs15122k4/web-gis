# Năm 2025 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (thao tác chuột/menu/hộp thoại)

Project: `2025.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/2025/NN_*.png` (36 ảnh).
Dữ liệu trung gian ở thư mục này (`clip/`, `mask/`, các `.tif`/`.gpkg`). Kết quả: `shoreline_2025.gpkg`.
Làm đúng chuỗi bước và tham số của năm 2024 (xem `../2024/BUOC.md`).

## Kết quả cuối
| Chỉ số | GUI (bản này) | Bản script cũ |
|---|---|---|
| Ngưỡng Otsu MNDWI | 0,0791 | 0,0802 |
| Diện tích nước liên thông lớn nhất | 11,80 km² | 11,78 km² |
| Độ dài đường bờ (sau clip vùng bờ 100 m) | 15,12 km | 14,88 km |
| Đường bờ GUI nằm trong 10 / 20 / 30 m quanh `data/qgis/shoreline/2025.json` | 99,4 % / 99,9 % / 100 % | |

## Các bước, tham số, ảnh
1. **Project mới + CRS** — Project > Properties > CRS, lọc "32648", chọn WGS 84 / UTM zone 48N; lưu `2025.qgz`. Ảnh 01.
2. **Ảnh Sentinel-2 L2A từ STAC Earth Search** (`https://earth-search.aws.element84.com/v1`, không token).
   Browser > STAC > New STAC Connection "Element84 Earth Search" (ảnh 02), kết nối được và liệt kê collection (ảnh 03).
   QGIS 3.40 không có ô lọc bbox/ngày cho STAC (đã thử và ghi lại 4 cách thất bại ở `../2024/BUOC.md`, ảnh 03–06 của 2024), nên
   dùng đúng 7 cảnh `used=true` trong `scenes.json` (tile 48PWS): 2025-01-04, 01-19, 02-03, 02-08, 02-13, 02-28, 03-10 (ID `S2A/S2B/S2C_48PWS_YYYYMMDD_0_L2A`),
   nạp asset COG `green`(B03), `swir16`(B11), `scl` (21 đường dẫn `/vsicurl/https://sentinel-cogs.s3.us-west-2.amazonaws.com/...`)
   bằng Layer > Data Source Manager > Raster (ảnh 04–05).
3. **Vùng phân tích** — Reproject Layer `binhmy_osm.json` sang EPSG:32648 (`ranh_gioi_xa_32648.gpkg`, ảnh 06);
   Buffer 1000 m có Dissolve (`vung_phan_tich_dem1000m.gpkg`, ảnh 07), Buffer 100 m (`vung_bo_dem100m.gpkg`).
   Raster > Extraction > Clip Raster by Mask Layer, Run as Batch Process 21 dòng: mask = vùng 1000 m, Target extent = extent lớp vùng 1000 m,
   Set output resolution = Yes 10 × 10 m; cột Input/Output điền bằng Autofill > Calculate by Expression với `@row_number` (ảnh 08–11).
   Kết quả `clip/YYYYMMDD_{B03,B11,SCL}.tif`, cùng lưới 2004 × 1331.
4. **Che mây SCL + composite trung vị** — GDAL Raster calculator (batch 14 dòng): A = band, B = SCL,
   `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)`, NoData −9999, Float32 → `mask/*_masked.tif` (ảnh 12–16).
   Cell statistics, Statistic = Median, Ignore NoData, lớp tham chiếu = một ảnh `mask/*_B03_masked.tif` (cùng lưới 10 m):
   7 lớp B03 → `green_median_2025.tif`, 7 lớp B11 → `swir16_median_2025.tif` (ảnh 17–19).
5. **MNDWI** — Raster > Raster Calculator: `("green_median_2025@1" - "swir16_median_2025@1") / ("green_median_2025@1" + "swir16_median_2025@1")` → `mndwi_2025.tif` (ảnh 20).
6. **Ngưỡng Otsu** — Layer Properties > Histogram (ảnh 21): hai đỉnh rõ (đất ≈ −0,45, nước ≈ +0,65), đáy rộng.
   QGIS 3.40 không có công cụ Otsu (tìm "otsu", "thresh" trong Processing: không có). Ngưỡng Otsu được tính trên đúng histogram của
   `mndwi_2025.tif` (1024 bin, như `qgis_interpret.py`; bằng script nhỏ ngoài QGIS, giống năm 2024) = **0,0791**.
   Mặt nạ nước: GDAL Raster calculator `(A>0,0791)*1`, Byte, NoData 255 → `nuoc_2025.tif` (ảnh 22).
7. **Lọc đốm, vector hoá, đường bờ**
   - GDAL Fill NoData (10 px) → `nuoc_fill_2025.tif` (ảnh 23); GDAL Sieve 500 px, 4-liên thông → `nuoc_sieve_2025.tif` (ảnh 24).
   - GDAL Polygonize → `nuoc_polygon_2025.gpkg` (ảnh 25–26); Extract by expression `"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)` → `nuoc_lon_nhat_2025.gpkg` (ảnh 27).
   - Mở hình thái học dạng vector: Buffer −50 m rồi +50 m (8 segments) (ảnh 28–29), Multipart to singleparts (ảnh 30),
     Extract by expression `$area = maximum($area)` → `song_lien_thong_2025.gpkg` (ảnh 31),
     Delete holes < 50 000 m² → `song_lap_lo_2025.gpkg` (ảnh 32), Polygons to lines → `bien_nuoc_2025.gpkg` (ảnh 33),
     Clip theo `vung_bo_dem100m` → **`shoreline_2025.gpkg`** (ảnh 34–35).
8. **Bản đồ kết quả** — nền green median (kéo giãn 350–1400), đường bờ đỏ 0,9 mm (ảnh 36).

## Ghi chú
- Không làm lại hai lần Polygons to lines + Clip "lần đầu" như 2024 (đường bờ 44,9 km do còn kênh rạch); đi thẳng chuỗi đã sửa.
- Mẹo thao tác (xdotool, fcitx, batch, Escape...): xem `../2024/BUOC.md`.
