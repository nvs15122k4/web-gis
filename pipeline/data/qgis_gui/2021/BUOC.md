# Năm 2021 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (thao tác chuột/menu/hộp thoại)

Project: `2021.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/2021/NN_*.png`.
Dữ liệu trung gian ở thư mục này (`clip/`, `mask/`, các `.tif`/`.gpkg`). Kết quả: `shoreline_2021.gpkg`.

## Kết quả cuối
| Chỉ số | GUI (bản này) | Bản cũ (qgis_interpret) |
|---|---|---|
| Ngưỡng Otsu MNDWI | 0,1325 | 0,1219 |
| Diện tích nước liên thông lớn nhất (sau mở hình thái, lấp lỗ) | 11,84 km² | 11,83 km² |
| Độ dài đường bờ (sau clip vùng bờ 100 m) | 15,37 km | 15,13 km |

## Các bước, tham số, ảnh
1. **Project mới + CRS** — Project > Properties > CRS = WGS 84 / UTM zone 48N (EPSG:32648); lưu `2021.qgz`. Ảnh 01.
2. **Ảnh Sentinel-2 L2A từ STAC Earth Search** (`https://earth-search.aws.element84.com/v1`, không token).
   - Browser > STAC > New STAC Connection "Element84 Earth Search" (ảnh 02–03); thử plugin STAC API Browser, URL search, URL item đều lỗi (ảnh 04, 05, 09);
     danh sách kết quả 38 cảnh theo bbox/ngày quý 1/2021 (ảnh 06–07), chọn 8 cảnh `used=true` tile 48PWS: 2021-01-15, 02-19, 02-24, 03-06, 03-11, 03-16, 03-21, 03-31 (ảnh 08).
   - Nạp asset COG `green`(B03), `swir16`(B11), `scl` của 8 cảnh (24 lớp) bằng Data Source Manager > Raster, đường dẫn `/vsicurl/https://sentinel-cogs...` (ảnh 10–11).
3. **Vùng phân tích** — Reproject `binhmy_osm` sang EPSG:32648 (ảnh 12) → Buffer 1000 m dissolve `vung_phan_tich_dem1000m.gpkg` (ảnh 13) và Buffer 100 m `vung_bo_dem100m.gpkg` (ảnh 14).
   Clip Raster by Mask Layer, Run as Batch Process 24 dòng: mask = vùng 1000 m, extent = vùng 1000 m, độ phân giải 10 × 10 m → `clip/{cg,cs,cscl}_YYYYMMDD.tif`, lưới 2004 × 1331 (ảnh 15–17).
4. **Che mây SCL + composite trung vị** — GDAL Raster calculator batch 16 dòng (B03, B11 với SCL): `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)`, Float32, NoData −9999 → `mask/mcg_*.tif`, `mask/mcs_*.tif` (ảnh 18–19).
   (Công thức bắt buộc có `A*1.0`; `-9999` với A kiểu uint16 sẽ tràn.) Kiểm tra: giá trị hợp lệ trong khoảng 1…20 368, không có giá trị tràn.
   Processing > Raster analysis > Cell statistics, Statistic = Median, Ignore NoData, NoData −9999: 8 lớp B03 → `green_median_2021.tif` (ảnh 20–21), 8 lớp B11 → `swir16_median_2021.tif` (ảnh 22–23).
5. **MNDWI** — Raster > Raster Calculator `("green_median_2021@1" - "swir16_median_2021@1") / ("green_median_2021@1" + "swir16_median_2021@1")` → `mndwi_2021.tif` (ảnh 24).
6. **Ngưỡng Otsu** — Layer Properties > Histogram (ảnh 25): hai đỉnh rõ (đất ≈ −0,25, nước ≈ +0,6), đáy rộng.
   QGIS 3.40 không có công cụ Otsu. Ngưỡng Otsu tính trên đúng histogram của `mndwi_2021.tif` (1024 bin, như `qgis_interpret.py`) = **0,1325** (nằm trong đáy histogram).
   Mặt nạ nước: GDAL Raster calculator `(A>0.1325)*1`, Byte, NoData 255 → `nuoc_2021.tif` (ảnh 26–27).
7. **Lọc đốm, vector hoá, đường bờ**
   - GDAL Fill NoData 10 px → `nuoc_fill_2021.tif` (ảnh 28–29); GDAL Sieve 500 px, 4-liên thông → `nuoc_sieve_2021.tif` (ảnh 30–31).
   - GDAL Polygonize → `nuoc_polygon_2021.gpkg` (ảnh 32–33); Extract by expression `"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)` → `nuoc_lon_nhat_2021.gpkg` (ảnh 34–35).
   - Mở hình thái dạng vector: Buffer −50 m rồi +50 m (8 segments) (ảnh 36–39), Multipart to singleparts (ảnh 40–41),
     Extract by expression `$area = maximum($area)` → `song_lien_thong_2021.gpkg` (ảnh 42–43), Delete holes < 50 000 m² → `song_lap_lo_2021.gpkg` (ảnh 44–45),
     Polygons to lines → `bien_nuoc_2021.gpkg` (ảnh 46–47), Clip theo `vung_bo_dem100m` → **`shoreline_2021.gpkg`** (ảnh 48–49).
8. **Bản đồ kết quả** — nền green median (kéo giãn 350–1400), đường bờ đỏ 0,9 mm (ảnh 50).

## Mẹo thao tác (màn hình ảo Xvfb, không WM) (màn hình ảo Xvfb, không WM)
- Khởi động QGIS KHÔNG qua bộ gõ fcitx: `env -u QT_IM_MODULE -u XMODIFIERS GTK_IM_MODULE=gtk-im-context-simple gui.sh D start`
  (nếu không, xdotool type qua Unikey biến "data" → "dât", đường dẫn sai). Script: scratchpad/start112.sh.
- Hộp thoại file là GTK: gõ thẳng đường dẫn đầy đủ vào ô Name rồi Enter.
- Phím tắt (ctrl+s, ctrl+l, ctrl+shift+p) chỉ ăn sau khi click vào canvas để lấy focus.
- Dialog Processing nhỏ: phóng to bằng `xdotool search --name "<tiêu đề>"` rồi `windowmove/windowsize`.
- Khi đổi tên lớp: click tên lớp, F2, ctrl+a, gõ tên, Enter.
- Tắt ô "Render" (góc phải dưới) khi nạp nhiều lớp COG từ mạng để QGIS không tải ảnh liên tục.
- Batch Clip: Autofill > Select from Open Layers > Select All; cột Mask/CRS/độ phân giải: đặt hàng 2 rồi Autofill > Fill Down;
  cột output: nút "…" hàng 2, gõ `.../clip/c.tif`, chọn "Fill with parameter values" theo Input layer.
