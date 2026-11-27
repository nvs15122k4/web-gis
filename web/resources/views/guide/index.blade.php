<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.head', ['title' => 'Hướng dẫn sử dụng — WebGIS Sông Hậu', 'description' => 'Cách xem bản đồ, chọn năm, đọc tốc độ biến động và hotspot sạt lở trên WebGIS Sông Hậu.'])
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<main id="main-content" tabindex="-1">
<header class="page-head">
    <div class="page-head-inner">
        <p class="eyebrow">Cách đọc bản đồ</p>
        <h1>Hướng dẫn sử dụng</h1>
        <p class="lead">Cách xem và đọc kết quả giám sát biến động bờ sông Hậu tại xã Bình Mỹ, An Giang. Bạn không cần đăng nhập
        để dùng các chức năng dưới đây.</p>
        <dl class="page-stamp" aria-label="Thông tin trang">
            <div><dt>Người dùng</dt><dd>Mọi người</dd></div>
            <div><dt>Đăng nhập</dt><dd>Không cần</dd></div>
            <div><dt>Thiết bị</dt><dd>Máy tính, điện thoại</dd></div>
        </dl>
    </div>
</header>
<div class="doc-layout">
<nav class="doc-toc" aria-label="Mục lục">
    <p class="doc-toc-title">Mục lục</p>
    <a href="#bat-dau">Bắt đầu</a>
    <a href="#tong-quan">Tổng quan</a>
    <a href="#lop-du-lieu">Lớp dữ liệu</a>
    <a href="#transect">Xem một transect</a>
    <a href="#hotspot">Hotspot</a>
    <a href="#so-sanh">So sánh hai năm</a>
    <a href="#chia-se">Chia sẻ &amp; tải dữ liệu</a>
    <a href="#doc-ket-qua">Đọc kết quả</a>
    <a href="#ban-phim">Bàn phím &amp; hỗ trợ</a>
    <a href="#hoi-dap">Hỏi đáp</a>
</nav>
<article class="doc">

    <section id="bat-dau">
        <h2>Bắt đầu</h2>
        <ol class="steps" role="list">
            <li>Mở trang <a href="/">Bản đồ</a>. Bản đồ tự căn khung vào đoạn sông Hậu thuộc xã Bình Mỹ; bạn vẫn kéo và thu phóng tự do.</li>
            <li>Bảng điều khiển bên trái có bốn thẻ: <strong>Tổng quan</strong>, <strong>Lớp dữ liệu</strong>, <strong>Hotspot</strong>
                và <strong>Chia sẻ</strong>. Trên điện thoại, bấm nút ba gạch ở góc trái thanh tiêu đề để mở hoặc đóng bảng này.</li>
            <li>Chú giải màu nằm ở góc bản đồ; bấm vào tiêu đề chú giải để thu gọn hoặc mở rộng.</li>
            <li>Trang <a href="/gioi-thieu">Phương pháp</a> giải thích dữ liệu, cách tính và các hạn chế.</li>
        </ol>
    </section>

    <section id="tong-quan">
        <h2>Thẻ Tổng quan</h2>
        <p>Tóm tắt số lượng transect theo từng mức biến động (bồi, ổn định, lùi nhẹ, lùi mạnh) và các chỉ số chung của toàn đoạn bờ.
            Khi dữ liệu chưa sẵn sàng, mục tương ứng ghi <em>Đang cập nhật</em> thay vì hiện số giả.</p>
    </section>

    <section id="lop-du-lieu">
        <h2>Thẻ Lớp dữ liệu</h2>
        <ul>
            <li><strong>Transect</strong> — các mặt cắt vuông góc với bờ, tô màu theo mức biến động. Bật <em>Chỉ transect có ý nghĩa thống kê (95%)</em> để ẩn những mặt cắt chưa đủ cơ sở kết luận.</li>
            <li><strong>Hotspot lùi bờ mạnh</strong> — đoạn bờ gộp từ các transect lùi mạnh liên tiếp.</li>
            <li><strong>Sạt lở tham chiếu 2019–2020</strong> — vùng cảnh báo từ nguồn công bố, chỉ để đối chiếu; mặc định tắt.</li>
            <li><strong>Đường bờ theo năm</strong> — bật nhiều năm để thấy bờ dịch chuyển. Năm bị làm mờ là năm chưa có ảnh hợp lệ.</li>
        </ul>
    </section>

    <section id="transect">
        <h2>Xem chi tiết một transect</h2>
        <ol class="steps" role="list">
            <li>Phóng to bản đồ rồi bấm vào một transect.</li>
            <li>Thẻ chi tiết hiện các chỉ số <strong>NSM</strong>, <strong>EPR</strong>, <strong>LRR</strong>, <strong>R²</strong>, độ bất định,
                mức biến động và biểu đồ khoảng cách đường bờ theo năm.</li>
            <li>Năm không có ảnh hợp lệ được bỏ trống trên biểu đồ, không nội suy.</li>
            <li>Đóng thẻ bằng nút đóng hoặc phím <kbd>Esc</kbd>.</li>
        </ol>
        <p>Quy ước dấu: giá trị <strong>dương = bồi</strong>, <strong>âm = lùi bờ</strong>.</p>
    </section>

    <section id="hotspot">
        <h2>Thẻ Hotspot</h2>
        <p>Nhập tốc độ lùi tối thiểu (m/năm) rồi bấm <strong>Lọc</strong> để chỉ giữ các đoạn bờ lùi nhanh hơn ngưỡng đó. Bấm một đoạn
            trong danh sách để bản đồ phóng tới đoạn ấy. Ngưỡng mặc định do quản trị viên đặt.</p>
    </section>

    <section id="so-sanh">
        <h2>So sánh hai năm</h2>
        <p>Trong thẻ Lớp dữ liệu, chọn <strong>Năm A</strong> và <strong>Năm B</strong> rồi bấm <strong>So sánh</strong>; hai đường bờ được vẽ
            bằng hai màu khác nhau. Bấm <strong>Bỏ so sánh</strong> để quay lại.</p>
    </section>

    <section id="chia-se">
        <h2>Chia sẻ và tải dữ liệu</h2>
        <ul>
            <li><strong>Sao chép liên kết</strong> (thẻ Chia sẻ) giữ nguyên vị trí, mức phóng và transect đang chọn; gửi liên kết cho người khác là họ thấy đúng khung nhìn của bạn.</li>
            <li>Mục <strong>Tải dữ liệu</strong> cho phép tải bốn lớp (transect, đường bờ, hotspot, sạt lở tham chiếu) dạng GeoJSON hoặc CSV, hệ toạ độ EPSG:4326.</li>
        </ul>
    </section>

    <section id="doc-ket-qua">
        <h2>Đọc kết quả cho đúng</h2>
        <div class="table-wrap"><table>
            <tr><th>Màu / mức</th><th>Ý nghĩa</th></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-accretion)"></span> Bồi</td><td>Bờ tiến ra sông, có ý nghĩa thống kê.</td></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-stable)"></span> Ổn định</td><td>Chưa đủ cơ sở kết luận có biến động; không đồng nghĩa với an toàn.</td></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-mild-erosion)"></span> Lùi nhẹ</td><td>Lùi bờ có ý nghĩa thống kê nhưng chưa thuộc nhóm lùi mạnh.</td></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-severe-erosion)"></span> Lùi mạnh</td><td>Lùi bờ nhanh và liên tục qua nhiều mốc năm.</td></tr>
        </table></div>
        <p>Kết quả mang tính tham khảo phục vụ nghiên cứu, dựa trên ảnh 10 m mỗi năm một đường bờ; biến động nhỏ hơn độ bất định không được
            kết luận. Hệ thống không dự báo thời điểm hay vị trí sạt lở và không thay thế cảnh báo chính thức.</p>
    </section>

    <section id="ban-phim">
        <h2>Bàn phím và hỗ trợ tiếp cận</h2>
        <ul>
            <li>Bấm <kbd>Tab</kbd> ngay khi mở trang để hiện liên kết <em>Bỏ qua điều hướng, đến nội dung chính</em>.</li>
            <li>Các thẻ trong bảng điều khiển chuyển bằng phím mũi tên trái/phải.</li>
            <li>Giao diện dùng một bảng màu sáng cố định, nền bản đồ sáng để màu phân loại dễ đọc; trên điện thoại, bảng điều khiển và thẻ chi tiết transect hiện dạng bottom sheet.</li>
        </ul>
    </section>

    <section id="hoi-dap">
        <h2>Hỏi đáp</h2>
        <dl class="defs">
            <dt>Vì sao một số năm bị mờ?</dt><dd>Năm đó không có ảnh vệ tinh hợp lệ (mây, thiếu cảnh), nên không có đường bờ.</dd>
            <dt>Vì sao hiện “Đang cập nhật”?</dt><dd>Dữ liệu lớp đó chưa được nạp hoặc dịch vụ chưa sẵn sàng; hãy thử lại sau.</dd>
            <dt>Vì sao vùng sạt lở 2019–2020 không có hotspot?</dt><dd>Xem mục “Sạt lở tham chiếu” ở trang <a href="/gioi-thieu#sat-lo">Phương pháp</a>: ảnh 10 m mỗi năm một lần không bắt được sạt lở đột ngột.</dd>
            <dt>Tôi là quản trị viên, chỉnh ngưỡng ở đâu?</dt><dd>Trang <a href="/admin">Quản trị</a> (đăng nhập bằng tài khoản admin).</dd>
        </dl>
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
