@php
    // Trạng thái: done = đã xong, part = xong một phần / còn hạn chế, todo = chưa làm.
    $progress = [
        ['2.1', 'Phân tích yêu cầu hệ thống', 'done', 'Yêu cầu, phạm vi và kiến trúc ghi trong README và báo cáo đồ án (docs/).'],
        ['2.2', 'Khu vực nghiên cứu, dữ liệu ảnh và dữ liệu tham chiếu', 'done', 'Ranh giới xã Bình Mỹ từ OSM, composite Sentinel-2 L2A mùa khô 2018–2026 (Planetary Computer), sự kiện sạt lở 2019–2020 và các số quyết định.'],
        ['2.3', 'Pipeline xử lý ảnh và trích xuất đường bờ', 'done', 'Composite trung vị, NDWI/MNDWI/AWEI + Otsu, vector hoá dưới pixel, baseline và transect vuông góc (pipeline/s1–s4).'],
        ['2.4', 'Đánh giá độ chính xác và kiểm chứng', 'done', 'Ground truth số hoá 5 ô mẫu (2018, 2022, 2026), RMSE đường bờ 15,1–20,0 m đưa vào U_classify; đối chiếu chéo ESA WorldCover và Impact Observatory LULC.'],
        ['2.5', 'Tiêu chí phân loại và độ bất định', 'done', 'NSM/EPR/LRR/R², kiểm định |LRR| > 1,96 × SE, bốn mức biến động, U_georef chốt 12 m.'],
        ['2.6', 'Kiến trúc, cơ sở dữ liệu và giao diện WebGIS', 'done', 'MySQL (người dùng, cấu hình) + PostGIS (không gian), GeoServer WMS (tuỳ chọn), Leaflet + Chart.js; giao diện sáng thiết kế theo hệ Transect, hỗ trợ bàn phím và cảm ứng.'],
        ['3.5', 'Đối chiếu với sạt lở tham chiếu 2019–2020', 'part', 'Nguồn công khai chỉ có lý trình Km, chưa có toạ độ nên dùng vùng cảnh báo ~3,3 km; kết quả hiện không phát hiện đoạn lùi bờ trong vùng này (đã ghi rõ ở trang Phương pháp).'],
        ['3.6.2', 'REST API và bảo mật', 'done', 'Sanctum, khoá đăng nhập sau 5 lần sai theo email + IP, token hết hạn sau 480 phút, kiểm tra đầu vào, ghi nhật ký thay đổi ngưỡng.'],
        ['3.6.3', 'Giao diện người dùng', 'done', 'Bản đồ, biểu đồ transect, hotspot, so sánh hai năm, chia sẻ liên kết, tải GeoJSON/CSV, trang Phương pháp, Quản trị, Hướng dẫn và Báo cáo.'],
        ['3.7', 'Kiểm thử', 'done', 'Unit/Feature PHP, Vitest, pytest cho pipeline và Playwright E2E (xem số liệu bên dưới); CI trên GitHub Actions.'],
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
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<main id="main-content" tabindex="-1">
<header class="page-head">
    <div class="page-head-inner">
        <p class="eyebrow">Trang nội bộ, chỉ quản trị viên</p>
        <h1>Báo cáo công việc đã làm</h1>
        <p class="lead">Tổng hợp từ lịch sử git và các tính năng đã hoàn thành của đề tài “WebGIS giám sát biến động bờ sông Hậu, xã Bình Mỹ, An Giang”,
        đối chiếu với đề cương. Trang nội bộ, chỉ tài khoản admin xem được. Số liệu chốt ngày 23/11/2026.</p>
        <dl class="page-stamp" aria-label="Thông tin tài liệu">
            <div><dt>Phạm vi</dt><dd>Nội bộ · admin</dd></div>
            <div><dt>Số liệu</dt><dd>23/11/2026</dd></div>
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
    <a href="#tinh-nang">Tính năng đã làm</a>
    <a href="#kiem-thu">Kiểm thử</a>
    <a href="#con-lai">Việc còn lại</a>
</nav>
<article class="doc">

    <section id="so-lieu">
        <h2>Số liệu chung</h2>
        <table class="stats">
            <caption>Các con số chính của đề tài tính đến ngày 23/11/2026.</caption>
            <tbody>
                <tr><th scope="row">Commit trên nhánh chính</th><td class="v">372</td><td class="n">5 ngày có commit, từ 27/09 đến 23/11/2026</td></tr>
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
            <li><time datetime="2026-11-23">23/11/2026 — 35 commit</time>
                Truy cập: liên kết bỏ qua điều hướng, focus, vùng chạm 44 px, giảm chuyển động, tương phản; giao diện sáng thống nhất theo hệ Transect (bỏ chế độ tối);
                kiểm thử JavaScript; thêm trang Hướng dẫn sử dụng và Báo cáo công việc (trang này).</li>
        </ol>
    </section>

    <section id="tinh-nang">
        <h2>Tính năng đã làm</h2>
        <h3>Pipeline (Python)</h3>
        <ul>
            <li>Tải Sentinel-2 L2A, lọc mây bằng SCL, composite trung vị mùa khô tháng 1–3.</li>
            <li>Chỉ số nước NDWI/MNDWI/AWEI, ngưỡng Otsu, vector hoá đường bờ cắt theo ranh giới xã.</li>
            <li>Baseline, transect cách đều, NSM/EPR/LRR/R², độ bất định, phân loại Mục 2.5, gom hotspot lùi mạnh.</li>
            <li>Đánh giá Precision/Recall/F1/IoU và RMSE từ ground truth số hoá; đối chiếu chéo sản phẩm phủ bề mặt độc lập.</li>
        </ul>
        <h3>Ứng dụng web (Laravel)</h3>
        <ul>
            <li>REST API tra cứu đường bờ, transect, hotspot, sự kiện tham chiếu, thống kê; xuất GeoJSON/CSV.</li>
            <li>Bản đồ tương tác: lớp bản đồ (GeoJSON từ API, hoặc WMS khi có GeoServer), tô màu theo mức biến động, so sánh hai năm, lọc hotspot, chia sẻ liên kết.</li>
            <li>Trang Phương pháp, Quản trị ngưỡng (có nhật ký), Hướng dẫn sử dụng và Báo cáo công việc (chỉ admin, chặn phía máy chủ).</li>
            <li>Giao diện sáng theo hệ thiết kế Transect (lưới 1 px, Cobalt/Vermilion), hỗ trợ bàn phím, trình đọc màn hình, màn hình cảm ứng và bố cục điện thoại dạng bottom sheet.</li>
        </ul>
        <h3>Hạ tầng</h3>
        <ul>
            <li>GeoServer (tuỳ chọn) với style SLD và layer group nền bản đồ tự phục vụ; PostGIS cho dữ liệu không gian, MySQL cho người dùng và cấu hình.</li>
            <li>CI GitHub Actions; E2E chạy trên CSDL và workspace GeoServer riêng.</li>
        </ul>
    </section>

    <section id="kiem-thu">
        <h2>Kiểm thử</h2>
        <div class="table-wrap"><table>
            <caption>Các bộ kiểm thử của dự án.</caption>
            <tr><th>Loại</th><th>Công cụ</th><th>Quy mô</th></tr>
            <tr><td>Unit + Feature (PHP)</td><td>PHPUnit</td><td>hơn 530 phương thức kiểm thử</td></tr>
            <tr><td>JavaScript giao diện</td><td>Vitest</td><td>120 ca</td></tr>
            <tr><td>Pipeline</td><td>pytest</td><td>73 ca</td></tr>
            <tr><td>Đầu–cuối</td><td>Playwright (Chromium, Firefox, WebKit)</td><td>134 kịch bản</td></tr>
        </table></div>
        <p class="hint">Số liệu đếm theo mã nguồn ngày 23/11/2026, chưa tính ca sinh từ data provider. WebKit không chạy được trên Ubuntu mới nên có script <code>test:no-webkit</code>.</p>
    </section>

    <section id="con-lai">
        <h2>Việc còn lại và rủi ro</h2>
        <ul>
            <li>Toạ độ chính xác của điểm sạt lở tham chiếu (Km 89+272 / 89+076) chưa có; các số quyết định lấy từ báo chí, chưa đối chiếu bản gốc.</li>
            <li>Pipeline chưa phát hiện đoạn lùi bờ quanh vùng sạt lở 2019–2020; cần trình bày như hạn chế của phương pháp, không như bằng chứng khớp thực tế.</li>
            <li>Ground truth mới có 5 ô mẫu; nếu bổ sung thêm ô và năm thì cần chạy lại đánh giá và cập nhật U_classify.</li>
            <li>Rà lại số liệu trong báo cáo docx sau mỗi lần chạy lại pipeline.</li>
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
</body>
</html>
