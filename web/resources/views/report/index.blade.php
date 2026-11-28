@php
    // Trạng thái: done = đã xong, part = xong một phần / còn hạn chế, todo = chưa làm.
    $progress = [
        ['2.1', 'Phân tích yêu cầu hệ thống', 'done', 'Yêu cầu, phạm vi và kiến trúc ghi trong README và báo cáo đồ án (docs/).'],
        ['2.2', 'Khu vực nghiên cứu, dữ liệu ảnh và dữ liệu tham chiếu', 'done', 'Ranh giới xã Bình Mỹ từ OSM, ảnh Sentinel-2 L2A mùa khô 2018–2026 nạp trong QGIS qua STAC (Earth Search), sự kiện sạt lở 2019–2020 và các số quyết định.'],
        ['2.3', 'Xử lý ảnh và trích xuất đường bờ (QGIS)', 'done', 'Làm hoàn toàn bằng giao diện QGIS 3.40 cho cả 9 năm: cắt vùng, che mây SCL, composite trung vị, MNDWI, ngưỡng nước, lọc đốm, vector hoá, baseline và 270 transect; hơn 350 ảnh chụp từng bước (docs/minh_chung/qgis/).'],
        ['2.4', 'Đánh giá độ chính xác và kiểm chứng', 'done', 'Ground truth số hoá 5 ô mẫu (2018, 2022, 2026), RMSE đường bờ 16,73–21,69 m đưa vào U_classify; chọn MNDWI vì sai số đường bờ thấp nhất (18,45 m, so với NDWI 27,83 m và AWEI 60,01 m); đối chiếu chéo ESA WorldCover và Impact Observatory LULC.'],
        ['2.5', 'Tiêu chí phân loại và độ bất định', 'done', 'NSM/EPR/LRR/R² tính bằng Field Calculator của QGIS, kiểm định |LRR| > t × SE (t Student 95%, n − 2 bậc tự do; chuỗi 9 năm t = 2,365), bốn mức biến động, lùi mạnh khi LRR ≤ −2 m/năm và giảm liên tục ≥ 3 mốc năm, U_georef 12 m (2σ) quy về 6 m (1σ).'],
        ['2.6', 'Kiến trúc, cơ sở dữ liệu và giao diện WebGIS', 'done', 'MySQL (người dùng, cấu hình) + PostGIS (không gian), GeoServer WMS (tuỳ chọn, một lớp chung tô màu đường bờ theo năm), Leaflet + Chart.js; giao diện sáng thiết kế v3 theo hệ Transect, hỗ trợ bàn phím và cảm ứng.'],
        ['3.5', 'Đối chiếu với sạt lở tham chiếu 2019–2020', 'part', 'Nguồn công khai chỉ có lý trình Km, chưa có toạ độ nên dùng vùng cảnh báo ~3,3 km; kết quả hiện không phát hiện đoạn lùi bờ trong vùng này (đã ghi rõ ở trang Phương pháp).'],
        ['3.6.2', 'REST API và bảo mật', 'done', 'Sanctum, khoá đăng nhập sau 5 lần sai theo email + IP, token hết hạn sau 480 phút, kiểm tra đầu vào, ghi nhật ký thay đổi ngưỡng; tài khoản demo tạo từ biến môi trường DEMO_*, không để mật khẩu trong mã nguồn.'],
        ['3.6.3', 'Giao diện người dùng', 'done', 'Bản đồ, biểu đồ transect, hotspot, so sánh hai năm, chia sẻ liên kết, tải GeoJSON/CSV, trang Phương pháp (kèm 9 bước giải đoán ảnh có hình và chi tiết kỹ thuật), Quản trị ngưỡng, Hướng dẫn và Báo cáo; icon thống nhất ở mọi trang, gợi ý lần đầu vào bản đồ, cài được như ứng dụng (manifest, icon).'],
        ['—', 'Hiệu năng tải trang và API', 'done', 'Gộp mọi năm đường bờ vào một lớp WMS (126 xuống 14 request tile), nhớ tạm 60 giây và gộp yêu cầu GET /api, lớp transect/hotspot và Chart.js chỉ tải khi cần, truy vấn GeoJSON bỏ cột geom nguyên bản, cache asset và nén trên Apache.'],
        ['3.7', 'Kiểm thử', 'done', 'Unit/Feature PHP, Vitest, pytest cho bản đối chiếu bằng Python và Playwright E2E (xem số liệu bên dưới); CI trên GitHub Actions.'],
        ['—', 'Báo cáo đồ án (docx) và hình minh chứng', 'part', 'Đã có bản báo cáo, bản đồ QGIS, sơ đồ và ảnh minh chứng trong docs/; cần rà lại số liệu sau mỗi lần chạy lại pipeline.'],
        ['—', 'Toạ độ chính xác điểm sạt lở Km 89+272 / 89+076', 'todo', 'Chưa có nguồn công bố toạ độ; cần khảo sát hoặc xin dữ liệu từ cơ quan quản lý.'],
    ];
    $count = fn (string $s) => count(array_filter($progress, fn ($r) => $r[2] === $s));
    $done = $count('done');
    $total = count($progress);
    $labels = ['done' => 'Hoàn thành', 'part' => 'Còn hạn chế', 'todo' => 'Chưa làm'];
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.head', ['title' => 'Báo cáo công việc đã làm — WebGIS Sông Hậu', 'description' => 'Báo cáo công việc của đề tài WebGIS giám sát biến động bờ sông Hậu (nội bộ).'])
    @vite(['resources/css/app.css', 'resources/js/back-to-top.js'])
</head>
<body class="page">
@include('partials.nav')
<main id="main-content" tabindex="-1">
<header class="page-head">
    <div class="page-head-inner">
        <p class="eyebrow">Trang nội bộ, chỉ quản trị viên</p>
        <h1>Báo cáo công việc đã làm</h1>
        <p class="lead">Tổng hợp từ lịch sử git và các tính năng đã hoàn thành của đề tài “WebGIS giám sát biến động bờ sông Hậu, xã Bình Mỹ, An Giang”,
        đối chiếu với đề cương. Trang nội bộ, chỉ tài khoản admin xem được. Số liệu chốt ngày 27/11/2026.</p>
        <dl class="page-stamp" aria-label="Thông tin tài liệu">
            <div><dt>Phạm vi</dt><dd>Nội bộ · admin</dd></div>
            <div><dt>Số liệu</dt><dd>27/11/2026</dd></div>
            <div><dt>Hạng mục</dt><dd>{{ $done }}/{{ $total }} hoàn thành</dd></div>
        </dl>
    </div>
</header>
<div class="doc-layout">
<nav class="doc-toc" aria-label="Mục lục">
    <p class="doc-toc-title">Mục lục</p>
    <a href="#so-lieu">Số liệu chung</a>
    <a href="#tien-do">Tiến độ theo đề cương</a>
    <a href="#moc-thoi-gian">Mốc thời gian</a>
    <a href="#doi-chieu">Đối chiếu QGIS và Python</a>
    <a href="#tinh-nang">Tính năng đã làm</a>
    <a href="#kiem-thu">Kiểm thử</a>
    <a href="#con-lai">Việc còn lại</a>
</nav>
<article class="doc">

    <section id="so-lieu">
        <h2>Số liệu chung</h2>
        <table class="stats">
            <caption>Các con số chính của đề tài tính đến ngày 27/11/2026.</caption>
            <tbody>
                <tr><th scope="row">Commit trên nhánh chính</th><td class="v">1.378</td><td class="n">9 ngày có commit, từ 27/09 đến 27/11/2026</td></tr>
                <tr><th scope="row">Transect phân tích</th><td class="v">{{ $transectCount }}</td><td class="n">mặt cắt vuông góc với bờ</td></tr>
                <tr><th scope="row">Hotspot lùi mạnh</th><td class="v">{{ $hotspotCount }}</td><td class="n">gộp từ {{ $severeCount }} transect lùi mạnh</td></tr>
                <tr><th scope="row">Năm đường bờ</th><td class="v">9</td><td class="n">giai đoạn 2018–2026</td></tr>
                <tr><th scope="row">Hạng mục đề cương hoàn thành</th><td class="v">{{ $done }}/{{ $total }}</td><td class="n">{{ round($done / $total * 100) }}% tổng số hạng mục</td></tr>
            </tbody>
        </table>
        <figure class="fig">
            <div class="progress" style="--n: {{ $total }}" role="progressbar" aria-label="Tiến độ theo đề cương" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}"><i style="width: {{ round($done / $total * 100) }}%"></i></div>
            <p class="progress-caption"><span>0</span><span>{{ $done }} / {{ $total }} hạng mục ({{ round($done / $total * 100) }}%)</span><span>{{ $total }}</span></p>
            <figcaption>Tiến độ thực hiện theo đề cương; mỗi ô là một hạng mục.</figcaption>
        </figure>
    </section>

    <section id="tien-do">
        <h2>Tiến độ theo đề cương</h2>
        <div class="table-wrap"><table>
            <caption>Đối chiếu từng hạng mục của đề cương với kết quả và bằng chứng.</caption>
            <tr><th>Mục</th><th>Nội dung</th><th>Trạng thái</th><th>Kết quả / bằng chứng</th></tr>
            @foreach ($progress as [$no, $name, $state, $note])
                <tr>
                    <td class="code">{{ $no }}</td>
                    <td>{{ $name }}</td>
                    <td><span class="badge badge-{{ $state }}">{{ $labels[$state] }}</span></td>
                    <td>{{ $note }}</td>
                </tr>
            @endforeach
        </table></div>
    </section>

    <section id="moc-thoi-gian">
        <h2>Mốc thời gian</h2>
        <ol class="history">
            <li><time datetime="2026-09-27">27/09/2026 — 65 commit</time>
                Dựng khung Laravel, lược đồ MySQL/PostGIS, REST API, lệnh nhập dữ liệu và cấu hình GeoServer; giao diện bản đồ (Leaflet, WMS, biểu đồ transect, chia sẻ liên kết).
                Dựng pipeline Sentinel-2 từ tải ảnh đến thống kê NSM/EPR/LRR, nạp dữ liệu demo lần 1; bộ số hoá ground truth.</li>
            <li><time datetime="2026-10-22">22/10/2026 — 20 commit</time>
                Tách thư mục web/, thêm CI và README tổng quan, sửa CI thiếu thư mục kiểm thử.</li>
            <li><time datetime="2026-10-24">24/10/2026 — 131 commit</time>
                Đối chiếu chéo với ESA WorldCover và Impact Observatory, bản đồ chuyên đề QGIS, chốt U_georef; nền bản đồ tự phục vụ trên GeoServer
                (bỏ nền Google), khoá khung nhìn vào khu vực nghiên cứu; sơ đồ và ảnh minh chứng; chuyển GeoJSON sang đuôi .json.</li>
            <li><time datetime="2026-11-07">07/11/2026 — 121 commit</time>
                Vector hoá đường bờ dưới pixel và chạy lại kết quả; đưa RMSE ground truth vào độ bất định; bổ sung mạnh kiểm thử;
                sửa lỗi API (bbox dạng mảng, id quá lớn, ngưỡng âm/quá dài); khoá đăng nhập sai và token hết hạn.</li>
            <li><time datetime="2026-11-23">23/11/2026 — 147 commit</time>
                Truy cập: liên kết bỏ qua điều hướng, focus, vùng chạm 44 px, giảm chuyển động, tương phản; giao diện sáng thống nhất theo hệ Transect (bỏ chế độ tối);
                kiểm thử JavaScript; thêm trang Hướng dẫn sử dụng và Báo cáo công việc (trang này); chốt trước tiêu chí phân loại (ngưỡng lùi mạnh tuyệt đối, độ tin cậy 95%, U_georef quy về 1σ).</li>
            <li><time datetime="2026-11-24">24/11/2026 — 114 commit</time>
                Dải tiêu đề trang Giới thiệu &amp; phương pháp, lớp nền Việt Nam từ Natural Earth, cache công khai kèm ETag cho API đọc, kiểm tra sức khoẻ /healthz và trạng thái GeoServer.</li>
            <li><time datetime="2026-11-25">25/11/2026 — 43 commit</time>
                Thiết kế lại giao diện: design tokens mới, viết lại thanh thời gian, khung cho màn hình nhỏ và trang đăng nhập.</li>
            <li><time datetime="2026-11-26">26/11/2026 — 614 commit</time>
                Giao diện v3: tìm kiếm, bảng thuộc tính và cây lớp theo phong cách atlas, phông hệ thống; nạp đường bờ chính từ bản QGIS giải đoán; cấu hình lớp lưu ở cơ sở dữ liệu.</li>
            <li><time datetime="2026-11-27">27/11/2026 — 123 commit</time>
                Tăng tốc tải trang (một lớp WMS cho mọi năm, nhớ tạm API, tải chậm lớp và biểu đồ); icon cho các trang phụ, icon tab và manifest; gợi ý lần đầu vào bản đồ;
                9 bước giải đoán ảnh ở trang Phương pháp; tài khoản demo qua biến môi trường; README và ảnh giao diện v3.</li>
            <li><time datetime="2026-11-28">28/11/2026</time>
                Làm lại toàn bộ chuỗi dữ liệu bằng giao diện QGIS 3.40 (9 năm đường bờ, baseline, 270 transect, độ bất định, hotspot) và chụp ảnh từng bước làm minh chứng;
                web chuyển sang dùng dữ liệu QGIS làm dữ liệu chính, bản tính bằng Python giữ làm đối chiếu; trang Giới thiệu chỉ mô tả cách làm bằng QGIS.</li>
        </ol>
    </section>

    <section id="doi-chieu">
        <h2>Đối chiếu QGIS và Python, lý do chọn QGIS</h2>
        <p>Cùng một đề tài được thực hiện hai lần: một chuỗi script Python tự động (bản tính ban đầu) và một chuỗi làm hoàn toàn bằng giao diện QGIS 3.40
            (bản dữ liệu chính hiện nay của web). Phần này so sánh hai bản và nêu lý do dùng QGIS làm dữ liệu chính.</p>

        <h3>Đường bờ: hai bản khớp nhau</h3>
        <div class="table-wrap"><table>
            <caption>Đường bờ QGIS đối chiếu với đường bờ Python từng năm. Lấy mẫu mỗi 10 m trên đường bờ QGIS, đo tới đường bờ Python.</caption>
            <tr><th>Năm</th><th>Dài QGIS (km)</th><th>Dài Python (km)</th><th>Trong 10 m</th><th>Trong 20 m</th><th>Lệch TB (m)</th></tr>
                <tr><td class="code">2018</td><td>15,28</td><td>15,12</td><td>96,8%</td><td>99,5%</td><td>4,0</td></tr>
                <tr><td class="code">2019</td><td>15,35</td><td>15,25</td><td>96,7%</td><td>99,6%</td><td>4,0</td></tr>
                <tr><td class="code">2020</td><td>15,12</td><td>14,70</td><td>97,6%</td><td>100,0%</td><td>3,8</td></tr>
                <tr><td class="code">2021</td><td>15,37</td><td>14,92</td><td>96,6%</td><td>100,0%</td><td>3,7</td></tr>
                <tr><td class="code">2022</td><td>15,68</td><td>15,13</td><td>81,8%</td><td>93,1%</td><td>8,9</td></tr>
                <tr><td class="code">2023</td><td>15,10</td><td>15,06</td><td>97,0%</td><td>99,9%</td><td>4,0</td></tr>
                <tr><td class="code">2024</td><td>15,62</td><td>14,67</td><td>93,1%</td><td>96,9%</td><td>7,3</td></tr>
                <tr><td class="code">2025</td><td>15,12</td><td>14,71</td><td>94,4%</td><td>99,7%</td><td>4,3</td></tr>
                <tr><td class="code">2026</td><td>15,22</td><td>14,74</td><td>95,5%</td><td>99,9%</td><td>4,1</td></tr>
        </table></div>
        <p class="hint">Hai năm lệch nhiều nhất là 2022 và 2024. Cảnh 04/02/2022 mang cờ <code>boa_offset_applied = False</code> nên phải trừ 1000 riêng cho cảnh này trong QGIS;
            nguồn ảnh cũng khác (QGIS dùng COG Earth Search, bản Python dùng composite Planetary Computer).</p>

        <h3>Transect và thống kê biến động</h3>
        <p class="hint">Có hai bản để so: <strong>bản Python</strong> là chuỗi script chạy trọn từ đầu (đường bờ contour dưới pixel, bảng dưới đây),
            và <strong>bản hiển thị trước đó trên web</strong> (266 transect, 4 lùi mạnh) dùng đường bờ giải đoán bằng script QGIS với transect dựng bằng Python.
            Bản QGIS so với bản hiển thị trước đó: RMSE LRR 1,39 m/năm, 164 trong 261 transect ghép được cùng nhóm (trang Giới thiệu dùng so sánh này).</p>
        <div class="table-wrap"><table>
            <caption>Kết quả transect của hai bản.</caption>
            <tr><th>Chỉ tiêu</th><th>QGIS (dữ liệu chính)</th><th>Python (đối chiếu)</th></tr>
            <tr><td>Số transect</td><td>270</td><td>267</td></tr>
            <tr><td>Ổn định</td><td>209</td><td>167</td></tr>
            <tr><td>Bồi tụ</td><td>52</td><td>81</td></tr>
            <tr><td>Xói lở nhẹ</td><td>8</td><td>18</td></tr>
            <tr><td>Lùi bờ mạnh</td><td>1</td><td>1</td></tr>
            <tr><td>LRR trung bình (m/năm)</td><td>0,389</td><td>0,408</td></tr>
        </table></div>
        <p>Trên 262 transect có mặt ở cả hai bản, RMSE của LRR là 1,39 m/năm (sai lệch trung bình +0,03; trung vị trị tuyệt đối 0,74 m/năm) và 156 transect có cùng nhóm biến động.
            Phần lớn chênh lệch đến từ cách dựng baseline và hướng transect, không phải từ đường bờ: bản Python xác định phía đất bằng mặt nạ nước,
            bản QGIS xác định bằng mắt và quy tắc hình học. Khoảng cách baseline đến đường bờ vì thế lệch khoảng 20–40 m, đầu tuyến
            T0001–T0007 giao điểm gần nhất nhảy giữa khoảng 115 m và 350 m giữa các năm. Cả hai bản đều chỉ có 1 transect lùi mạnh.</p>

        <h3>Vì sao chọn QGIS làm dữ liệu chính</h3>
        <ul>
            <li><strong>Đúng yêu cầu của đề tài.</strong> Dữ liệu không gian phải làm bằng phần mềm GIS chuyên dụng và kiểm tra được; trong QGIS mọi bước là một hộp thoại có tham số
                và có ảnh chụp, người đọc dựng lại được mà không cần hiểu mã nguồn.</li>
            <li><strong>Truy vết từng bước.</strong> Mỗi năm có 28–50 ảnh chụp và một file <code>BUOC.md</code> ghi tham số; Processing ghi cả lệnh GDAL tương đương dưới mỗi hộp thoại.</li>
            <li><strong>Kết quả không kém.</strong> Đường bờ khớp bản Python 93–98% trong 10 m (trừ 2022: 82%), độ dài 15,1–15,7 km mỗi năm.</li>
            <li><strong>Có thể kiểm bằng mắt.</strong> Mỗi lớp trung gian (MNDWI, mặt nạ nước, đa giác nước lớn nhất) mở được trong QGIS để soát lỗi, như khi phát hiện cảnh 2022 còn offset 1000
                và lỗi tràn số khi che mây.</li>
        </ul>

        <h3>Hạn chế của bản QGIS</h3>
        <ul>
            <li>QGIS 3.40 không có công cụ Otsu: ngưỡng nước được tính riêng trên histogram của ảnh MNDWI rồi nhập vào Raster Calculator; histogram trong QGIS dùng để đối chiếu bằng mắt.</li>
            <li>Bảng hệ số t Student được nhúng trong biểu thức Field Calculator (làm tròn 3 chữ số); hotspot một transect là đoạn thẳng 50 m thay vì vùng đệm.</li>
            <li>Chạy lại từ đầu cho 9 năm mất nhiều giờ thao tác tay, trong khi chuỗi Python chạy trọn trong vài phút; vì vậy bản Python vẫn được giữ để kiểm tra nhanh khi đổi tham số.</li>
            <li>Baseline và hướng transect dựng thủ công nên lệch bản Python như đã nêu; kết quả LRR từng transect chỉ nên đọc theo nhóm biến động, không nên so tuyệt đối từng mét.</li>
        </ul>
    </section>

    <section id="tinh-nang">
        <h2>Tính năng đã làm</h2>
        <h3>Xử lý dữ liệu (QGIS)</h3>
        <ul>
            <li>Nạp ảnh Sentinel-2 L2A qua STAC, cắt theo ranh giới xã đệm 1 km (Clip Raster by Mask Layer chạy hàng loạt), che mây bằng SCL, composite trung vị (Cell Statistics).</li>
            <li>MNDWI bằng Raster Calculator, ngưỡng nước từ histogram, lọc đốm (Fill NoData, Sieve), vector hoá (Polygonize), mở hình thái bằng Buffer −50/+50 m, đường bờ cắt theo ranh giới xã.</li>
            <li>Baseline (Offset lines), transect cách 50 m dài 600 m, khoảng cách tới từng đường bờ, NSM/EPR/LRR/R², độ bất định, kiểm định ý nghĩa, phân loại Mục 2.5, hotspot, bản đồ bằng Print Layout.</li>
            <li>Kết quả xuất GeoJSON (EPSG:4326) rồi nạp vào PostGIS bằng <code>php artisan webgis:import</code>.</li>
        </ul>
        <h3>Bản đối chiếu (Python)</h3>
        <ul>
            <li>Chuỗi script Python tự động (s1–s9) chạy cùng quy trình, giữ lại làm bản đối chiếu; không hiển thị trên web.</li>
            <li>Đánh giá Precision/Recall/F1/IoU và RMSE từ ground truth số hoá; đối chiếu chéo sản phẩm phủ bề mặt độc lập.</li>
        </ul>
        <h3>Ứng dụng web (Laravel)</h3>
        <ul>
            <li>REST API tra cứu đường bờ, transect, hotspot, sự kiện tham chiếu, thống kê; xuất GeoJSON/CSV.</li>
            <li>Bản đồ tương tác: lớp bản đồ (GeoJSON từ API, hoặc WMS khi có GeoServer), tô màu theo mức biến động, đường bờ nhiều năm trong một lớp, so sánh hai năm, lọc hotspot, tìm kiếm, bảng thuộc tính, chia sẻ liên kết.</li>
            <li>Trang Phương pháp (9 bước giải đoán ảnh), Quản trị ngưỡng (có nhật ký), Hướng dẫn sử dụng và Báo cáo công việc (chỉ admin, chặn phía máy chủ).</li>
            <li>Giao diện sáng theo hệ thiết kế Transect (lưới 1 px, Cobalt/Vermilion), hỗ trợ bàn phím, trình đọc màn hình, màn hình cảm ứng, bố cục điện thoại dạng bottom sheet và cài được như ứng dụng web.</li>
        </ul>
        <h3>Hạ tầng</h3>
        <ul>
            <li>GeoServer (tuỳ chọn) với style SLD và layer group nền bản đồ tự phục vụ; PostGIS cho dữ liệu không gian, MySQL cho người dùng và cấu hình.</li>
            <li>CI GitHub Actions; E2E chạy trên CSDL và workspace GeoServer riêng.</li>
            <li>Cấu hình cache và nén cho máy chủ Apache; tài khoản demo seed từ biến môi trường <code>DEMO_*</code>.</li>
        </ul>
    </section>

    <section id="kiem-thu">
        <h2>Kiểm thử</h2>
        <div class="table-wrap"><table>
            <caption>Các bộ kiểm thử của dự án.</caption>
            <tr><th>Loại</th><th>Công cụ</th><th>Quy mô</th></tr>
            <tr><td>Unit + Feature (PHP)</td><td>PHPUnit</td><td>618 phương thức kiểm thử</td></tr>
            <tr><td>JavaScript giao diện</td><td>Vitest</td><td>160 ca (21 tệp)</td></tr>
            <tr><td>Pipeline</td><td>pytest</td><td>hơn 190 hàm kiểm thử</td></tr>
            <tr><td>Đầu–cuối</td><td>Playwright (Chromium, Firefox, WebKit)</td><td>167 kịch bản (501 lượt chạy trên 3 trình duyệt)</td></tr>
        </table></div>
        <p class="hint">Số liệu đếm theo mã nguồn ngày 27/11/2026 (Vitest đo bằng lần chạy thực), chưa tính ca sinh từ data provider. WebKit không chạy được trên Ubuntu mới nên có script <code>test:no-webkit</code>.</p>
    </section>

    <section id="con-lai">
        <h2>Việc còn lại và rủi ro</h2>
        <ul>
            <li>Toạ độ chính xác của điểm sạt lở tham chiếu (Km 89+272 / 89+076) chưa có; các số quyết định lấy từ báo chí, chưa đối chiếu bản gốc.</li>
            <li>Dữ liệu QGIS chưa phát hiện đoạn lùi bờ quanh vùng sạt lở 2019–2020 (74 transect trong 300 m: 22 bồi, 52 ổn định; hotspot gần nhất cách khoảng 5,2 km); cần trình bày như hạn chế của phương pháp, không như bằng chứng khớp thực tế.</li>
            <li>Mỗi năm chỉ có một đường bờ mùa khô và số cảnh dùng chênh nhiều giữa các năm (4 đến 13 cảnh), nên chất lượng composite không đồng đều.</li>
            <li>Ground truth mới có 5 ô mẫu; nếu bổ sung thêm ô và năm thì cần đánh giá lại và cập nhật U_classify.</li>
            <li>Rà lại số liệu trong báo cáo docx: dữ liệu chính đã chuyển sang bản QGIS (270 transect, 1 hotspot), nhiều số trong báo cáo cũ cần cập nhật.</li>
        </ul>
    </section>
</article>
</div>
</main>
<script>
    // Đánh dấu mục đang đọc trong mục lục.
    (() => {
        const links = [...document.querySelectorAll('.doc-toc a')];
        if (!links.length || !('IntersectionObserver' in window)) return;
        const byId = new Map(links.map((a) => [a.getAttribute('href').slice(1), a]));
        const io = new IntersectionObserver((es) => es.forEach((e) => {
            if (!e.isIntersecting) return;
            links.forEach((a) => a.classList.remove('is-current'));
            byId.get(e.target.id)?.classList.add('is-current');
        }), { rootMargin: '0px 0px -70% 0px' });
        byId.forEach((_, id) => document.getElementById(id) && io.observe(document.getElementById(id)));
    })();
</script>
@include('partials.back-to-top')
</body>
</html>
