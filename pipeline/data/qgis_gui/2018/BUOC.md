# Năm 2018 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (thao tác chuột/menu/hộp thoại)

Project: `2018.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/2018/NN_*.png`.
Dữ liệu trung gian ở thư mục này (`clip_*.tif`, `mask_*.tif`, các `.tif`/`.gpkg`). Kết quả: `shoreline_2018.gpkg`.
Quy trình và tham số giống hệt năm 2024 (xem `../2024/BUOC.md`).

## Kết quả cuối
| Chỉ số | GUI (bản này) | Bản đối chiếu cũ |
|---|---|---|
| Ngưỡng Otsu MNDWI | 0,178 (0,1777) | 0,1687 |
| Diện tích nước liên thông lớn nhất | 11,91 km² | 11,92 km² |
| Độ dài đường bờ (sau clip vùng bờ 100 m) | 15,28 km | 15,37 km |
| Đường bờ GUI nằm trong 10 / 20 / 30 m quanh đường bờ `pipeline/data/qgis/shoreline/2018.json` | 97,0 % / 97,4 % / 97,9 % | |

## Các bước, tham số, ảnh
1. **Project mới + CRS** — Project > Properties > CRS, chọn WGS 84 / UTM zone 48N (EPSG:32648); lưu `2018.qgz`. Ảnh 01.
2. **Ảnh Sentinel-2 L2A từ STAC Earth Search** (`https://earth-search.aws.element84.com/v1`, không token).
   - Browser > STAC > New STAC Connection "Earth Search AWS": liệt kê collection (ảnh 02). QGIS 3.40 không có ô lọc bbox/ngày cho STAC.
   - Cách lọc đã dùng: Data Source Manager > Vector > Protocol HTTP(S), URI
     `https://earth-search.aws.element84.com/v1/search?collections=sentinel-2-l2a&bbox=105.17,10.45,105.36,10.575&datetime=2018-01-01T00:00:00Z/2018-03-31T23:59:59Z&limit=100`
     → lớp GeoJSON gồm 23 item (ảnh 03–07); mở bảng thuộc tính, chọn đúng 4 cảnh `used=true` trong `pipeline/data/composite/scenes.json` (tile 48PWS):
     2018-01-01, 02-05, 02-10, 03-27 (ảnh 08).
   - Nạp asset COG `green`(B03), `swir16`(B11), `scl` của 4 cảnh bằng Data Source Manager > Raster, ô "Raster dataset(s)" chứa đường dẫn
     `/vsicurl/https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/48/P/WS/2018/...` (ảnh 09–10). 12 lớp.
     DN của COG Earth Search đã bỏ offset BOA — MNDWI là tỉ số nên không cần nhân 1e-4.
3. **Vùng phân tích** — `binhmy_osm.json` → Vector > Data Management > Reproject Layer sang EPSG:32648 (ảnh 11) → Buffer 1000 m, dissolve
   (`vung_phan_tich_dem1km.gpkg`, ảnh 12) và Buffer 100 m (`vung_bo_dem100m.gpkg`, ảnh 13).
   Raster > Extraction > Clip Raster by Mask Layer, **Run as Batch Process** 12 dòng: mask = vùng 1000 m, Target extent = extent vùng 1000 m,
   Set output resolution = Yes, 10 × 10 m, cột Input/Output điền bằng Autofill > Calculate by Expression với `@row_number` (ảnh 14–15).
   Kết quả `clip_{b03,b11,scl}_YYYYMMDD.tif`, cùng lưới 2004 × 1331.
4. **Che mây SCL + composite trung vị** — Processing > GDAL > Raster calculator (batch 8 dòng): A = band, B = SCL,
   công thức `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)`, NoData −9999, Float32 → `mask_{b03,b11}_YYYYMMDD.tif` (ảnh 16–17).
   (Bản làm dở trước đó dùng `-9999` không có `A*1.0` nên A kiểu uint16 bị tràn thành 55537 — đã xóa toàn bộ kết quả hỏng và làm lại từ bước này.)
   Processing > Raster analysis > Cell statistics, Statistic = Median, Ignore NoData, NoData −9999: 4 lớp B03 → `green_median_2018.tif` (ảnh 18),
   4 lớp B11 → `swir16_median_2018.tif` (ảnh 19–20). Kiểm tra lại bằng numpy: trùng median từng pixel của 4 lớp mask (sai số 0).
5. **MNDWI** — Raster > Raster Calculator:
   `("green_median_2018@1" - "swir16_median_2018@1") / ("green_median_2018@1" + "swir16_median_2018@1")` → `mndwi_2018.tif` (ảnh 21).
   Lưu ý: sau khi ghi đè file đầu ra phải gỡ lớp cũ khỏi project rồi nạp lại, nếu không QGIS dùng dữ liệu cache cũ (lần đầu cho ra 92 253 pixel bằng 0 sai).
6. **Ngưỡng Otsu** — Layer Properties > Histogram của `mndwi_2018.tif` (ảnh 22): hai đỉnh rõ (đất ≈ −0,3, nước ≈ +0,7), đáy rộng 0…0,5.
   QGIS 3.40 không có công cụ Otsu. Ngưỡng Otsu tính trên đúng histogram của `mndwi_2018.tif` (1024 bin, như `qgis_interpret.py`) = **0,178**.
   Mặt nạ nước: GDAL Raster calculator `(A>0.178)*1`, Byte, NoData 255 → `nuoc_2018.tif` (ảnh 23).
7. **Lọc đốm, vector hoá, đường bờ**
   - GDAL Fill NoData (10 px) → `nuoc_fill_2018.tif` (ảnh 24); GDAL Sieve 500 px, 4-liên thông → `nuoc_sieve_2018.tif` (ảnh 25).
   - GDAL Polygonize → `nuoc_polygon_2018.gpkg` (ảnh 26); Extract by expression `"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)` → `nuoc_lon_nhat_2018.gpkg` (ảnh 27).
   - Mở hình thái học dạng vector: Buffer −50 m rồi +50 m (8 segments) (ảnh 28–29), Multipart to singleparts (ảnh 30),
     Extract by expression `$area = maximum($area)` → `song_lien_thong_2018.gpkg` (ảnh 31), Delete holes < 50 000 m² → `song_lap_lo_2018.gpkg` (ảnh 32),
     Polygons to lines → `bien_nuoc_2018.gpkg` (ảnh 33), Clip theo `vung_bo_dem100m` → **`shoreline_2018.gpkg`** (ảnh 34).
8. **Bản đồ kết quả** — nền green median (kéo giãn 350–1400), đường bờ đỏ 0,9 mm, ranh giới xã (ảnh 35).

## Mẹo thao tác (màn hình ảo, xdotool)
- Khởi động QGIS không qua bộ gõ fcitx (`env -u QT_IM_MODULE -u XMODIFIERS GTK_IM_MODULE=gtk-im-context-simple gui.sh D start`); chuỗi dài/dấu: dán qua clipboard (xclip) rồi ctrl+v.
- Batch: cột Input/Output điền bằng Autofill > Calculate by Expression; `@row_number` bắt đầu từ 0. Không bấm Escape trong cửa sổ batch.
- Hộp "Input layers" của Cell statistics sắp xếp lại sau mỗi lần nạp lớp mới — luôn chụp màn hình kiểm tra các ô được tích trước khi chạy.
