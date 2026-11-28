@php
    $img = '/img/qgis/';
    /*
     * Mỗi bước: hình chính (cột phải, dính khi cuộn), 2 đoạn tóm tắt và phần "Chi tiết kỹ thuật" (details/summary, không cần JS).
     * Mọi bước làm bằng giao diện QGIS 3.40; số liệu lấy từ pipeline/data/qgis_gui/<năm>/BUOC.md, giai_doan2/BUOC.md và export/*.geojson.
     * Test AboutStepsTest đối chiếu lại từng số với các file nguồn đó.
     * 'more' = [Đầu vào/đầu ra, Thông số, Công thức/thuật toán, Kiểm tra chất lượng]; 'figs' = ảnh chụp phụ trong phần chi tiết.
     */
    $steps = [
        [
            'id' => 'buoc-1', 'title' => 'Nạp ảnh Sentinel-2 từ kho STAC', 'tag' => 'Data Source Manager · STAC Earth Search',
            'img' => 'a1-stac-them-band.webp', 'w' => 1600, 'h' => 894,
            'alt' => 'Ảnh chụp màn hình QGIS: hộp thoại Data Source Manager nạp các band B03, B11 và lớp phân loại cảnh SCL của ảnh Sentinel-2 L2A từ kho STAC.',
            'cap' => 'Thêm các band ảnh Sentinel-2 L2A từ kho STAC vào QGIS bằng Data Source Manager.',
            'body' => [
                'Dự án QGIS đặt hệ toạ độ EPSG:32648 (UTM 48N). Ảnh lấy từ kho STAC Earth Search (earth-search.aws.element84.com/v1, không cần khoá truy cập) qua Browser &gt; STAC &gt; New STAC Connection. Mỗi cảnh nạp ba lớp COG: B03 (xanh lục), B11 (SWIR1) và SCL (phân loại cảnh).',
                'QGIS 3.40 không có ô lọc theo khung bao hay ngày cho kết nối STAC (đã thử bốn cách, đều lỗi hoặc không lọc được), nên mỗi năm chọn đúng các cảnh đã lọc mây từ trước: 66 trong 126 cảnh mùa khô tháng 1–3 của 9 năm, từ 4 cảnh (2018) đến 13 cảnh (2020).',
            ],
            'more' => [
                'io' => 'Vào: kho STAC Earth Search, collection <code>sentinel-2-l2a</code>, tile 48PWS. Ra: các lớp raster trong dự án (đường dẫn <code>/vsicurl/…</code> tới COG), 3 lớp mỗi cảnh. Dữ liệu từng năm nằm ở <code>pipeline/data/qgis_gui/&lt;năm&gt;/</code>, kèm file ghi thao tác <code>BUOC.md</code>.',
                'params' => 'Số cảnh dùng mỗi năm: 2018: 4; 2019: 9; 2020: 13; 2021: 8; 2022: 5; 2023: 7; 2024: 7; 2025: 7; 2026: 6. Tiêu chí chọn cảnh: tỉ lệ pixel bị che theo lớp SCL không quá 30% trong vùng tải. Lớp SCL bị che: 0, 1, 3, 8, 9, 10, 11 (không dữ liệu, bão hoà, bóng mây, mây trung bình, mây cao, mây ti, tuyết).',
                'method' => 'Chọn cảnh bằng cách nạp từng nhóm đường dẫn COG trong Data Source Manager &gt; Raster; nạp từ xa từng lớp rất chậm nên các bước sau chạy hàng loạt (Batch) với đường dẫn điền bằng Autofill &gt; Calculate by Expression.',
                'qa' => 'Cảnh dùng cho 2026 là 09/01, 19/01, 03/02, 10/03, 15/03 và 25/03. Năm 2022 có một cảnh (04/02/2022, processing baseline 04.00) còn cộng thêm 1000 vào giá trị số; cảnh này được trừ lại ở bước 3. Nếu không trừ, ngưỡng nước năm đó lệch hẳn (−0,045) và diện tích nước thành 18,8 km², là kết quả sai.',
            ],
            'figs' => [],
        ],
        [
            'id' => 'buoc-2', 'title' => 'Vùng phân tích và cắt ảnh', 'tag' => 'Reproject · Buffer · Clip Raster by Mask Layer',
            'img' => 'a2-batch-clip.webp', 'w' => 1600, 'h' => 894,
            'alt' => 'Ảnh chụp màn hình QGIS: hộp thoại Clip Raster by Mask Layer chạy hàng loạt (Batch Process) cho nhiều band ảnh.',
            'cap' => 'Cắt ảnh theo vùng phân tích bằng Clip Raster by Mask Layer chạy hàng loạt (Batch).',
            'body' => [
                'Ranh giới xã Bình Mỹ (OpenStreetMap) được đổi sang EPSG:32648 bằng Reproject Layer. Buffer 1.000 m (có Dissolve) cho vùng phân tích; Buffer 100 m cho vùng bờ dùng để cắt đường bờ ở bước 5.',
                'Clip Raster by Mask Layer chạy dạng Batch Process cắt mọi band theo vùng 1.000 m, ép về lưới 10 m, nên mọi cảnh và mọi năm cùng một lưới 2.004 × 1.331 pixel.',
            ],
            'more' => [
                'io' => 'Vào: ranh giới xã và các lớp raster ở bước 1. Ra: <code>ranh_gioi_xa_32648.gpkg</code>, <code>vung_phan_tich_dem1000m.gpkg</code>, <code>vung_bo_dem100m.gpkg</code> và ảnh đã cắt <code>clip/YYYYMMDD_&lt;band&gt;.tif</code> trong thư mục từng năm.',
                'params' => 'CRS: EPSG:32648. Buffer 1.000 m và 100 m có Dissolve. Clip Raster by Mask Layer: mask = vùng 1.000 m, Target extent = phạm vi lớp vùng 1.000 m, Set output resolution = 10 × 10 m (B11 và SCL gốc 20 m được lấy mẫu láng giềng gần nhất về 10 m). Số dòng Batch mỗi năm bằng 3 band × số cảnh, ví dụ 12 dòng (2018), 27 (2019), 39 (2020), 18 (2026).',
                'method' => 'Batch Process điền cột Input và Output bằng Autofill &gt; Calculate by Expression với biến <code>@row_number</code>, nên một lần chạy xử lý hết các band của cả năm.',
                'qa' => 'Nhật ký của Processing báo hoàn tất từng dòng; ảnh sau cắt của mọi năm có cùng kích thước 2.004 × 1.331, nên xếp chồng được ở bước tiếp theo.',
            ],
            'figs' => [
                ['s2-buffer-1000m.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại Buffer với khoảng cách 1.000 m và tuỳ chọn Dissolve, tạo vùng phân tích quanh ranh giới xã.', 'Buffer 1.000 m có Dissolve quanh ranh giới xã để làm vùng phân tích.'],
            ],
        ],
        [
            'id' => 'buoc-3', 'title' => 'Che mây và gộp composite trung vị', 'tag' => 'GDAL Raster Calculator · Cell Statistics',
            'img' => 'a3-che-may-scl.webp', 'w' => 1600, 'h' => 894,
            'alt' => 'Ảnh chụp màn hình QGIS: GDAL Raster Calculator che mây và bóng mây theo lớp SCL, chạy hàng loạt.',
            'cap' => 'Che mây và bóng mây theo lớp SCL bằng GDAL Raster Calculator (Batch).',
            'body' => [
                'GDAL Raster Calculator chạy Batch đặt pixel mây, bóng mây và pixel không hợp lệ thành NoData (−9999) theo lớp SCL. Sau đó Cell Statistics lấy trung vị các cảnh của năm, bỏ qua NoData, riêng cho B03 và B11.',
                'Kết quả mỗi năm là hai ảnh trung vị: <code>green_median_&lt;năm&gt;.tif</code> và <code>swir16_median_&lt;năm&gt;.tif</code>. Ảnh trung vị loại bỏ mây còn sót ở một cảnh nhờ các cảnh còn lại.',
            ],
            'more' => [
                'io' => 'Vào: ảnh đã cắt ở bước 2. Ra: <code>mask/*_masked.tif</code> (Float32, NoData −9999) và <code>green_median_&lt;năm&gt;.tif</code>, <code>swir16_median_&lt;năm&gt;.tif</code> cùng lưới 10 m.',
                'params' => 'Biểu thức GDAL Raster Calculator: <code>where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)</code> với A là band, B là SCL; kiểu Float32. Lần chạy đầu dùng A kiểu số nguyên không dấu nên giá trị −9999 bị tràn thành 55537; phải nhân <code>A*1.0</code> để ép kiểu thực. Cell Statistics: Statistic = Median, Ignore NoData, lớp tham chiếu là một ảnh đã che cùng lưới.',
                'method' => 'Trung vị theo pixel của các quan sát không bị che, theo công thức ở trên. Với cảnh 04/02/2022, biểu thức đổi thành <code>A*1.0-1000.0</code> để bỏ phần cộng 1000.',
                'qa' => 'Số lớp đầu vào của Cell Statistics bằng số cảnh của năm (ví dụ 6 lớp B03 và 6 lớp B11 cho 2026). Nhật ký xử lý của Processing báo hoàn tất từng lần chạy.',
            ],
            'figs' => [
                ['a4-median.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: Cell Statistics với Statistic là Median, gộp các lớp B03 đã che mây thành ảnh trung vị.', 'Gộp các cảnh B03 đã che mây thành ảnh trung vị bằng Cell Statistics (Median).'],
            ],
        ],
        [
            'id' => 'buoc-4', 'title' => 'Chỉ số nước MNDWI và ngưỡng nước', 'tag' => 'Raster Calculator · Histogram',
            'img' => 'a5-mndwi.webp', 'w' => 1600, 'h' => 894,
            'alt' => 'Ảnh chụp màn hình QGIS: Raster Calculator tính chỉ số nước MNDWI từ hai ảnh trung vị xanh lục và SWIR1.',
            'cap' => 'Tính MNDWI = (Green − SWIR1) / (Green + SWIR1) trong Raster Calculator.',
            'body' => [
                'Raster Calculator tính MNDWI từ hai ảnh trung vị. Nước có MNDWI cao, đất có MNDWI thấp. Biểu đồ phân bố (Layer Properties &gt; Histogram) có hai đỉnh, đất quanh −0,45 và nước quanh +0,65 ở năm 2026, ngưỡng nằm ở đáy giữa hai đỉnh.',
                'QGIS 3.40 không có công cụ Otsu. Vì vậy ngưỡng của từng năm được tính trên đúng histogram của ảnh MNDWI năm đó (1.024 bin) rồi nhập vào Raster Calculator; trong QGIS vẫn mở được histogram để đối chiếu bằng mắt. Mặt nạ nước là <code>(A&gt;ngưỡng)*1</code>.',
            ],
            'more' => [
                'io' => 'Vào: <code>green_median</code> và <code>swir16_median</code> của năm. Ra: <code>mndwi_&lt;năm&gt;.tif</code> (Float32) và mặt nạ nước <code>nuoc_&lt;năm&gt;.tif</code> (Byte, 1 nước, 0 đất, NoData 255).',
                'params' => 'Ngưỡng nước MNDWI từng năm: 2018: 0,178; 2019: 0,081; 2020: 0,145; 2021: 0,1325; 2022: 0,1067; 2023: 0,1423; 2024: 0,039; 2025: 0,0791; 2026: 0,1149. Công thức Raster Calculator: <code>("green_median_2026@1" - "swir16_median_2026@1") / ("green_median_2026@1" + "swir16_median_2026@1")</code>. Mặt nạ năm 2026: GDAL Raster Calculator <code>(A&gt;0,1149)*1</code>, kiểu Byte.',
                'method' => 'Ngưỡng Otsu chọn giá trị làm cực đại phương sai giữa hai lớp của histogram (công thức ở trên). Giá trị được tính ngoài giao diện QGIS vì QGIS không có công cụ này; kết quả luôn nằm trong đáy giữa hai đỉnh khi xem lại bằng Histogram. MNDWI được chọn thay NDWI và AWEI sau khi so với ground truth số hoá thủ công (xem bước 9).',
                'qa' => 'Ngưỡng ở mọi năm nằm trong đáy histogram, từ 0,039 (2024) đến 0,178 (2018). Ngưỡng đổi theo năm nên một phần chênh lệch giữa các năm có thể do ngưỡng, không do bờ dịch chuyển (xem hạn chế bên dưới).',
            ],
            'figs' => [
                ['a6-histogram.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: biểu đồ phân bố MNDWI trong Layer Properties, hai đỉnh đất và nước, ngưỡng chọn ở đáy giữa hai đỉnh.', 'Histogram MNDWI trong QGIS: ngưỡng nước nằm ở đáy giữa đỉnh đất và đỉnh nước.'],
                ['s5-mat-na-nuoc.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại GDAL Raster Calculator tạo mặt nạ nước từ ảnh MNDWI với ngưỡng 0,1149.', 'Tạo mặt nạ nước bằng GDAL Raster Calculator với ngưỡng 0,1149 (năm 2026).'],
            ],
        ],
        [
            'id' => 'buoc-5', 'title' => 'Mặt nạ nước, làm sạch và đường bờ', 'tag' => 'Fill NoData · Sieve · Polygonize · Buffer · Clip',
            'img' => 'c1-ban-do-9-duong-bo.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ xuất từ Print Layout của QGIS: chín đường bờ sông Hậu năm 2018 đến 2026, mỗi năm một màu, chồng trên ảnh trung vị.',
            'cap' => 'Chín đường bờ 2018–2026 chồng trên ảnh nền, xuất bằng Print Layout của QGIS.',
            'body' => [
                'Mặt nạ nước được làm sạch bằng GDAL Fill NoData và GDAL Sieve (500 pixel), vector hoá bằng GDAL Polygonize, rồi giữ vùng nước lớn nhất (Extract by expression). Tiếp theo mở hình thái ở dạng vector: Buffer −50 m rồi +50 m để bỏ kênh rạch hẹp dưới khoảng 100 m, giữ thành phần liên thông lớn nhất và lấp lỗ nhỏ hơn 5 ha (Delete holes).',
                'Polygons to Lines lấy ranh giới vùng nước, Clip theo vùng bờ 100 m cho ra đường bờ của năm. Chín năm có chiều dài từ 15,10 đến 15,68 km; vùng nước liên thông lớn nhất rộng 11,69 đến 11,93 km².',
            ],
            'more' => [
                'io' => 'Vào: <code>nuoc_&lt;năm&gt;.tif</code>. Ra: <code>nuoc_polygon</code>, <code>nuoc_lon_nhat</code>, <code>song_lien_thong</code>, <code>song_lap_lo</code>, <code>bien_nuoc</code> và đường bờ cuối <code>shoreline_&lt;năm&gt;.gpkg</code> (EPSG:32648) trong <code>pipeline/data/qgis_gui/&lt;năm&gt;/</code>.',
                'params' => 'GDAL Fill NoData: khoảng cách tìm 10 pixel. GDAL Sieve: ngưỡng 500 pixel, liên thông 4. Extract by expression: <code>"DN" = 1 AND $area = maximum($area, filter:="DN" = 1)</code>. Buffer −50 m rồi +50 m (8 đoạn mỗi góc). Multipart to singleparts rồi Extract by expression <code>$area = maximum($area)</code>. Delete holes: diện tích nhỏ hơn 50.000 m² (5 ha). Clip theo <code>vung_bo_dem100m</code>. Độ dài đường bờ (km): 2018: 15,28; 2019: 15,35; 2020: 15,12; 2021: 15,37; 2022: 15,68; 2023: 15,10; 2024: 15,62; 2025: 15,12; 2026: 15,22.',
                'method' => 'Mở hình thái học dạng vector (Buffer âm rồi dương) bỏ cấu trúc hẹp hơn 100 m, nên ao nuôi, kênh nội đồng và nhiễu nhỏ không thành đường bờ; hệ quả là kênh rộng dưới khoảng 100 m không được theo dõi. Lần thử đầu của năm 2024 (không mở hình thái, không lấp lỗ) cho đường bờ dài 44,9 km vì còn bờ các kênh nối vào sông, nên chuỗi cuối đã thêm các bước này.',
                'qa' => 'So với đường bờ tính trước đó, đường bờ dựng bằng giao diện nằm trong 20 m ở 96,8% (2024) đến 100% (2026) chiều dài; chỉ số này chỉ ghi cho các năm 2018–2020 và 2024–2026. Chiều dài chênh bản trước từ −0,15 đến +0,77 km tuỳ năm.',
            ],
            'figs' => [
                ['a7-polygonize.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại GDAL Polygonize vector hoá mặt nạ nước thành vùng nước.', 'Vector hoá mặt nạ nước bằng GDAL Polygonize.'],
                ['s6-sieve-500.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại GDAL Sieve với ngưỡng 500 pixel để lọc đốm nhỏ trên mặt nạ nước.', 'Lọc đốm nhỏ bằng GDAL Sieve, ngưỡng 500 pixel.'],
                ['s6-buffer-am50.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại Buffer với khoảng cách âm 50 m, bước đầu của mở hình thái học dạng vector.', 'Buffer −50 m, bước đầu của mở hình thái học dạng vector (sau đó Buffer +50 m).'],
                ['s6-delete-holes.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại Delete holes lấp các lỗ nhỏ hơn 50.000 mét vuông trong vùng nước.', 'Lấp lỗ nhỏ hơn 5 ha trong vùng nước bằng Delete holes.'],
                ['s6-clip-vung-bo.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại Clip cắt đường bờ theo vùng bờ đệm 100 mét.', 'Cắt đường bờ theo vùng bờ đệm 100 m bằng Clip.'],
                ['a8-duong-bo-2026.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: đường bờ năm 2026 màu đỏ trên ảnh trung vị xanh lục, kết quả cuối của chuỗi giải đoán.', 'Đường bờ năm 2026 (đỏ) trên ảnh trung vị, kết quả cuối của chuỗi giải đoán.'],
            ],
        ],
        [
            'id' => 'buoc-6', 'title' => 'Baseline và transect', 'tag' => '270 transect · Offset lines · Geometry by expression',
            'img' => 'b2-transect.webp', 'w' => 1600, 'h' => 894,
            'alt' => 'Ảnh chụp màn hình QGIS: các transect vuông góc với baseline, dài 600 m, cách nhau 50 m, cắt các đường bờ.',
            'cap' => 'Transect vuông góc baseline, cách nhau 50 m, dài 600 m, dựng bằng Points along geometry và Geometry by expression.',
            'body' => [
                'Baseline lấy từ đường bờ 2018: giữ đoạn dài nhất, Simplify 150 m, Smooth 4 lần, rồi Offset lines +250 m sang phía đất (phía đất xác định bằng cách xem bản đồ). Points along geometry đặt 270 điểm cách nhau 50 m, bắt đầu lệch 25 m.',
                'Từ mỗi điểm, Geometry by expression dựng đoạn thẳng dài 600 m vuông góc hướng baseline, hướng ra sông. Extract by expression với <code>overlay_crosses</code> kiểm tra transect cắt nhau: không có transect nào phải bỏ, nên còn 270 transect.',
            ],
            'more' => [
                'io' => 'Vào: <code>shoreline_2018.gpkg</code>. Ra: <code>bl_1…bl_4_*.gpkg</code>, <code>baseline.gpkg</code> và <code>transects.gpkg</code> (270 transect, mã T0001…T0270, <code>chainage_m</code> từ 25 đến 13.475 m) trong <code>pipeline/data/qgis_gui/giai_doan2/</code>.',
                'params' => 'Baseline: Simplify Douglas-Peucker 150 m; Smooth 4 lần, offset 0,25; Offset lines +250 m (dương là lệch sang trái hướng vẽ). Transect: Points along geometry cách 50 m, lệch đầu 25 m; chiều dài 600 m; hướng đo trên cửa sổ ±100 m dọc baseline; <code>transect_code = \'T\'||lpad(to_string(to_int(("distance"-25)/50)+1),4,\'0\')</code>.',
                'method' => 'Biểu thức Geometry by expression dùng <code>azimuth</code> giữa hai điểm cách ±100 m trên baseline, rồi <code>make_line($geometry, project($geometry, 600, @az + pi()/2))</code>. Hướng ra sông xác định bằng quy tắc hình học (phía phải hướng vẽ baseline vì baseline offset sang trái) và kiểm tra bằng mắt trên ảnh, không dùng mặt nạ nước.',
                'qa' => 'Overlay kiểm tra không có cặp transect nào cắt nhau (kiểm lại bằng truy vấn không gian ngoài QGIS cũng ra 0). Baseline của QGIS khác bản tính trước (Smooth khác thuật toán làm trơn trước đó), nên khoảng cách baseline đến bờ khoảng 340–380 m ở đoạn giữa; chỉ so sánh được xu hướng theo thời gian, không so tuyệt đối. Ở đầu tuyến phía tây bắc (T0001–T0007) đường bờ gấp khúc song song transect nên số đo nhảy giữa các năm.',
            ],
            'figs' => [
                ['b1-baseline.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: baseline được dựng bằng cách dịch đường bờ đã làm trơn vào phía đất 250 mét.', 'Dựng baseline: làm trơn đường bờ rồi Offset lines +250 m vào phía đất.'],
                ['g2-baseline-pha-dat.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: bản đồ cho thấy baseline nằm phía đất liền, sông Hậu ở phía đông và bắc.', 'Kiểm tra bằng mắt: baseline nằm phía đất, sông Hậu ở phía đông và bắc.'],
                ['g2-transect-bieu-thuc.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: hộp thoại Geometry by expression với biểu thức dựng transect dài 600 mét vuông góc baseline.', 'Biểu thức Geometry by expression dựng transect 600 m vuông góc baseline.'],
            ],
        ],
        [
            'id' => 'buoc-7', 'title' => 'Khoảng cách và tốc độ biến động theo transect', 'tag' => 'Field Calculator · NSM · EPR · LRR · R²',
            'img' => 'b3-lrr.webp', 'w' => 1600, 'h' => 894,
            'alt' => 'Ảnh chụp màn hình QGIS: Field Calculator tính tốc độ biến động LRR bằng biểu thức hồi quy tuyến tính.',
            'cap' => 'Tính tốc độ biến động LRR bằng Field Calculator.',
            'body' => [
                'Với mỗi năm, Field Calculator tính cột <code>d&lt;năm&gt;</code> là khoảng cách từ gốc transect đến giao điểm gần nhất với đường bờ năm đó; transect không cắt đường bờ thì để trống. Quan sát lệch quá 50 m so với trung vị chuỗi của transect bị loại (22 quan sát), phần còn lại nằm ở các cột <code>c&lt;năm&gt;</code>.',
                'Từ chuỗi c, các cột NSM, EPR, LRR (hồi quy bình phương tối thiểu viết bằng hàm mảng), R², sai số chuẩn SE và hệ số Student t được tính. Trên 270 transect, LRR trung bình 0,389 m/năm, trung vị 0,269 m/năm; 61 transect có LRR khác 0 có ý nghĩa thống kê.',
            ],
            'more' => [
                'io' => 'Vào: <code>transects.gpkg</code> và chín <code>shoreline_&lt;năm&gt;.gpkg</code>. Ra: bảng thuộc tính transect có <code>d2018…d2026</code> (giá trị thô), <code>med_d</code>, <code>c2018…c2026</code> (đã khử đột biến), <code>n_obs</code>, <code>n_despiked</code>, <code>first_year</code>, <code>last_year</code>, <code>nsm</code>, <code>epr</code>, <code>lrr</code>, <code>r2</code>, <code>se_lrr</code>, <code>t_crit</code>. Tổng 2.426 số đo, sau khử đột biến còn 2.404.',
                'params' => 'Giao điểm gần gốc transect nhất: <code>distance(start_point($geometry), closest_point(@p, start_point($geometry)))</code> với <code>@p</code> là giao của transect với đường bờ. Ngưỡng khử đột biến 50 m so với trung vị chuỗi. Tối thiểu 3 điểm để tính. Hệ số t hai phía 95% với n − 2 bậc tự do, tra bảng nhúng trong biểu thức (12,706; 4,303; 3,182; 2,776; 2,571; 2,447; 2,365 cho n = 3…9). Z<sub>95</sub> = 1,959964 cho độ bất định EPR.',
                'method' => 'Mỗi cột là một lần Field Calculator. LRR là hệ số góc hồi quy bình phương tối thiểu của khoảng cách d theo năm t, R² = S<sub>xy</sub>²/(S<sub>xx</sub>·S<sub>yy</sub>), SE như công thức ở trên. Các đại lượng tính theo công thức ở trên; t là hệ số Student hai phía 95% với n − 2 bậc tự do.',
                'qa' => 'Trong 2.430 số đo (270 × 9) chỉ 4 ô trống; 259 transect có đủ 9 năm, ít nhất một transect còn 3 năm. Ba transect T0005, T0006 và T0194 bị khử từ 3 quan sát trở lên (4, 6 và 3). T0089: NSM −24,91 m, EPR −3,114 m/năm, LRR −2,827 m/năm, SE 0,518, t = 2,365, R² = 0,810. Có 160 trong 270 transect có R² dưới 0,3 (đều thuộc nhóm ổn định); cờ này chỉ để lưu ý.',
            ],
            'figs' => [
                ['g2-khoang-cach-d2018.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: Field Calculator tính khoảng cách từ gốc transect đến đường bờ năm 2018, cột d2018.', 'Field Calculator tính khoảng cách từ gốc transect đến đường bờ năm 2018 (cột d2018).'],
                ['b4-bang-thuoc-tinh.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: bảng thuộc tính transect với khoảng cách theo năm, NSM, EPR, LRR và R².', 'Bảng thuộc tính transect với khoảng cách theo năm và các thống kê biến động.'],
            ],
        ],
        [
            'id' => 'buoc-8', 'title' => 'Độ bất định, phân loại và hotspot', 'tag' => 'Mục 2.5 đề cương · Field Calculator',
            'img' => 'c2-ban-do-transect-lrr.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ xuất từ Print Layout của QGIS: các transect tô màu theo tốc độ biến động LRR, từ lùi bờ đến bồi.',
            'cap' => 'Bản đồ 270 transect tô màu theo LRR (5 lớp phân vị, bảng màu Spectral).',
            'body' => [
                'Một transect có biến động thực sự khi |LRR| lớn hơn t × SE(LRR) (t = 2,365 với 9 năm). Lùi mạnh cần thêm LRR ≤ −2 m/năm và khoảng cách giảm liên tục ít nhất 2 giai đoạn liền nhau (3 mốc năm). Tiêu chí chốt trước, không chỉnh theo kết quả.',
                'Kết quả: 209 ổn định, 52 bồi, 8 lùi nhẹ và 1 lùi mạnh (T0089). Đoạn lùi mạnh duy nhất tạo thành 1 hotspot dài khoảng 50 m, mức lùi 2,83 m/năm giai đoạn 2018–2026. Đa số đoạn bờ không có biến động đủ lớn so với độ bất định của ảnh 10 m.',
            ],
            'more' => [
                'io' => 'Vào: bảng thuộc tính transect ở bước 7. Ra: các cột <code>n_years</code>, <code>unc_lrr</code>, <code>is_significant</code>, <code>unc_epr</code>, <code>u_shoreline_avg</code>, <code>consecutive_erosion_periods</code>, <code>low_r2_caution</code>, <code>change_class</code>; lớp hotspot <code>hs_*.gpkg</code> với <code>severity</code>, <code>period_start_year</code>, <code>period_end_year</code>, <code>n_transect</code>, <code>notes</code>.',
                'params' => 'Ngưỡng lùi mạnh LRR ≤ −2,0 m/năm. Số mốc năm liên tiếp tối thiểu 3 (tức 2 giai đoạn liền nhau). Cờ R² dưới 0,3. Độ bất định đường bờ U<sub>shoreline</sub> = √(5² + 6² + U<sub>classify</sub>²) với U<sub>classify</sub> = 16,73 (2018), 17,82 (2022), 21,69 (2026), các năm khác 21,7. Hotspot: gom các transect lùi mạnh cách nhau không quá 75 m; mỗi nhóm một đoạn nối các giao điểm với đường bờ 2026 (nhóm một transect thì đoạn dài 50 m dọc bờ).',
                'method' => 'Bồi: có ý nghĩa và LRR &gt; 0. Ổn định: không có ý nghĩa. Có ý nghĩa và LRR &lt; 0: lùi mạnh nếu LRR ≤ −2 và có đủ chuỗi giảm liên tục, còn lại là lùi nhẹ. Công thức nhóm viết bằng một biểu thức <code>if</code> lồng trong Field Calculator. Hotspot dựng bằng Extract by expression, Field calculator của Processing và Geometry by expression (Line).',
                'qa' => 'Tổng 209 + 52 + 8 + 1 = 270. LRR trung bình theo nhóm (m/năm): ổn định 0,109, bồi 1,945, lùi nhẹ −2,010, lùi mạnh −2,827. T0089 (chainage 4.425 m): khoảng cách giảm từ 184,78 m (2018) xuống 159,87 m (2026); khoảng tin cậy LRR ±1,225 m/năm, độ bất định EPR 7,236 m/năm. Ngưỡng −2 m/năm và số mốc 3 là quy ước của đồ án; kết quả phân nhóm nhạy với hai quy ước đó.',
            ],
            'figs' => [
                ['g2-change-class.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: Field Calculator gán nhóm biến động change_class bằng biểu thức if lồng nhau.', 'Gán nhóm biến động change_class bằng Field Calculator.'],
                ['g3-unc-lrr.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: Field Calculator tính độ bất định LRR bằng tích của hệ số t và sai số chuẩn.', 'Tính độ bất định của LRR (t × SE) bằng Field Calculator.'],
                ['g3-thong-ke-theo-lop.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: kết quả Statistics by categories, LRR theo từng nhóm biến động.', 'Statistics by categories: LRR theo từng nhóm biến động.'],
                ['g3-ban-do-hotspot.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: đoạn hotspot lùi bờ mạnh tại transect T0089 tô màu trên ảnh nền.', 'Hotspot duy nhất, đoạn lùi mạnh tại transect T0089 (khoảng 50 m).'],
            ],
        ],
        [
            'id' => 'buoc-9', 'title' => 'Kiểm chứng, bản đồ và xuất cho web', 'tag' => 'Join attributes · Print Layout · Save Features As',
            'img' => 'c3-ban-do-so-sanh-lrr.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ xuất từ Print Layout của QGIS: transect tô màu theo chênh lệch LRR giữa kết quả dựng bằng giao diện QGIS và bản tính trước đó.',
            'cap' => 'Bản đồ chênh lệch LRR giữa bản dựng bằng giao diện QGIS và bản tính trước đó.',
            'body' => [
                'Kết quả được kiểm chứng ba cách: so với ground truth số hoá thủ công (5 ô mẫu, 3 năm 2018, 2022, 2026) để lấy U<sub>classify</sub>; so với bản tính trước bằng Join attributes by field value theo <code>transect_code</code>; và đối chiếu với vùng cảnh báo sạt lở 2019–2020.',
                'Bản đồ làm bằng Print Layout (xuất PNG 300 dpi và PDF). Dữ liệu xuất sang GeoJSON EPSG:4326 bằng Layer &gt; Save As: đường bờ, baseline, 270 transect và hotspot, rồi web nạp để hiển thị; web không tính lại chỉ số nào.',
            ],
            'more' => [
                'io' => 'Vào: bảng thuộc tính transect, <code>transects.json</code> của bản trước (đổi tên lớp <code>transects_cu</code>) và vùng cảnh báo sạt lở. Ra: <code>so_sanh_ban_cu.gpkg</code>, <code>thong_ke_chenh_lrr.gpkg</code>, <code>thong_ke_lrr_chung.gpkg</code>, ba bản đồ <code>ban_do_*.png/pdf</code> và thư mục <code>pipeline/data/qgis_gui/giai_doan2/export/</code>: <code>shorelines.geojson</code>, <code>baseline.geojson</code>, <code>transects.geojson</code>, <code>hotspots.geojson</code>.',
                'params' => 'GeoJSON: EPSG:4326, <code>COORDINATE_PRECISION=7</code>, bỏ tuỳ chọn ghi metadata. Đường bờ gộp bằng Merge vector layers, thêm cột <code>year</code> từ tên lớp và <code>water_index = \'mndwi\'</code>. 59 transect mỗi năm được so với đường bờ số hoá tay. Bán kính khớp sự kiện sạt lở 500 m; vùng đệm thống kê 100 m quanh vùng cảnh báo.',
                'method' => 'Sai số đường bờ RMSE = căn bậc hai của trung bình bình phương chênh lệch d(tự động) − d(tham chiếu) dọc transect. Chọn chỉ số nước: F1 trung bình cao nhất, nếu chênh không quá 0,005 thì chọn RMSE thấp nhất. Bản đồ dùng map theme và ba layout, chú giải lọc theo bản đồ, thước tỉ lệ và mũi tên Bắc.',
                'qa' => 'F1 trung bình: NDWI 0,9398; MNDWI 0,9456; AWEI 0,9457. RMSE đường bờ trung bình: NDWI 27,83 m; MNDWI 18,45 m; AWEI 60,01 m. Với đường bờ chính, RMSE 16,73 m (2018), 17,82 m (2022), 21,69 m (2026); các số này đo trên đường bờ do chuỗi QGIS chạy bằng script trước khi dựng lại bằng giao diện, chưa đo riêng cho bản dựng bằng giao diện. So với bản tính trước: chênh LRR có RMSE 1,389 m/năm, cùng nhóm biến động ở 164 trong 261 transect ghép được (62,8%). Vùng cảnh báo sạt lở dài 3.377 m: hotspot T0089 cách vùng này 5.183 m, nhiều hơn bán kính khớp 500 m nên 0/2 sự kiện khớp; 70 transect trong vùng đệm 100 m (49 ổn định, 21 bồi) có LRR trung bình +1,09 m/năm, không transect nào lùi có ý nghĩa thống kê. Khoảng cách và số transect trong vùng được đếm bằng truy vấn không gian ngoài QGIS; trong QGIS làm lại được bằng Select by Location.',
            ],
            'figs' => [
                ['b5-rmse.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: tính RMSE của chênh lệch LRR và EPR giữa bản dựng bằng giao diện và bản tính trước đó.', 'Tính RMSE của chênh lệch LRR và EPR so với bản tính trước đó.'],
                ['g3-thong-ke-lrr-chung.webp', 1600, 894, 'Ảnh chụp màn hình QGIS: nhật ký Basic statistics for fields trên LRR của 270 transect, trung bình 0,389 và trung vị 0,269.', 'Basic statistics for fields trên LRR của 270 transect: trung bình 0,389, trung vị 0,269 m/năm.'],
            ],
        ],
    ];

    $limits = [
        'Đối chiếu sạt lở không khớp (0/2 sự kiện theo tiêu chí khoảng cách): hotspot duy nhất (T0089) cách vùng cảnh báo 5.183 m, xa hơn ngưỡng 500 m. Trong 70 transect của vùng đệm 100 m (49 ổn định, 21 bồi) không transect nào lùi có ý nghĩa thống kê (LRR trung bình +1,09 m/năm). Hai sự kiện dùng chung một vùng nên không độc lập.',
        'Kết quả nhạy với cách giải đoán đường bờ. Cùng ảnh, bản tính trước đó cho 266 transect và 4 transect lùi mạnh, còn bản dựng bằng giao diện QGIS cho 270 transect và 1 transect lùi mạnh; chỉ 62,8% transect (164 trong 261 ghép được) cùng nhóm biến động, chênh LRR có RMSE 1,389 m/năm (lớn nhất 8,6 m/năm ở đầu tuyến T0001–T0007). Bản dựng bằng giao diện được chọn làm dữ liệu chính sau khi đã có bản trước; đây là quyết định của đồ án, không phải kết quả khách quan.',
        'Ngưỡng nước không do QGIS tính: QGIS 3.40 không có công cụ Otsu, nên giá trị được tính trên histogram của ảnh MNDWI mỗi năm bên ngoài giao diện rồi nhập vào Raster Calculator. Histogram trong QGIS chỉ để đối chiếu bằng mắt.',
        'Chọn chỉ số có yếu tố chủ quan: AWEI có F1 trung bình cao hơn MNDWI 0,0001 (0,9457 so với 0,9456). Hai chỉ số được coi là hoà nhờ ngưỡng 0,005 đặt sau khi đã thấy kết quả, rồi chọn MNDWI vì RMSE đường bờ thấp hơn (18,45 m so với 60,01 m). Phép so này làm trên bản tính trước đó, chưa làm lại bằng giao diện.',
        'Ground truth nhỏ: 5 ô mẫu và chỉ 3 năm (2018, 2022, 2026). Sáu năm còn lại dùng U<sub>classify</sub> = 21,7 m, là giá trị lớn nhất đã đo làm tròn lên chứ không phải số đo riêng. Các RMSE trên đo ở đường bờ chạy bằng script QGIS, chưa đo cho bản dựng bằng giao diện.',
        'Độ bất định lớn hơn tín hiệu ở nhiều nơi: U<sub>shoreline</sub> 18,5 đến 23,1 m, trong khi LRR của các transect nằm từ −7,36 đến +6,31 m/năm. Ở T0089, |EPR| = 3,11 m/năm nhỏ hơn độ bất định EPR 7,24 m/năm (95%), trong khi khoảng tin cậy của LRR (±1,23) chỉ tính từ độ lệch quanh đường hồi quy, không cộng sai số của từng đường bờ. Hai cách tính độ bất định cho kết luận khác nhau về cùng một transect, nên LRR nên đọc là xu hướng, không phải kết luận thống kê cho từng transect.',
        'Sai số định vị 12 m ở mức 95,5% được quy về 6 m ở 1σ, nửa pixel được coi là 1σ, và công thức gộp U<sub>shoreline</sub> là suy luận của đồ án, không gán cho nguồn nào.',
        'Mỗi năm chỉ một composite mùa khô, từ 4 đến 13 cảnh tuỳ năm, chưa hiệu chỉnh mực nước. Ngưỡng nước đổi theo năm từ 0,039 (2024) đến 0,178 (2018) nên một phần chênh lệch giữa các năm có thể do ngưỡng, không do bờ dịch chuyển.',
        'Các quy ước do đồ án đặt: khử đột biến 50 m (22 quan sát bị loại), lùi mạnh khi LRR ≤ −2 m/năm và giảm liên tục từ 3 mốc năm. 160 trong 270 transect có R² dưới 0,3 (đều thuộc nhóm ổn định). Kênh rộng dưới khoảng 100 m không được theo dõi do bước mở hình thái. Hướng ra sông của transect xác định bằng quy tắc hình học và kiểm tra bằng mắt, và đầu tuyến T0001–T0007 có đường bờ gấp khúc làm số đo kém ổn định.',
        'Hotspot ở bản dựng bằng giao diện là một đoạn đường dài 50 m (không phải vùng đệm 25 m quanh điểm như bản trước), vì một lớp chỉ nên có một kiểu hình học; chỉ có một hotspot nên chưa thử được trường hợp nhiều transect liền nhau.',
    ];
    /* Công thức: MathML gốc của trình duyệt (không cần JS hay thư viện). Mỗi mục = [tên, MathML, giải thích ký hiệu]. */
    $i = fn ($x) => "<mi>$x</mi>";
    $o = fn ($x) => "<mo>$x</mo>";
    $n = fn ($x) => "<mn>$x</mn>";
    $sb = fn ($a, $b) => "<msub><mi>$a</mi><mi mathvariant=\"normal\">$b</mi></msub>";
    $fr = fn ($a, $b) => "<mfrac><mrow>$a</mrow><mrow>$b</mrow></mfrac>";
    $rt = fn ($x) => "<msqrt><mrow>$x</mrow></msqrt>";
    $pa = fn ($x) => "<mo>(</mo>$x<mo>)</mo>";
    $sq = fn ($x) => "<msup><mrow>$x</mrow><mn>2</mn></msup>";
    $bar = fn ($x) => "<mover><mi>$x</mi><mo>¯</mo></mover>";
    $minus = '<mo>−</mo>';
    $maths = [
        2 => [
            ['Composite theo pixel', $sb('ρ', 'comp').$pa($i('x').$o(',').$i('y')).$o('=').'<munder><mi mathvariant="normal">median</mi><mi>t</mi></munder><mspace width="0.25em"></mspace>'.$sb('ρ', 't').$pa($i('x').$o(',').$i('y')),
                'ρ<sub>t</sub>: giá trị của cảnh thứ t tại pixel (x, y); chỉ lấy các quan sát không bị che (Cell Statistics, Median, bỏ NoData).'],
        ],
        3 => [
            ['MNDWI (Xu 2006)', $i('MNDWI').$o('=').$fr($i('B03').$minus.$i('B11'), $i('B03').$o('+').$i('B11')), 'B03: kênh xanh lục; B11: hồng ngoại sóng ngắn 1 (SWIR1). Là tỉ số nên không cần nhân hệ số 10.000.'],
            ['Ngưỡng Otsu', "<msup><mi>t</mi><mo>*</mo></msup>".$o('=').'<munder><mi mathvariant="normal">arg max</mi><mi>t</mi></munder>'.'<mo>[</mo>'.$sb('ω', '0').$pa($i('t')).'<mo>·</mo>'.$sb('ω', '1').$pa($i('t')).'<mo>·</mo>'.$sq($pa($sb('μ', '0').$pa($i('t')).$minus.$sb('μ', '1').$pa($i('t')))).'<mo>]</mo>',
                'ω<sub>0</sub>, ω<sub>1</sub>: tỉ lệ pixel ở hai lớp (đất, nước) khi cắt tại ngưỡng t; μ<sub>0</sub>, μ<sub>1</sub>: giá trị trung bình của hai lớp. Ngưỡng t* làm phương sai giữa hai lớp lớn nhất.'],
        ],
        6 => [
            ['Dịch chuyển ròng (NSM)', $i('NSM').$o('=').$sb('d', 'cuối').$minus.$sb('d', 'đầu'), 'd: khoảng cách từ gốc transect đến đường bờ (m).'],
            ['Tốc độ điểm cuối (EPR)', $i('EPR').$o('=').$fr($i('NSM'), '<mi mathvariant="normal">Δ</mi><mi>t</mi>'), 'Δt: số năm giữa mốc đầu và mốc cuối. Đơn vị m/năm.'],
            ['Tốc độ hồi quy tuyến tính (LRR)', $i('LRR').$o('=').$fr('<mo>∑</mo>'.$pa($sb('t', 'i').$minus.$bar('t')).$pa($sb('d', 'i').$minus.$bar('d')), '<mo>∑</mo>'.$sq($pa($sb('t', 'i').$minus.$bar('t')))),
                'LRR là hệ số góc OLS của d theo năm t, tính trên n mốc năm; t̄ và d̄ là giá trị trung bình.'],
            ['Sai số chuẩn của LRR', $i('SE').$o('=').$rt($fr('<mo>∑</mo>'.$sq($sb('e', 'i')), $pa($i('n').$minus.$n('2')).'<mo>∑</mo>'.$sq($pa($sb('t', 'i').$minus.$bar('t'))))),
                'e<sub>i</sub> = d<sub>i</sub> − (a + LRR·t<sub>i</sub>): phần dư của mốc thứ i.'],
            ['Hệ số xác định', $sq($i('R')).$o('=').$n('1').$minus.$fr('<mo>∑</mo>'.$sq($sb('e', 'i')), '<mo>∑</mo>'.$sq($pa($sb('d', 'i').$minus.$bar('d')))), 'Không dùng làm ngưỡng loại trừ.'],
        ],
        7 => [
            ['Nửa bề rộng khoảng tin cậy của LRR', $sb('CI', '95').$o('=').$i('t').'<mo>·</mo>'.$i('SE'), 't: hệ số Student hai phía 95% với n − 2 bậc tự do. Transect có ý nghĩa thống kê khi |LRR| &gt; CI<sub>95</sub>.'],
            ['Độ bất định của EPR', $sb('U', 'EPR').$o('=').$fr($sb('Z', '95').'<mo>·</mo>'.$rt($sq($sb('U', 'đầu')).$o('+').$sq($sb('U', 'cuối'))), '<mi mathvariant="normal">Δ</mi><mi>t</mi>'), 'Z<sub>95</sub> = 1,96; U<sub>đầu</sub>, U<sub>cuối</sub>: độ bất định đường bờ ở hai mốc.'],
            ['Độ bất định đường bờ', $sb('U', 'shoreline').$o('=').$rt($sq($sb('U', 'pixel')).$o('+').$sq($sb('U', 'georef')).$o('+').$sq($sb('U', 'classify'))),
                'U<sub>pixel</sub> = 5 m (nửa pixel 10 m); U<sub>georef</sub> = 6 m (12 m ở 95,5% quy về 1σ); U<sub>classify</sub> = RMSE so với đường bờ số hoá.'],
        ],
        8 => [
            ['Sai số đường bờ (RMSE)', $i('RMSE').$o('=').$rt($fr($n('1'), $i('n')).'<mo>∑</mo>'.$sq($pa($sb('d', 'tự động').$minus.$sb('d', 'tham chiếu')))), 'd: khoảng cách đo dọc transect; tham chiếu là đường bờ số hoá thủ công.'],
        ],
    ];
@endphp
<section class="steps" id="giai-doan-anh" aria-labelledby="steps-title">
    <div class="steps-inner">
        <header class="steps-head">
            <p class="eyebrow">Quy trình giải đoán ảnh bằng QGIS</p>
            <h2 id="steps-title">Từ ảnh vệ tinh đến đường bờ và tốc độ biến động</h2>
            <p class="lead">Chín bước dưới đây đều làm bằng giao diện QGIS 3.40 (menu, hộp thoại, Processing Toolbox, Field Calculator, Print Layout), từ nạp ảnh Sentinel-2 đến xuất GeoJSON cho web. Hình minh hoạ là ảnh chụp màn hình và bản đồ xuất từ chính dự án QGIS, không phải ảnh mô phỏng. Mỗi bước có phần chi tiết kỹ thuật bấm để mở; cuối quy trình là các hạn chế chưa giải quyết được.</p>
        </header>
        <ol class="step-list">
            @foreach ($steps as $i => $s)
            <li class="step" id="{{ $s['id'] }}" data-step="{{ $i + 1 }}">
                <div class="step-text">
                    <p class="step-num" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</p>
                    <p class="step-tag">{{ $s['tag'] }}</p>
                    <h3>{{ $s['title'] }}</h3>
                    @foreach ($s['body'] as $p)
                        <p>{!! $p !!}</p>
                    @endforeach
                    <details class="step-more">
                        <summary>Chi tiết kỹ thuật</summary>
                        <div class="step-more-body">
                            <dl class="spec">
                                <dt>Đầu vào và đầu ra</dt><dd>{!! $s['more']['io'] !!}</dd>
                                <dt>Thông số</dt><dd>{!! $s['more']['params'] !!}</dd>
                                @if (!empty($maths[$i]))
                                <dt>Công thức áp dụng</dt>
                                <dd>
                                    @foreach ($maths[$i] as $m)
                                    <div class="eq">
                                        <p class="eq-name">{!! $m[0] !!}</p>
                                        <math display="block" class="eq-math">{!! $m[1] !!}</math>
                                        @if ($m[2] !== '')<p class="eq-where">{!! $m[2] !!}</p>@endif
                                    </div>
                                    @endforeach
                                </dd>
                                @endif
                                <dt>Thuật toán</dt><dd>{!! $s['more']['method'] !!}</dd>
                                <dt>Kiểm tra chất lượng</dt><dd>{!! $s['more']['qa'] !!}</dd>
                            </dl>
                            @foreach ($s['figs'] as $f)
                            <figure class="more-fig">
                                <img src="{{ $img.$f[0] }}" width="{{ $f[1] }}" height="{{ $f[2] }}" alt="{{ $f[3] }}" loading="lazy" decoding="async">
                                <figcaption>{{ $f[4] }}</figcaption>
                            </figure>
                            @endforeach
                        </div>
                    </details>
                </div>
                <figure class="step-fig">
                    <img src="{{ $img.$s['img'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" alt="{{ $s['alt'] }}" @if ($i === 0) fetchpriority="high" @else fetchpriority="low" @endif decoding="async">
                    <figcaption>{{ $s['cap'] }}</figcaption>
                </figure>
            </li>
            @endforeach
        </ol>
        <aside class="step-limits" id="han-che-giai-doan" aria-labelledby="step-limits-title">
            <h3 id="step-limits-title">Hạn chế của quy trình</h3>
            <p>Những chỗ kết quả không khớp kỳ vọng hoặc phụ thuộc vào lựa chọn của đồ án. Không chỗ nào được chỉnh thông số để cho đẹp.</p>
            <ul>
                @foreach ($limits as $l)
                    <li>{!! $l !!}</li>
                @endforeach
            </ul>
        </aside>
    </div>
</section>
