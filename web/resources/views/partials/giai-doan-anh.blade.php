@php
    $img = '/img/phuong-phap/';
    /*
     * Mỗi bước: hình chính (cột phải, dính khi cuộn), 2 đoạn tóm tắt và phần "Chi tiết kỹ thuật" (details/summary, không cần JS).
     * Số liệu đọc từ pipeline/config.py và pipeline/output/*.json lúc viết; test AboutStepsTest đối chiếu lại từng số với file nguồn.
     * 'more' = [Đầu vào/đầu ra, Thông số, Công thức/thuật toán, Kiểm tra chất lượng]; 'figs' = hình phụ trong phần chi tiết.
     */
    $steps = [
        [
            'id' => 'buoc-1', 'title' => 'Thu thập và lọc cảnh', 'tag' => 'Sentinel-2 L2A · s1_composite.py',
            'img' => 'p1-loc-canh.webp', 'w' => 797, 'h' => 494,
            'alt' => 'Biểu đồ các cảnh Sentinel-2 từng năm 2018 đến 2026 theo ngày trong mùa khô: điểm tròn là cảnh được dùng, dấu X đỏ là cảnh bị loại vì mây che quá 30%.',
            'cap' => 'Mỗi điểm là một cảnh (ngày chụp trên trục ngang). Màu là tỉ lệ pixel bị che trong vùng tải; dấu X đỏ là cảnh bị loại. Tổng cộng 66 trên 126 cảnh được dùng.',
            'body' => [
                'Mỗi năm pipeline tìm các cảnh Sentinel-2 L2A chụp trong tháng 1–3 trên kho Microsoft Planetary Computer, trong khung bao quanh đoạn sông Hậu qua xã Bình Mỹ. Cảnh có mây metadata từ 80% trở lên bị bỏ ngay để bớt tải; qua bước lọc sơ này còn 126 cảnh cho cả chín năm, mỗi năm từ 11 đến 18 cảnh.',
                'Với các cảnh còn lại, pipeline đếm tỉ lệ pixel bị che theo lớp phân loại cảnh SCL; cảnh che hơn 30% bị loại. Còn lại 66 cảnh được dùng, từ 4 cảnh (2018) đến 13 cảnh (2020).',
            ],
            'more' => [
                'io' => 'Vào: truy vấn STAC <code>sentinel-2-l2a</code>, các kênh B03, B08, B11, B12 và SCL. Ra: <code>data/composite/scenes.json</code>, mỗi cảnh một dòng gồm ngày, <code>masked_fraction</code>, <code>used</code>, <code>processing_baseline</code>; mỗi năm thêm số cảnh dùng và <code>source_image_date</code> (ngày của cảnh dùng cuối cùng).',
                'params' => 'Khung tải (EPSG:4326): 105,17–105,36 kinh Đông, 10,45–10,575 vĩ Bắc. Mùa 01/01–31/03 mỗi năm, 2018–2026. Lọc sơ <code>eo:cloud_cover</code> &lt; 80%. Lớp SCL bị che: 0, 1, 3, 8, 9, 10, 11 (không dữ liệu, bão hoà, bóng mây, mây trung bình, mây cao, mây ti, tuyết). Ngưỡng loại cảnh: tỉ lệ pixel bị che &gt; 0,30 trong vùng tải. Gộp các cảnh cùng ngày mặt trời (<code>groupby solar_day</code>).',
                'method' => 'Tỉ lệ che của một cảnh = số pixel có SCL thuộc danh sách trên chia cho tổng pixel của vùng tải. Cảnh đạt ngưỡng đi tiếp sang bước tiền xử lý; cảnh bị loại vẫn được ghi trong <code>scenes.json</code> với <code>used = false</code> để truy vết.',
                'qa' => 'Trong các cảnh được dùng, tỉ lệ che cao nhất là 0,283 (2025), luôn dưới ngưỡng 0,30. Mỗi năm đều còn ít nhất 4 cảnh nên không năm nào bị bỏ trống. Số cảnh dùng chênh nhiều giữa các năm (4 đến 13), nghĩa là chất lượng composite không đồng đều.',
            ],
            'figs' => [],
        ],
        [
            'id' => 'buoc-2', 'title' => 'Tiền xử lý: cắt vùng, hiệu chỉnh, composite', 'tag' => 'Median composite · s1_composite.py',
            'img' => 'b1-composite.webp', 'w' => 778, 'h' => 521,
            'alt' => 'Ảnh composite giả màu năm 2026 quanh xã Bình Mỹ, sông Hậu màu xanh thẫm, ranh giới xã nét đứt vàng.',
            'cap' => 'Composite mùa khô 2026, giả màu SWIR1 · NIR · Xanh lục (nước gần đen, thực vật xanh lục sáng). Nét đứt vàng là ranh giới xã.',
            'body' => [
                'Các cảnh đạt yêu cầu được đưa về cùng một lưới 10 m trong hệ UTM 48N rồi lấy trung vị, pixel mây và bóng mây không tham gia. Kết quả là một ảnh bốn kênh phản xạ cho mỗi năm.',
                'Từ processing baseline 04.00 (25/01/2022) ESA cộng thêm 1000 vào giá trị số của ảnh L2A, nên pipeline trừ lại để các năm so sánh được. Sau đó mới tính chỉ số nước và chỉ phân tích trong ranh giới xã cộng đệm 1 km.',
            ],
            'more' => [
                'io' => 'Vào: các cảnh đạt ở bước 1. Ra: <code>data/composite/&lt;năm&gt;.tif</code>, GeoTIFF nén deflate, 4 kênh (B03, B08, B11, B12) kiểu float32 trong khoảng 0–1, EPSG:32648, pixel 10 m. Ảnh 2026 rộng 2.081 × 1.385 pixel (khoảng 20,8 × 13,9 km).',
                'params' => 'CRS: EPSG:32648. Độ phân giải: 10 m. SWIR (B11, B12) gốc 20 m được nội suy song tuyến lên 10 m; SCL dùng láng giềng gần nhất. Offset hiệu chỉnh: 1000 cho processing baseline từ 4,0 trở lên, 0 cho bản cũ. Trong danh sách cảnh, hai năm có cả hai nhóm baseline là 2019 (02.12 và 05.00) và 2022 (03.00, 04.00, 05.10); từ 2023 mọi cảnh đều từ 04.00 trở lên. Ranh giới xã lấy từ OSM relation 13646056; vùng phân tích là ranh giới này đệm 1.000 m.',
                'method' => 'Phản xạ = (DN − offset) / 10000, pixel DN = 0 là không dữ liệu. Với mỗi pixel, composite = trung vị theo thời gian của các quan sát không bị che (<code>skipna</code>). Cắt vùng: ảnh giữ nguyên khung tải, còn các bước sau chỉ tính trong vùng phân tích.',
                'qa' => 'Pixel mây còn sót vài điểm được xử lý ở bước làm sạch mặt nạ. Pipeline không có bước kiểm độc lập riêng cho việc trừ offset; chất lượng chung của composite chỉ được đo gián tiếp qua ground truth ở bước 9.',
            ],
            'figs' => [
                ['p3-cat-vung.webp', 756, 536, 'Ảnh composite 2026 với ranh giới xã nét đứt vàng và vùng phân tích đệm 1 km viền xanh lơ.', 'Cắt vùng: ranh giới xã (vàng) và vùng phân tích đệm 1.000 m (xanh lơ) dùng để tính ngưỡng Otsu.'],
                ['p2-bang-pho.webp', 1188, 229, 'Bốn ảnh xám của các kênh B03, B08, B11 và B12 quanh đoạn sông Hậu; nước tối ở kênh hồng ngoại.', 'Bốn kênh phổ dùng, cắt quanh đoạn sông có điểm nóng, kéo giãn 2–98% từng kênh. Nước tối dần ở NIR và SWIR, là cơ sở của chỉ số nước.'],
            ],
        ],
        [
            'id' => 'buoc-3', 'title' => 'Làm nổi mặt nước bằng chỉ số', 'tag' => 'MNDWI · Otsu · s2_water.py',
            'img' => 'b2-mndwi.webp', 'w' => 751, 'h' => 419,
            'alt' => 'Bản đồ chỉ số MNDWI năm 2026: sông Hậu và ao hồ màu xanh dương đậm, đất liền màu đỏ cam.',
            'cap' => 'MNDWI năm 2026. Vùng xanh dương là nước, đỏ cam là đất; ngưỡng Otsu năm này là 0,113.',
            'body' => [
                'Pipeline tính ba chỉ số nước là NDWI, MNDWI và AWEI. Mỗi chỉ số được tách nước/đất bằng ngưỡng Otsu trong ranh giới xã cộng đệm 1 km.',
                'Chọn chỉ số dựa trên ground truth số hoá thủ công (5 ô mẫu × 3 năm). F1 trung bình của MNDWI (0,9456) và AWEI (0,9457) gần như hoà, nên xét tiếp sai số đường bờ: MNDWI 18,45 m, NDWI 27,83 m, AWEI 60,01 m. MNDWI được chọn. Ngưỡng hoà 0,005 đặt sau khi đã thấy kết quả; đây là một lựa chọn của đồ án, không phải kết quả khách quan.',
            ],
            'more' => [
                'io' => 'Vào: <code>composite/&lt;năm&gt;.tif</code>. Ra: <code>data/water/&lt;năm&gt;_&lt;chỉ số&gt;.tif</code> kiểu uint8 (1 nước, 0 đất, 255 không dữ liệu), cùng lưới 10 m, và <code>data/water/thresholds.json</code> (ngưỡng từng năm, từng chỉ số, làm tròn 4 chữ số).',
                'params' => 'Ngưỡng Otsu MNDWI từng năm: 2018: 0,1633; 2019: 0,0673; 2020: 0,1349; 2021: 0,1211; 2022: 0,1207; 2023: 0,1289; 2024: 0,0404; 2025: 0,081; 2026: 0,1129. Vùng tính ngưỡng: ranh giới xã đệm 1.000 m; năm 2026 có 1.347.886 pixel hợp lệ, trong đó 9,82% là nước.',
                'method' => 'NDWI = (B03 − B08) / (B03 + B08) (McFeeters 1996). MNDWI = (B03 − B11) / (B03 + B11) (Xu 2006). AWEI<sub>nsh</sub> = 4(B03 − B11) − (0,25·B08 + 2,75·B12) (Feyisa et al. 2014). Otsu chọn ngưỡng làm cực đại phương sai giữa hai lớp của histogram; histogram MNDWI 2026 có hai đỉnh rõ (đất quanh −0,4, nước quanh 0,7) nên ngưỡng nằm ở thung lũng giữa hai đỉnh.',
                'qa' => 'Ngưỡng 2026 trong <code>thresholds.json</code> được tính lại từ composite bằng đúng hàm của pipeline khi vẽ hình histogram, hai giá trị khớp nhau. Độ chính xác của từng chỉ số đo trên ground truth ở bước 9.',
            ],
            'figs' => [
                ['p4-otsu.webp', 750, 397, 'Histogram MNDWI năm 2026 có hai đỉnh, đất quanh âm 0,4 và nước quanh 0,7, đường đỏ đứng là ngưỡng Otsu 0,113.', 'Histogram MNDWI 2026 trong vùng phân tích và ngưỡng Otsu 0,113 (đường đỏ). Đất chiếm đỉnh lớn bên trái, nước là đỉnh nhỏ bên phải.'],
            ],
        ],
        [
            'id' => 'buoc-4', 'title' => 'Mặt nạ nước và làm sạch', 'tag' => 'Hình thái học · s3_shoreline.py',
            'img' => 'b3-mat-na-nuoc.webp', 'w' => 778, 'h' => 521,
            'alt' => 'Mặt nạ nước nhị phân năm 2026: sông Hậu màu xanh, đất màu nâu nhạt.',
            'cap' => 'Mặt nạ nước 2026 sau ngưỡng MNDWI (xanh: nước, nâu: đất).',
            'body' => [
                'Mặt nạ được làm sạch trước khi trích đường bờ: mở hình thái để bỏ kênh rạch hẹp dưới khoảng 100 m, giữ vùng nước liên thông lớn nhất (sông Hậu) và lấp các lỗ nhỏ hơn 5 ha.',
                'Việc này nhằm chỉ theo dõi bờ sông chính, không để ao nuôi, kênh nội đồng hay nhiễu nhỏ trở thành đường bờ. Năm 2026, bước này bỏ 51.918 pixel nước (khoảng 519 ha) và không thêm pixel nào.',
            ],
            'more' => [
                'io' => 'Vào: <code>water/&lt;năm&gt;_mndwi.tif</code>. Ra: mặt nạ nước đã làm sạch (trong bộ nhớ, dùng trực tiếp cho bước trích đường bờ).',
                'params' => 'Mở hình thái với phần tử đĩa bán kính 5 pixel (50 m). Giữ thành phần liên thông lớn nhất. Lấp lỗ nhỏ hơn 500 pixel (5 ha). Cụm không dữ liệu nhỏ hơn 25 pixel (0,25 ha) được điền bằng giá trị pixel hợp lệ gần nhất; cụm từ 25 pixel trở lên coi là vùng thiếu dữ liệu thật và đoạn bờ đi qua đó bị cắt.',
                'method' => 'Mở hình thái (binary opening) = co rồi giãn, bỏ cấu trúc hẹp hơn phần tử cấu trúc. Gắn nhãn thành phần liên thông (<code>ndimage.label</code>) và chọn thành phần có diện tích lớn nhất làm sông Hậu. Lỗ trong lòng sông (ghe tàu, bèo) được lấp nếu nhỏ hơn ngưỡng.',
                'qa' => 'Năm 2026: trước làm sạch 206.932 pixel nước, sau làm sạch 155.014 pixel, bỏ 51.918 pixel (519,2 ha), thêm 0 pixel. Hình bên dưới là một cửa sổ 2,4 × 1,6 km, nơi kênh nội đồng và ao (đỏ) bị bỏ còn sông chính (xanh) được giữ. Hệ quả cần biết: kênh rộng dưới khoảng 100 m không được theo dõi.',
            ],
            'figs' => [
                ['p5-truoc-sau.webp', 1188, 419, 'Hai mặt nạ nước cạnh nhau: bên trái trước làm sạch gồm sông và nhiều kênh ao nhỏ, bên phải sau làm sạch các kênh ao bị tô đỏ còn sông giữ màu xanh.', 'Trước và sau làm sạch mặt nạ nước 2026 (cửa sổ 2,4 × 1,6 km). Đỏ là pixel nước bị bỏ, xanh là nước được giữ.'],
            ],
        ],
        [
            'id' => 'buoc-5', 'title' => 'Trích đường bờ từng năm', 'tag' => 'Contour dưới pixel · s3_shoreline.py',
            'img' => 'b4-duong-bo.webp', 'w' => 688, 'h' => 485,
            'alt' => 'Chín đường bờ từ 2018 đến 2026 vẽ chồng lên ảnh composite, mỗi năm một màu.',
            'cap' => 'Chín đường bờ 2018–2026 chồng trên composite 2026, phóng to quanh đoạn có điểm nóng lùi bờ.',
            'body' => [
                'Đường bờ là đường đồng mức 0 của trường chỉ số MNDWI trừ ngưỡng Otsu, trong vùng nước đã làm sạch. Cách này cho vị trí dưới pixel thay vì nhảy từng nửa pixel như đường đồng mức 0,5 trên mặt nạ nhị phân. Kết quả được cắt theo ranh giới xã và đơn giản hoá 5 m: chín đường, mỗi năm một đường, 2018–2026.',
                'Độ bất định của từng đường bờ gộp từ nửa pixel (5 m), định vị ESA (6 m ở mức 1σ) và sai số phân loại đo trên ground truth (16,7 đến 21,7 m). Gộp lại, U<sub>shoreline</sub> nằm trong khoảng 18,5 đến 23,1 m. Từ bản demo lần 5, đường bờ chính do chuỗi QGIS giải đoán theo các bước của <code>s3_shoreline.py</code>; bản Python được giữ làm đối chứng.',
            ],
            'more' => [
                'io' => 'Vào: mặt nạ đã làm sạch, composite và ngưỡng Otsu của năm. Ra: <code>data/shoreline/&lt;năm&gt;.json</code>, GeoJSON một MultiLineString, EPSG:32648, kèm thuộc tính năm và chỉ số nước. Mỗi năm là một đoạn liên tục dài 14,85 đến 15,37 km (2018: 15,37 km; 2026: 14,92 km).',
                'params' => 'Đơn giản hoá: 5 m (nửa pixel). Cắt theo ranh giới xã nới 100 m để không mất đoạn bờ sát ranh. Bỏ mảnh ngắn hơn 200 m. Cụm không dữ liệu lớn bị cắt kèm đệm 2 pixel (20 m). Hệ số eps = 0,001 dùng ở chỗ hình thái học đã sửa mặt nạ.',
                'method' => 'Trường có dấu f = MNDWI − ngưỡng, giữ nguyên giá trị liên tục ở nơi mặt nạ sạch khớp ngưỡng, còn chỗ mặt nạ bị sửa thì rơi về ±eps. Đường bờ = contour f = 0 (<code>skimage.measure.find_contours</code>, nội suy tuyến tính dưới pixel) rồi nối mảnh, lọc mảnh ngắn, đơn giản hoá. Nếu thiếu composite hoặc ngưỡng, pipeline lùi về contour mức 0,5 trên mặt nạ nhị phân (độ phân giải nửa pixel).',
                'qa' => 'Sai số đường bờ so với đường số hoá tay đo ở bước 9 (RMSE MNDWI 15,9 m năm 2018, 18,15 m năm 2022, 21,30 m năm 2026). Cả chín năm đều ra đúng một đường liên tục trong ranh giới xã, không bị đứt đoạn.',
            ],
            'figs' => [],
        ],
        [
            'id' => 'buoc-6', 'title' => 'Baseline và transect', 'tag' => '266 transect · s4_transects.py',
            'img' => 'b5-transect.webp', 'w' => 688, 'h' => 485,
            'alt' => 'Các transect vuông góc với baseline đỏ nét đứt, tô màu theo mức biến động: nhiều đoạn xám, vài đoạn xanh dương và cam, một đoạn đỏ.',
            'cap' => 'Baseline (đỏ nét đứt) và transect cách 50 m, tô theo mức biến động: xanh dương bồi, xám ổn định, cam lùi nhẹ, đỏ lùi mạnh.',
            'body' => [
                'Baseline là đường bờ năm đầu làm trơn, dịch 250 m vào phía đất. Từ đó sinh transect vuông góc mỗi 50 m, dài 600 m ra phía sông; transect cắt nhau ở chỗ bờ cong gắt bị bỏ. Kết quả có 266 transect.',
                'Tại mỗi transect, pipeline đo khoảng cách từ baseline tới giao điểm đường bờ của từng năm. Năm nào không có số đo hợp lệ thì để trống, không nội suy.',
            ],
            'more' => [
                'io' => 'Vào: chín đường bờ và mặt nạ nước năm đầu (để biết phía đất). Ra: <code>data/baseline.json</code> (1 LineString, dài 13,76 km) và <code>data/transects.json</code>: mỗi transect có mã (T0001…), <code>chainage_m</code> và <code>distances</code> = {năm: mét hoặc null}.',
                'params' => 'Khoảng cách transect 50 m, chiều dài 600 m, baseline cách đường bờ 250 m về phía đất. Làm trơn baseline: đơn giản hoá 150 m rồi Chaikin 4 vòng. Hướng transect lấy trên cửa sổ ±100 m. Sinh 275 vị trí (lý trình 25 đến 13.725 m), bỏ 9 transect cắt nhau, còn 266.',
                'method' => 'Phương pháp DSAS: transect vuông góc baseline, khoảng cách = từ gốc transect đến giao điểm gần nhất với đường bờ (tuỳ chọn closest intersection). Phía sông chọn bằng cách thử hai hướng pháp tuyến, hướng nào có nhiều điểm rơi vào nước hơn. Transect cắt nhiều transect khác nhất bị bỏ lần lượt cho đến khi hết giao cắt.',
                'qa' => 'Cả 266 transect đều có đủ 9 năm lúc đo (2.394 quan sát), không nội suy chỗ trống. Hình bên dưới phóng to đoạn 1.000 × 600 m quanh điểm nóng: pixel 10 m hiện rõ, 17 transect cắt các đường bờ, mỗi chấm màu là giao điểm của một năm.',
            ],
            'figs' => [
                ['p6-phong-to-transect.webp', 778, 471, 'Cận cảnh một đoạn bờ 1.000 m: các transect vuông góc cắt chín đường bờ nhiều màu, mỗi giao điểm là một chấm, pixel 10 m nhìn thấy rõ trên ảnh nền.', 'Phóng to một đoạn bờ (1.000 × 600 m, ảnh nền giữ nguyên pixel 10 m). Mỗi chấm là giao điểm transect với đường bờ một năm; màu transect theo nhóm biến động.'],
            ],
        ],
        [
            'id' => 'buoc-7', 'title' => 'Tốc độ biến động theo transect', 'tag' => 'NSM · EPR · LRR · R² · s5_stats.py',
            'img' => 'b6-chuoi-nam.webp', 'w' => 670, 'h' => 397,
            'alt' => 'Biểu đồ khoảng cách từ baseline đến đường bờ giảm dần từ 189 m năm 2018 xuống 168 m năm 2026, kèm đường hồi quy.',
            'cap' => 'Chuỗi vị trí đường bờ tại transect T0094 và đường hồi quy tuyến tính: LRR = −2,67 m/năm, R² = 0,80.',
            'body' => [
                'Với chuỗi khoảng cách theo năm, pipeline tính NSM (dịch chuyển giữa đầu và cuối), EPR (NSM chia số năm), LRR (hệ số góc hồi quy tuyến tính), sai số chuẩn SE và R². Quan sát lệch quá 50 m so với trung vị chuỗi được loại trước (9 quan sát trên toàn bộ dữ liệu).',
                'Ở T0094, khoảng cách giảm từ 189,43 m (2018) xuống 168,20 m (2026), tức bờ lùi 21,23 m. LRR là −2,67 m/năm với khoảng tin cậy 95% là ±1,21 m, không chứa 0.',
            ],
            'more' => [
                'io' => 'Vào: <code>data/transects.json</code> và chín đường bờ. Ra: <code>output/transects.json</code> (EPSG:4326, mỗi transect có <code>distances</code> đã khử đột biến và khối <code>stats</code>), <code>output/summary.json</code>. Sau khử đột biến còn 2.385 trên 2.394 quan sát.',
                'params' => 'Độ tin cậy 95%. Ngưỡng khử đột biến 50 m so với trung vị chuỗi. Tối thiểu 3 điểm để tính. Hệ số t hai phía với bậc tự do n − 2: n = 9 thì t = 2,365; transect thiếu năm dùng t tương ứng với n của nó. Z<sub>95</sub> = 1,959964 cho độ bất định EPR.',
                'method' => 'NSM = d(năm cuối) − d(năm đầu). EPR = NSM / Δt. LRR = hệ số góc OLS của d theo năm; SE = √(Σ phần dư² / (n − 2) / Σ(t − t̄)²); R² = 1 − Σ phần dư² / Σ(d − d̄)². Nửa bề rộng khoảng tin cậy của LRR = t × SE. Độ bất định EPR = Z<sub>95</sub> × √(U<sub>đầu</sub>² + U<sub>cuối</sub>²) / Δt.',
                'qa' => 'T0094: NSM −21,23 m, EPR −2,654 m/năm, LRR −2,674 m/năm, SE 0,5108, t = 2,365, R² = 0,797, 5 giai đoạn giảm liên tục, không điểm nào bị khử. Trên toàn bộ 266 transect, 85 có LRR khác 0 có ý nghĩa thống kê; 140 transect có R² dưới 0,3 và bị gắn cờ cảnh báo (cờ chỉ để lưu ý, không đổi nhóm).',
            ],
            'figs' => [],
        ],
        [
            'id' => 'buoc-8', 'title' => 'Phân loại mức biến động', 'tag' => 'Mục 2.5 đề cương · s5_stats.py',
            'img' => 'phan_loai.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ phân loại mức biến động bờ sông Hậu tại Bình Mỹ theo bốn mức: bồi, ổn định, lùi nhẹ, lùi mạnh.',
            'cap' => 'Bản đồ phân loại 266 transect: 71 bồi, 181 ổn định, 10 lùi nhẹ, 4 lùi mạnh.',
            'body' => [
                'Một transect có biến động thực sự khi |LRR| lớn hơn t × SE(LRR) (t = 2,365 với 9 năm, tin cậy 95%). Lùi mạnh cần thêm LRR ≤ −2 m/năm và khoảng cách giảm liên tục ở ít nhất 3 mốc năm liền nhau. Tiêu chí được chốt trước khi chạy lại, không chỉnh theo kết quả.',
                'Kết quả: 71 transect bồi, 181 ổn định, 10 lùi nhẹ và 4 lùi mạnh (T0020, T0094, T0200, T0201), tạo thành 3 điểm nóng dài khoảng 50, 50 và 100 m. Đa số đoạn bờ không có biến động đủ lớn so với độ bất định của ảnh 10 m.',
            ],
            'more' => [
                'io' => 'Vào: khối <code>stats</code> của bước 7. Ra: <code>change_class</code> và <code>low_r2_caution</code> trong từng transect; <code>output/hotspots.json</code> (3 điểm nóng) và <code>output/summary.json</code> (số transect mỗi nhóm).',
                'params' => 'Ngưỡng lùi mạnh LRR ≤ −2,0 m/năm. Số mốc năm liên tiếp tối thiểu 3 (tức 2 giai đoạn liền nhau). Cờ R² dưới 0,3. Điểm nóng: gom các transect lùi mạnh liền kề cách nhau không quá 1,5 lần khoảng cách transect.',
                'method' => 'Bồi: có ý nghĩa và LRR &gt; 0. Ổn định: không có ý nghĩa. Lùi: có ý nghĩa và LRR &lt; 0; trong đó lùi mạnh nếu LRR ≤ −2 và có đủ chuỗi giảm liên tục, còn lại là lùi nhẹ. Trung bình và độ lệch chuẩn của LRR các transect lùi có ý nghĩa (−1,91 và 0,74 m/năm) chỉ để mô tả, không dùng làm ngưỡng.',
                'qa' => 'Tổng 71 + 181 + 10 + 4 = 266. Ngưỡng −2 m/năm và số mốc 3 là quy ước của đồ án, tham chiếu khoảng phân lớp trong tài liệu nhưng không có nguồn nào dùng đúng ngưỡng này; kết quả phân nhóm nhạy với hai quy ước đó. Ba điểm nóng có mức lùi trung bình 2,68 (T0020), 2,67 (T0094) và 3,09 m/năm (T0200–T0201) giai đoạn 2018–2026.',
            ],
            'figs' => [],
        ],
        [
            'id' => 'buoc-9', 'title' => 'Độ chính xác và độ bất định', 'tag' => 'Ground truth · s7_evaluate.py',
            'img' => 'p7-do-chinh-xac.webp', 'w' => 968, 'h' => 394,
            'alt' => 'Hai biểu đồ cột so sánh ba chỉ số nước NDWI, MNDWI, AWEI: F1 trung bình gần bằng nhau, còn sai số đường bờ của MNDWI thấp nhất, 18,45 m.',
            'cap' => 'F1 trung bình và RMSE đường bờ của ba chỉ số trên ground truth. MNDWI (xanh) được chọn vì RMSE thấp nhất khi F1 hoà với AWEI.',
            'body' => [
                'Ground truth là mặt nước số hoá thủ công trong 5 ô mẫu ở 3 năm (2018, 2022, 2026). Pipeline so mặt nạ nước từng chỉ số với ground truth theo pixel (precision, recall, F1, IoU) và so đường bờ tự động với đường bờ số hoá tay dọc các transect.',
                'Với MNDWI, RMSE đường bờ là 15,9 m (2018), 18,15 m (2022), 21,30 m (2026). U<sub>classify</sub> lấy RMSE trên đường bờ chính sau khử đột biến (16,73; 17,82; 21,69 m), rồi gộp với nửa pixel và sai số định vị thành U<sub>shoreline</sub> từng năm (18,5 đến 23,1 m).',
            ],
            'more' => [
                'io' => 'Vào: <code>data/ground_truth/ground_truth.gpkg</code> (lớp <code>windows</code> và <code>water_&lt;năm&gt;</code>, trường <code>loai</code> = song | khac) và các mặt nạ nước ba chỉ số. Ra: <code>output/evaluation.json</code>. Đối chiếu chéo: <code>output/crosscheck.json</code>.',
                'params' => 'Ngưỡng hoà F1 0,005. 5 ô mẫu (W1–W5), mỗi năm 18.000 pixel. Số transect so được: 59 mỗi năm (2018, 2022, 2026). U<sub>pixel</sub> = 5 m (nửa pixel), U<sub>georef</sub> = 12 m / 2 = 6 m (ESA, 95,5% quy về 1σ), U<sub>classify</sub> = RMSE sau khử đột biến nêu trên; năm không có ground truth lấy 21,7 m, giá trị lớn nhất đã đo làm tròn lên.',
                'method' => 'Precision = TP/(TP + FP), Recall = TP/(TP + FN), F1 = 2PR/(P + R), IoU = TP/(TP + FP + FN). RMSE = √(trung bình (d<sub>tự động</sub> − d<sub>tham chiếu</sub>)²). Chọn chỉ số: F1 trung bình cao nhất, nếu chênh không quá 0,005 thì chọn RMSE thấp nhất. Độ bất định đường bờ: U<sub>shoreline</sub> = √(U<sub>pixel</sub>² + U<sub>georef</sub>² + U<sub>classify</sub>²), mọi thành phần cùng chuẩn 1σ.',
                'qa' => 'F1 trung bình: NDWI 0,9398; MNDWI 0,9456; AWEI 0,9457. RMSE đường bờ trung bình: NDWI 27,83 m; MNDWI 18,45 m; AWEI 60,01 m. U<sub>shoreline</sub>: 18,46 m (2018), 19,46 m (2022), 23,05 m (2026), 23,06 m ở các năm còn lại. Đối chiếu chéo với ESA WorldCover (2020–2021) và Impact Observatory (2018–2023): F1 trung bình MNDWI 0,8765, thấp hơn ground truth; theo ghi chú của pipeline, hai sản phẩm này là ảnh cả năm nên chỉ là kiểm tra thô, không dùng để chỉnh U.',
            ],
            'figs' => [
                ['p8-bat-dinh.webp', 745, 375, 'Biểu đồ cột độ bất định đường bờ từng năm từ 18,5 đến 23,1 mét, kèm các thành phần định vị 6 mét, nửa pixel 5 mét và sai số phân loại.', 'Độ bất định đường bờ từng năm (1σ): cột là U gộp, chấm đỏ là U phân loại, hai đường ngang là U định vị 6 m và nửa pixel 5 m.'],
            ],
        ],
        [
            'id' => 'buoc-10', 'title' => 'Đối chiếu và giới hạn', 'tag' => 'Kiểm chứng · s9_validate.py',
            'img' => 'doi_chieu_2021.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ đối chiếu vùng cảnh báo sạt lở 2019–2020 với kết quả phân loại của pipeline.',
            'cap' => 'Vùng cảnh báo sạt lở 2019–2020 dọc QL91 đặt cạnh kết quả phân loại.',
            'body' => [
                'Kết quả được đối chiếu với hai sự kiện sạt lở đã công bố (01/08/2019 và 27/05/2020). Nguồn chỉ nêu lý trình Km nên vùng cảnh báo dài khoảng 3,4 km và hai sự kiện dùng chung một hình học, không độc lập.',
                'Pipeline khớp theo tiêu chí khoảng cách nhưng khớp yếu: điểm nóng gần nhất cách vùng cảnh báo 355 m (ngưỡng khớp 500 m), nên 2/2 sự kiện khớp. Trong vùng đệm 100 m có 70 transect (38 bồi, 32 ổn định), không transect nào lùi có ý nghĩa thống kê, nên khớp là do điểm nóng nằm gần chứ không phải vùng cảnh báo lùi. Bản Python trước đó không khớp (5.234 m, 0/2). Ngưỡng 500 m không được chỉnh; thứ thay đổi giữa hai bản là cách giải đoán đường bờ.',
            ],
            'more' => [
                'io' => 'Vào: <code>output/reference.json</code> (vùng cảnh báo, EPSG:4326), <code>output/hotspots.json</code>, <code>output/transects.json</code>. Ra: <code>output/validation.json</code>: mỗi sự kiện một dòng gồm khoảng cách tới điểm nóng gần nhất, số transect trong vùng, phân bố nhóm, LRR trung bình.',
                'params' => 'Bán kính khớp 500 m. Vùng đệm thống kê 100 m quanh vùng cảnh báo. Mọi phép đo trong EPSG:32648. Độ dài vùng cảnh báo 3.377 m.',
                'method' => 'Hai phép đo: (1) khoảng cách từ vùng cảnh báo đến điểm nóng gần nhất, khớp khi ≤ 500 m; (2) thống kê các transect cách vùng ≤ 100 m, không phụ thuộc việc có điểm nóng hay không, để biết ảnh có thấy xu hướng lùi bờ trong vùng cảnh báo dù chưa vượt ngưỡng lùi mạnh.',
                'qa' => 'Trong 70 transect của vùng: LRR trung bình +1,15 m/năm (bồi), chỉ 2,9% (2/70) có LRR âm, 0% lùi có ý nghĩa. Đây là dữ liệu kiểm chứng, không dùng để huấn luyện hay chỉnh ngưỡng; vùng cảnh báo là đoạn dài chứ không phải điểm sạt nên không đánh giá được độ chính xác vị trí.',
            ],
            'figs' => [],
        ],
        [
            'id' => 'buoc-11', 'title' => 'Xuất ra GeoServer và web', 'tag' => 'webgis:import · provision.sh',
            'img' => 'p9-xuat-geoserver.webp', 'w' => 863, 'h' => 315,
            'alt' => 'Sơ đồ năm khối nối tiếp: thư mục output của pipeline, lệnh nạp webgis:import, PostGIS, GeoServer phục vụ WMS và WFS, rồi web Leaflet.',
            'cap' => 'Luồng dữ liệu từ pipeline đến trình duyệt: GeoJSON, UPSERT vào PostGIS, GeoServer phát WMS/WFS, web Leaflet đọc lại.',
            'body' => [
                'Pipeline ghi bốn lớp kết quả (baseline, đường bờ, transect, điểm nóng) cùng lớp sạt lở tham chiếu ra GeoJSON EPSG:4326. Lệnh nạp đưa chúng vào PostGIS bằng UPSERT trong một giao dịch, nên chạy lại không làm mất mã định danh đang được tham chiếu.',
                'GeoServer đọc thẳng các bảng PostGIS và phát thành lớp WMS và WFS với kiểu SLD riêng cho từng lớp. Web hiển thị lớp WMS, đọc số liệu qua API và cho tải xuống; web không tính lại chỉ số nào.',
            ],
            'more' => [
                'io' => 'Vào: <code>pipeline/output/*.json</code> (baseline, shorelines, transects, hotspots, reference). Ra: các bảng <code>shorelines</code>, <code>transects</code>, <code>erosion_hotspots</code>, <code>reference_landslide_events</code> trong PostGIS và các lớp tương ứng trên GeoServer.',
                'params' => 'Lệnh: <code>php artisan webgis:import ../pipeline/output</code> (thêm <code>--dry-run</code> để chỉ kiểm tra, không ghi). GeoServer: <code>web/geoserver/provision.sh songhau &lt;csdl&gt;</code> tạo workspace <code>songhau</code>, kho PostGIS, bốn kiểu SLD (<code>change_class</code>, <code>shoreline</code>, <code>hotspot</code>, <code>reference</code>), publish lớp ở EPSG:4326, bật WFS (tối đa 5.000 đối tượng mỗi yêu cầu) và nhóm lớp nền <code>nen_ban_do</code>. Lớp transect lấy từ view <code>transects_styled</code>.',
                'method' => 'UPSERT theo khoá riêng của từng bảng: đường bờ theo (năm, chỉ số nước), transect theo mã transect, khoảng cách theo (transect, năm), thay vì xoá rồi tạo lại. Lớp nền và lớp chuyên đề cùng một workspace nên web gọi một địa chỉ WMS. Nếu không cấu hình GeoServer, web vẽ GeoJSON từ API bằng Leaflet.',
                'qa' => 'Lệnh nạp chạy trong một giao dịch: lỗi ở lớp nào thì cả đợt được hoàn tác. Script GeoServer chạy lặp được (idempotent) và nạp lại cấu hình sau khi cập nhật để cột mới như <code>unc_lrr</code> xuất hiện trong WFS.',
            ],
            'figs' => [],
        ],
    ];

    $limits = [
        'Đối chiếu sạt lở khớp 2/2 sự kiện theo tiêu chí khoảng cách, nhưng khớp yếu: điểm nóng gần nhất cách vùng cảnh báo 355 m (ngưỡng 500 m), còn trong 70 transect của vùng đệm 100 m (38 bồi, 32 ổn định) không transect nào lùi có ý nghĩa thống kê (LRR trung bình +1,15 m/năm). Hai sự kiện dùng chung một vùng nên không độc lập.',
        'Kết quả nhạy với cách giải đoán đường bờ. Đường bờ chính hiện do chuỗi QGIS giải đoán (SHORELINE_ENGINE = qgis). Cùng ảnh, bản Python trước đó cho 0/2 sự kiện (điểm nóng gần nhất cách 5.234 m), 267 transect và 1 transect lùi mạnh thay vì 4. Chuỗi QGIS được chọn làm đường bờ chính sau khi đã có kết quả bản Python; đây là quyết định của đồ án, không phải kết quả khách quan.',
        'Chọn chỉ số có yếu tố chủ quan: AWEI có F1 trung bình cao hơn MNDWI 0,0001 (0,9457 so với 0,9456). Hai chỉ số được coi là hoà nhờ ngưỡng 0,005 đặt sau khi đã thấy kết quả, rồi chọn MNDWI vì RMSE đường bờ thấp hơn (18,45 m so với 60,01 m). Thử đổi ngưỡng hoà: từ 0,00007 trở lên vẫn chọn MNDWI, kể cả khi bỏ lần lượt từng năm ground truth; chỉ khi ngưỡng bằng 0 mới chọn AWEI.',
        'Ground truth nhỏ: 5 ô mẫu và chỉ 3 năm (2018, 2022, 2026). Sáu năm còn lại dùng U<sub>classify</sub> = 21,7 m, là giá trị lớn nhất đã đo làm tròn lên chứ không phải số đo riêng. Ước lượng riêng từng năm từ độ nhạy theo ngưỡng (hiệu chuẩn bằng 3 năm có ground truth) cho 18,1 đến 24,3 m ở sáu năm đó; riêng 2019 là 24,3 m, cao hơn mức 21,7 m đang dùng. Đây là ước lượng của đồ án, không phải số đo.',
        'Độ bất định lớn hơn tín hiệu ở nhiều nơi: U<sub>shoreline</sub> 18,5 đến 23,1 m, trong khi LRR của các transect chỉ từ −3,76 đến +4,73 m/năm. Ở T0094, |EPR| = 2,65 m/năm nhỏ hơn độ bất định EPR 7,24 m/năm (95%), trong khi khoảng tin cậy của LRR (±1,21) chỉ tính từ độ lệch quanh đường hồi quy, không cộng sai số của từng đường bờ. Hai cách tính độ bất định cho kết luận khác nhau về cùng một transect. Nếu cộng sai số từng đường bờ vào khoảng tin cậy thì khoảng này thành ±6,70 m/năm và không transect nào trong 266 còn có ý nghĩa (T0094 chuyển từ lùi mạnh sang ổn định), trong khi cách đang dùng cho 85 transect có ý nghĩa. Phép cộng có thể phóng đại vì sai số giữa các năm không độc lập (χ² rút gọn trung vị 0,027). Vì vậy LRR nên đọc là xu hướng, không phải kết luận thống kê cho từng transect.',
        'Sai số định vị 12 m ở mức 95,5% được quy về 6 m ở 1σ, nửa pixel được coi là 1σ, và công thức gộp U<sub>shoreline</sub> là suy luận của đồ án, không gán cho nguồn nào.',
        'Đối chiếu chéo cho sai lệch lớn hơn ground truth: so với ESA WorldCover và Impact Observatory, đường bờ pipeline nằm lệch về phía sông trung bình 24,6 đến 34,3 m, RMSE 47,4 đến 68,4 m. Hai sản phẩm đó là ảnh cả năm, không cùng mùa với ảnh tháng 1–3, nên chỉ là kiểm tra thô. Khoảng 87 đến 93% tổng bình phương độ lệch đến từ một số ít transect lệch trên 50 m, tập trung ở cùng vài đoạn qua các năm; bỏ chúng thì RMSE còn 15,1 đến 19,6 m. Dựng lại composite theo từng cửa sổ hai tháng của 2020–2021 (các cửa sổ có từ 3 cảnh) thì độ lệch vẫn 20 đến 30 m, nên mùa không giải thích được. Mực nước và thuỷ triều lúc chụp chưa kiểm tra được vì không có số liệu trạm đo; nguyên nhân của các đoạn lệch lớn vẫn chưa rõ.',
        'Mỗi năm chỉ một composite mùa khô, từ 4 đến 13 cảnh tuỳ năm, chưa hiệu chỉnh mực nước. Ngưỡng Otsu đổi theo năm từ 0,0404 (2024) đến 0,1633 (2018) nên một phần chênh lệch giữa các năm có thể do ngưỡng, không do bờ dịch chuyển. Chạy thử với ngưỡng cố định (dựng đường bờ bằng chuỗi Python), LRR tương quan 0,89 (ngưỡng 0,0) đến 0,94 (ngưỡng bằng trung vị Otsu) với kết quả chính, nhưng 55 đến 61 transect đổi nhóm và số transect lùi mạnh giảm từ 4 xuống 1 đến 2. Ngày ảnh hiển thị trên web là ngày của cảnh dùng cuối, không phải ngày của cả composite.',
        'Các quy ước do đồ án đặt: khử đột biến 50 m (9 quan sát bị loại), lùi mạnh khi LRR ≤ −2 m/năm và giảm liên tục từ 3 mốc năm. 140 trong 266 transect có R² dưới 0,3 (đều thuộc nhóm ổn định). Kênh rộng dưới khoảng 100 m không được theo dõi do bước làm sạch mặt nạ.',
    ];
@endphp
<section class="steps" id="giai-doan-anh" aria-labelledby="steps-title">
    <div class="steps-inner">
        <header class="steps-head">
            <p class="eyebrow">Quy trình giải đoán ảnh</p>
            <h2 id="steps-title">Từ ảnh vệ tinh đến đường bờ và tốc độ biến động</h2>
            <p class="lead">Mười một bước dưới đây đúng với những gì pipeline Python đã chạy trong repo, từ thu thập ảnh đến xuất lên GeoServer. Hình minh hoạ vẽ lại từ dữ liệu trung gian thật của pipeline, không phải ảnh mô phỏng. Mỗi bước có phần chi tiết kỹ thuật bấm để mở; cuối quy trình là các hạn chế chưa giải quyết được.</p>
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
                                <dt>Công thức, thuật toán</dt><dd>{!! $s['more']['method'] !!}</dd>
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
                    <img src="{{ $img.$s['img'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" alt="{{ $s['alt'] }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async">
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
