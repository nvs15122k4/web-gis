# Năm 2019 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (thao tác chuột/menu/hộp thoại)

Project: `2019.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/2019/NN_*.png`.
Dữ liệu trung gian ở thư mục này. Kết quả: `shoreline_2019.gpkg`. Quy trình và tham số giống hệt năm 2024 (`../2024/BUOC.md`).

## Kết quả cuối
| Chỉ số | GUI (bản này) | Bản đối chiếu cũ |
|---|---|---|
| Ngưỡng Otsu MNDWI | 0,081 (0,0813) | 0,0708 |
| Diện tích nước liên thông lớn nhất | 11,93 km² | 11,91 km² |
| Độ dài đường bờ (sau clip vùng bờ 100 m) | 15,35 km | 15,15 km |
| Đường bờ GUI nằm trong 10 / 20 / 30 m quanh đường bờ `pipeline/data/qgis/shoreline/2019.json` | 97,5 % / 98,7 % / 99,6 % | |

## Các bước, tham số, ảnh
1. **Project mới + CRS** — Project > Properties > CRS, lọc "32648", chọn WGS 84 / UTM zone 48N; lưu `2019.qgz`. Ảnh 01.
2. **Ảnh Sentinel-2 L2A từ STAC Earth Search** (`https://earth-search.aws.element84.com/v1`, không token).
   - QGIS 3.40 không có ô lọc bbox/ngày cho STAC. Cách lọc: Data Source Manager > Vector > Protocol HTTP(S), URI
     `https://earth-search.aws.element84.com/v1/search?collections=sentinel-2-l2a&bbox=105.17,10.45,105.36,10.575&datetime=2019-01-01T00:00:00Z/2019-03-31T23:59:59Z&limit=100`
     (Protocol báo lỗi vsicurl, bấm Yes để thử lại ở chế độ "File" thì nạp được) → lớp GeoJSON 18 item (ảnh 02).
   - Edit > Select > Select Features by Expression: `"id" IN (...)` chọn đúng 9 cảnh `used=true` trong `pipeline/data/composite/scenes.json` (tile 48PWS):
     2019-01-06, 01-21, 01-31, 02-15, 02-20, 02-25, 03-02, 03-12, 03-27 (ảnh 03).
   - Asset COG `B03` (green), `B11` (swir16), `SCL` nạp bằng Data Source Manager > Raster với đường dẫn
     `/vsicurl/https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/48/P/WS/2019/<tháng>/<id>/<band>.tif` (ảnh 04 minh hoạ cho cảnh 2019-02-15);
     với 27 lớp, đường dẫn `/vsicurl/` được điền thẳng vào bảng batch ở bước 3 (không nạp từng lớp vào project để khỏi chờ tính thống kê).
3. **Vùng phân tích** — `pipeline/data/ref/binhmy_osm.json` → Reproject Layer sang EPSG:32648 (`ranh_gioi_xa_32648.gpkg`, ảnh 05) → Buffer 1000 m, dissolve
   (`vung_phan_tich_dem1000m.gpkg`, ảnh 06) và Buffer 100 m (`vung_bo_dem100m.gpkg`, ảnh 07).
   Raster > Extraction > Clip Raster by Mask Layer, **Run as Batch Process** 27 dòng (9 cảnh × B03/B11/SCL): mask = vùng 1000 m,
   Match extent = Yes, Set output resolution = Yes, 10 × 10 m; cột Input/Output điền bằng Autofill > Calculate by Expression với `@row_number` (ảnh 08–09).
   Kết quả `clip_{b03,b11,scl}_YYYYMMDD.tif`, cùng lưới 2004 × 1331. (Lần chạy đầu sai chỉ số `substr` làm đường dẫn COG hỏng — 27 dòng báo lỗi, đã sửa biểu thức và chạy lại.)
4. **Che mây SCL + composite trung vị** — GDAL Raster calculator (batch 18 dòng): A = band, B = SCL, công thức
   `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)`, NoData −9999, Float32 → `mask_{b03,b11}_YYYYMMDD.tif` (ảnh 10–11).
   Cell statistics, Statistic = Median, Ignore NoData, NoData −9999: 9 lớp B03 → `green_median_2019.tif` (ảnh 12), 9 lớp B11 → `swir16_median_2019.tif` (ảnh 13).
   Kiểm tra numpy: trùng median từng pixel (sai số 0).
5. **MNDWI** — Raster > Raster Calculator `("green_median_2019@1" - "swir16_median_2019@1") / ("green_median_2019@1" + "swir16_median_2019@1")` → `mndwi_2019.tif` (ảnh 14).
6. **Ngưỡng Otsu** — Layer Properties > Histogram (ảnh 15): hai đỉnh rõ (đất ≈ −0,4, nước ≈ +0,62), đáy rộng. QGIS 3.40 không có công cụ Otsu;
   ngưỡng Otsu tính trên đúng histogram của `mndwi_2019.tif` (1024 bin, như `qgis_interpret.py`) = **0,081**.
   GDAL Raster calculator `(A>0.081)*1`, Byte, NoData 255 → `nuoc_2019.tif` (ảnh 16).
7. **Lọc đốm, vector hoá, đường bờ**
   - GDAL Fill NoData 10 px → `nuoc_fill_2019.tif` (ảnh 17); GDAL Sieve 500 px, 4-liên thông → `nuoc_sieve_2019.tif` (ảnh 18).
   - GDAL Polygonize → `nuoc_polygon_2019.gpkg` (ảnh 19); Extract by expression `"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)` → `nuoc_lon_nhat_2019.gpkg` (ảnh 20).
   - Buffer −50 m rồi +50 m (8 segments) (ảnh 21–22), Multipart to singleparts (ảnh 23), Extract by expression `$area = maximum($area)` → `song_lien_thong_2019.gpkg` (ảnh 24),
     Delete holes < 50 000 m² → `song_lap_lo_2019.gpkg` (ảnh 25), Polygons to lines → `bien_nuoc_2019.gpkg` (ảnh 26), Clip theo `vung_bo_dem100m` → **`shoreline_2019.gpkg`** (ảnh 27).
8. **Bản đồ kết quả** — nền green median (kéo giãn 350–1400), đường bờ đỏ 0,9 mm, ranh giới xã (ảnh 28).

## Mẹo thao tác
- Khởi động QGIS không qua bộ gõ fcitx; chuỗi dài dán qua clipboard (xclip) rồi ctrl+a, ctrl+v. Không bấm Escape trong cửa sổ batch.
- Khi thêm lớp đầu tiên vào project rỗng, QGIS có thể đổi CRS dự án sang CRS của lớp đó (vd. 4326) — kiểm tra góc phải dưới và đặt lại 32648.
