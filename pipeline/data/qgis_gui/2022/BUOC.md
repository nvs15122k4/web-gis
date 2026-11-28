# Năm 2022 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (thao tác chuột/menu/hộp thoại)

Project: `2022.qgz` (CRS dự án EPSG:32648). Ảnh minh chứng: `docs/minh_chung/qgis/2022/NN_*.png`.
Dữ liệu trung gian ở thư mục này (`clip/`, `mask/`, các `.tif`/`.gpkg`). Kết quả: `shoreline_2022.gpkg`.

## Kết quả cuối
| Chỉ số | GUI (bản này) | Bản cũ (qgis_interpret) |
|---|---|---|
| Ngưỡng Otsu MNDWI | 0,1067 | 0,1200 |
| Diện tích nước liên thông lớn nhất (sau mở hình thái, lấp lỗ) | 11,69 km² | 11,82 km² |
| Độ dài đường bờ (sau clip vùng bờ 100 m) | 15,68 km | 15,17 km |

## Các bước, tham số, ảnh
1. **Project mới + CRS** — Project > Properties > CRS = WGS 84 / UTM zone 48N (EPSG:32648); lưu `2022.qgz`. Ảnh 01.
2. **Ảnh Sentinel-2 L2A từ STAC Earth Search** (`https://earth-search.aws.element84.com/v1`, không token).
   - Browser > STAC > New STAC Connection "Element84 Earth Search" (ảnh 02), liệt kê collection (ảnh 03). QGIS 3.40 không có ô lọc bbox/ngày cho STAC
     (đã thử và ghi nhận ở năm 2024: URL search, plugin STAC API Browser, URL item đều lỗi), nên danh sách item được lấy bằng truy vấn STAC search (bbox 105,17–105,36 E / 10,45–10,575 N, quý 1/2022)
     và dùng đúng 5 cảnh `used=true` của tile 48PWS trong `scenes.json`: 2022-01-10, 01-20, 02-04, 03-01, 03-11 (ID `S2B_48PWS_20220110_0_L2A`, `S2B_..20220120`, `S2A_..20220204`, `S2B_..20220301`, `S2B_..20220311`).
   - Nạp thử asset COG `B03`, `B11`, `SCL` của cảnh 2022-01-10 vào project bằng Data Source Manager > Raster, đường dẫn `/vsicurl/https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/48/P/WS/2022/1/S2B_48PWS_20220110_0_L2A/...` (ảnh 04–05);
     với batch gõ thẳng đường dẫn `/vsicurl/` cho cả 15 lớp (nạp từng lớp từ xa rất chậm).
3. **Vùng phân tích** — Reproject `binhmy_osm.json` sang EPSG:32648 (`ranh_gioi_xa_32648.gpkg`, ảnh 06–07) → Buffer 1000 m dissolve `vung_phan_tich_dem1000m.gpkg` (ảnh 08–09) và Buffer 100 m dissolve `vung_bo_dem100m.gpkg` (ảnh 10–11).
   Clip Raster by Mask Layer, Run as Batch Process 15 dòng (B03, B11, SCL × 5 cảnh): Input = `array_get(array(...),@row_number)` chứa đường dẫn `/vsicurl/`, mask = vùng 1000 m,
   độ phân giải 10 × 10 m, output `clip/{cg,cs,cscl}_YYYYMMDD.tif`, lưới 2004 × 1331 (ảnh 12–13).
4. **Che mây SCL + composite trung vị** — GDAL Raster calculator batch 10 dòng (A = B03/B11, B = SCL): `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)`, Float32, NoData −9999 → `mask/mcg_*.tif`, `mask/mcs_*.tif` (ảnh 14–15).
   - **Hiệu chỉnh offset BOA cho cảnh 2022-02-04**: thuộc tính STAC `earthsearch:boa_offset_applied = False` (processing baseline 04.00, DN còn cộng 1000), các cảnh còn lại đã bỏ offset (percentile 1 % của B03: 1436 so với 424–518 ở cảnh khác).
     Chạy lại GDAL Raster calculator batch 2 dòng cho cảnh này với công thức `where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0-1000.0)` ghi đè `mcg_20220204.tif`, `mcs_20220204.tif` (ảnh 21–22). Nếu không hiệu chỉnh, Otsu = −0,045 và diện tích nước 18,8 km² (sai).
   - Processing > Raster analysis > Cell statistics, Statistic = Median, Ignore NoData, NoData −9999: 5 lớp B03 → `green_median_2022.tif` (ảnh 16–17), 5 lớp B11 → `swir16_median_2022.tif` (ảnh 18–19).
     (Cell statistics và MNDWI được chạy lại sau bước hiệu chỉnh offset; ảnh 16–20 là bản chạy lại.)
5. **MNDWI** — Raster > Raster Calculator `("green_median_2022@1" - "swir16_median_2022@1") / ("green_median_2022@1" + "swir16_median_2022@1")` → `mndwi_2022.tif` (ảnh 20).
6. **Ngưỡng Otsu** — Layer Properties > Histogram (ảnh 23): đỉnh đất ≈ −0,35, đỉnh nước nhỏ ≈ +0,6.
   QGIS 3.40 không có công cụ Otsu. Ngưỡng Otsu tính trên đúng histogram của `mndwi_2022.tif` (1024 bin, như `qgis_interpret.py`) = **0,1067**.
   Mặt nạ nước: GDAL Raster calculator `(A>0.1067)*1`, Byte, NoData 255 → `nuoc_2022.tif` (ảnh 24–25).
7. **Lọc đốm, vector hoá, đường bờ**
   - GDAL Fill NoData 10 px → `nuoc_fill_2022.tif` (ảnh 26–27); GDAL Sieve 500 px, 4-liên thông → `nuoc_sieve_2022.tif` (ảnh 28–29).
   - GDAL Polygonize → `nuoc_polygon_2022.gpkg` (ảnh 30–31); Extract by expression `"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)` → `nuoc_lon_nhat_2022.gpkg` (ảnh 32–33).
   - Mở hình thái dạng vector: Buffer −50 m rồi +50 m (8 segments) (ảnh 34–37), Multipart to singleparts (ảnh 38–39),
     Extract by expression `$area = maximum($area)` → `song_lien_thong_2022.gpkg` (ảnh 40–41), Delete holes < 50 000 m² → `song_lap_lo_2022.gpkg` (ảnh 42–43),
     Polygons to lines → `bien_nuoc_2022.gpkg` (ảnh 44–45), Clip theo `vung_bo_dem100m` → **`shoreline_2022.gpkg`** (ảnh 46–47).
8. **Bản đồ kết quả** — nền green median (kéo giãn 350–1400), đường bờ đỏ 0,9 mm (ảnh 48).

## Mẹo thao tác (màn hình ảo Xvfb, không WM) (màn hình ảo Xvfb, không WM) (màn hình ảo Xvfb, không WM)
- Khởi động QGIS KHÔNG qua bộ gõ fcitx: `env -u QT_IM_MODULE -u XMODIFIERS GTK_IM_MODULE=gtk-im-context-simple gui.sh D start`
  (nếu không, xdotool type qua Unikey biến "data" → "dât", đường dẫn sai). Script: scratchpad/start112.sh.
- Hộp thoại file là GTK: gõ thẳng đường dẫn đầy đủ vào ô Name rồi Enter.
- Phím tắt (ctrl+s, ctrl+l, ctrl+shift+p) chỉ ăn sau khi click vào canvas để lấy focus.
- Dialog Processing nhỏ: phóng to bằng `xdotool search --name "<tiêu đề>"` rồi `windowmove/windowsize`.
- Khi đổi tên lớp: click tên lớp, F2, ctrl+a, gõ tên, Enter.
- Tắt ô "Render" (góc phải dưới) khi nạp nhiều lớp COG từ mạng để QGIS không tải ảnh liên tục.
- Batch Clip: Autofill > Select from Open Layers > Select All; cột Mask/CRS/độ phân giải: đặt hàng 2 rồi Autofill > Fill Down;
  cột output: nút "…" hàng 2, gõ `.../clip/c.tif`, chọn "Fill with parameter values" theo Input layer.
