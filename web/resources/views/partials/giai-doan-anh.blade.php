@php
    $img = '/img/phuong-phap/';
    $steps = [
        [
            'id' => 'buoc-1', 'title' => 'Ảnh đầu vào: composite mùa khô', 'tag' => 'Sentinel-2 L2A · s1_composite.py',
            'img' => 'b1-composite.webp', 'w' => 778, 'h' => 521,
            'alt' => 'Ảnh composite giả màu năm 2026 quanh xã Bình Mỹ, sông Hậu màu xanh thẫm, ranh giới xã nét đứt vàng.',
            'cap' => 'Composite mùa khô 2026, giả màu SWIR1 · NIR · Xanh lục (nước gần đen, thực vật xanh lục sáng). Nét đứt vàng là ranh giới xã.',
            'body' => [
                'Mỗi năm pipeline lấy các cảnh Sentinel-2 L2A tháng 1–3 (ô 48PWS) từ Microsoft Planetary Computer: năm 2026 dùng 6 cảnh, các năm khác từ 4 đến 13 cảnh.',
                'Pixel mây, bóng mây, tuyết và bão hoà bị loại bằng lớp phân loại cảnh SCL; cảnh có hơn 30% pixel bị che trong vùng tải bị bỏ. Các kênh B03, B08, B11, B12 được đưa về 10 m rồi lấy trung vị thành một ảnh duy nhất cho năm đó.',
            ],
        ],
        [
            'id' => 'buoc-2', 'title' => 'Làm nổi mặt nước bằng chỉ số', 'tag' => 'MNDWI · s2_water.py',
            'img' => 'b2-mndwi.webp', 'w' => 751, 'h' => 419,
            'alt' => 'Bản đồ chỉ số MNDWI năm 2026: sông Hậu và ao hồ màu xanh dương đậm, đất liền màu đỏ cam.',
            'cap' => 'MNDWI năm 2026. Vùng xanh dương là nước, đỏ cam là đất; ngưỡng Otsu năm này là 0,113.',
            'body' => [
                'Pipeline tính ba chỉ số nước là NDWI, MNDWI và AWEI. Mỗi chỉ số được tách nước/đất bằng ngưỡng Otsu trong ranh giới xã cộng đệm 1 km.',
                'Chọn chỉ số dựa trên ground truth số hoá thủ công (5 ô mẫu × 3 năm). F1 trung bình của MNDWI (0,9456) và AWEI (0,9457) gần như hoà, nên xét tiếp sai số đường bờ: MNDWI 17,83 m, NDWI 28,11 m, AWEI 62,54 m. MNDWI được chọn. Ngưỡng hoà 0,005 đặt sau khi đã thấy kết quả; đây là một lựa chọn của đồ án, không phải kết quả khách quan.',
            ],
        ],
        [
            'id' => 'buoc-3', 'title' => 'Mặt nạ nước và làm sạch', 'tag' => 'Hình thái học · s3_shoreline.py',
            'img' => 'b3-mat-na-nuoc.webp', 'w' => 778, 'h' => 521,
            'alt' => 'Mặt nạ nước nhị phân năm 2026: sông Hậu màu xanh, đất màu nâu nhạt.',
            'cap' => 'Mặt nạ nước 2026 sau ngưỡng MNDWI (xanh: nước, nâu: đất).',
            'body' => [
                'Mặt nạ được làm sạch trước khi trích đường bờ: mở hình thái để bỏ kênh rạch hẹp dưới khoảng 100 m, giữ vùng nước liên thông lớn nhất (sông Hậu) và lấp các lỗ nhỏ hơn 5 ha.',
                'Việc này nhằm chỉ theo dõi bờ sông chính, không để ao nuôi, kênh nội đồng hay nhiễu nhỏ trở thành đường bờ.',
            ],
        ],
        [
            'id' => 'buoc-4', 'title' => 'Trích đường bờ từng năm', 'tag' => 'Contour 0,5 · s3_shoreline.py',
            'img' => 'b4-duong-bo.webp', 'w' => 688, 'h' => 485,
            'alt' => 'Chín đường bờ từ 2018 đến 2026 vẽ chồng lên ảnh composite, mỗi năm một màu.',
            'cap' => 'Chín đường bờ 2018–2026 chồng trên composite 2026, phóng to quanh đoạn có điểm nóng lùi bờ.',
            'body' => [
                'Đường bờ là đường đồng mức 0,5 của mặt nạ nước (độ chính xác dưới pixel), cắt theo ranh giới xã và đơn giản hoá 5 m. Kết quả là chín đường, mỗi năm một đường, 2018–2026.',
                'Độ bất định của từng đường bờ gộp từ nửa pixel (5 m), định vị ESA (6 m ở mức 1σ) và sai số phân loại đo trên ground truth (15,1 đến 20 m). Gộp lại, U<sub>shoreline</sub> nằm trong khoảng 17 đến 21,5 m.',
            ],
        ],
        [
            'id' => 'buoc-5', 'title' => 'Baseline và transect', 'tag' => '267 transect · s4_transects.py',
            'img' => 'b5-transect.webp', 'w' => 688, 'h' => 485,
            'alt' => 'Các transect vuông góc với baseline đỏ nét đứt, tô màu theo mức biến động: nhiều đoạn xám, vài đoạn xanh dương và cam, một đoạn đỏ.',
            'cap' => 'Baseline (đỏ nét đứt) và transect cách 50 m, tô theo mức biến động: xanh dương bồi, xám ổn định, cam lùi nhẹ, đỏ lùi mạnh.',
            'body' => [
                'Baseline là đường bờ năm đầu làm trơn, dịch 250 m vào phía đất. Từ đó sinh transect vuông góc mỗi 50 m, dài 600 m ra phía sông; transect cắt nhau ở chỗ bờ cong gắt bị bỏ. Kết quả có 267 transect.',
                'Tại mỗi transect, pipeline đo khoảng cách từ baseline tới giao điểm đường bờ của từng năm. Năm nào không có số đo hợp lệ thì để trống, không nội suy.',
            ],
        ],
        [
            'id' => 'buoc-6', 'title' => 'Tốc độ biến động theo transect', 'tag' => 'NSM · EPR · LRR · R² · s5_stats.py',
            'img' => 'b6-chuoi-nam.webp', 'w' => 670, 'h' => 397,
            'alt' => 'Biểu đồ khoảng cách từ baseline đến đường bờ giảm dần từ 188 m năm 2018 xuống 165 m năm 2026, kèm đường hồi quy.',
            'cap' => 'Chuỗi vị trí đường bờ tại transect T0094 và đường hồi quy tuyến tính: LRR = −2,56 m/năm, R² = 0,73.',
            'body' => [
                'Với chuỗi khoảng cách theo năm, pipeline tính NSM (dịch chuyển giữa đầu và cuối), EPR (NSM chia số năm), LRR (hệ số góc hồi quy tuyến tính), sai số chuẩn SE và R². Quan sát lệch quá 50 m so với trung vị chuỗi được loại trước (13 quan sát trên toàn bộ dữ liệu).',
                'Ở T0094, khoảng cách giảm từ 187,99 m (2018) xuống 165,33 m (2026), tức bờ lùi 22,66 m. LRR là −2,56 m/năm với khoảng tin cậy 95% là ±1,40 m, không chứa 0.',
            ],
        ],
        [
            'id' => 'buoc-7', 'title' => 'Phân loại mức biến động', 'tag' => 'Mục 2.5 đề cương · s5_stats.py',
            'img' => 'phan_loai.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ phân loại mức biến động bờ sông Hậu tại Bình Mỹ theo bốn mức: bồi, ổn định, lùi nhẹ, lùi mạnh.',
            'cap' => 'Bản đồ phân loại 267 transect: 81 bồi, 167 ổn định, 18 lùi nhẹ, 1 lùi mạnh.',
            'body' => [
                'Một transect có biến động thực sự khi |LRR| lớn hơn t × SE(LRR) (t = 2,365 với 9 năm, tin cậy 95%). Lùi mạnh cần thêm LRR ≤ −2 m/năm và khoảng cách giảm liên tục ở ít nhất 3 mốc năm liền nhau. Tiêu chí được chốt trước khi chạy lại, không chỉnh theo kết quả.',
                'Kết quả: 81 transect bồi, 167 ổn định, 18 lùi nhẹ và chỉ 1 lùi mạnh (T0094), tạo thành 1 điểm nóng dài khoảng 50 m. Đa số đoạn bờ không có biến động đủ lớn so với độ bất định của ảnh 10 m.',
            ],
        ],
        [
            'id' => 'buoc-8', 'title' => 'Đối chiếu và giới hạn', 'tag' => 'Kiểm chứng · s9_validate.py',
            'img' => 'doi_chieu_2021.webp', 'w' => 1600, 'h' => 1131,
            'alt' => 'Bản đồ đối chiếu vùng cảnh báo sạt lở 2019–2020 với kết quả phân loại của pipeline.',
            'cap' => 'Vùng cảnh báo sạt lở 2019–2020 dọc QL91 đặt cạnh kết quả phân loại.',
            'body' => [
                'Kết quả được đối chiếu với hai sự kiện sạt lở đã công bố (01/08/2019 và 27/05/2020). Nguồn chỉ nêu lý trình Km nên vùng cảnh báo dài khoảng 3,3 km và hai sự kiện dùng chung một hình học, không độc lập.',
                'Pipeline không khớp: điểm nóng gần nhất cách vùng cảnh báo 5.234 m (ngưỡng khớp 500 m), nên 0/2 sự kiện khớp. Trong vùng đệm 100 m có 69 transect (41 bồi, 28 ổn định), không transect nào lùi có ý nghĩa thống kê. Nguyên nhân có thể là ảnh 10 m, mỗi năm một ảnh, hoặc công trình gia cố sau sự cố, nhưng đây là giả thuyết chưa kiểm chứng. Ngưỡng không được chỉnh để cho khớp.',
            ],
        ],
    ];
@endphp
<section class="steps" id="giai-doan-anh" aria-labelledby="steps-title">
    <div class="steps-inner">
        <header class="steps-head">
            <p class="eyebrow">Quy trình giải đoán ảnh</p>
            <h2 id="steps-title">Từ ảnh vệ tinh đến đường bờ và tốc độ biến động</h2>
            <p class="lead">Tám bước dưới đây đúng với những gì pipeline Python đã chạy trong repo. Hình minh hoạ vẽ lại từ dữ liệu trung gian thật của pipeline, không phải ảnh mô phỏng. Cuộn xuống để theo từng bước.</p>
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
                </div>
                <figure class="step-fig">
                    <img src="{{ $img.$s['img'] }}" width="{{ $s['w'] }}" height="{{ $s['h'] }}" alt="{{ $s['alt'] }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async">
                    <figcaption>{{ $s['cap'] }}</figcaption>
                </figure>
            </li>
            @endforeach
        </ol>
    </div>
</section>
