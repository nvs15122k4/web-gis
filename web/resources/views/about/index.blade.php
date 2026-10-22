<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giới thiệu &amp; phương pháp — WebGIS Sông Hậu</title>
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<div class="doc-layout">
<nav class="doc-toc" aria-label="Mục lục">
    <p>Mục lục</p>
    <a href="#muc-tieu">Mục tiêu</a>
    <a href="#du-lieu">Dữ liệu</a>
    <a href="#phuong-phap">Phương pháp</a>
    <a href="#phan-loai">Phân loại</a>
    <a href="#bat-dinh">Độ bất định</a>
    <a href="#sat-lo">Sạt lở tham chiếu</a>
    <a href="#han-che">Hạn chế</a>
    <a href="#tai-du-lieu">Tải dữ liệu</a>
    <a href="#tham-khao">Tham khảo</a>
</nav>
<main class="doc" id="about">
    <h1>Giới thiệu &amp; phương pháp</h1>
    <p class="lead">WebGIS giám sát biến động bờ sông Hậu tại xã Bình Mỹ, tỉnh An Giang, giai đoạn
        {{ $years[0] }}–{{ end($years) }}. Kết quả được tính sẵn bằng pipeline Python từ ảnh vệ tinh; web chỉ đọc và hiển thị,
        không tính lại các chỉ số.</p>


    <section id="muc-tieu">
        <h2>Mục tiêu</h2>
        <p>Xác định các đoạn bờ có xu hướng lùi bờ mạnh kèm độ bất định, và đối chiếu với các sự kiện sạt lở đã công bố tại
            Bình Mỹ giai đoạn 2019–2020 (ảnh hưởng tuyến Quốc lộ 91). Hệ thống hỗ trợ khoanh vùng ưu tiên khảo sát thực địa,
            không thay thế khảo sát thực địa.</p>
    </section>

    <section id="du-lieu">
        <h2>Dữ liệu</h2>
        <div class="table-wrap"><table>
            <tr><th>Lớp</th><th>Nội dung</th><th>Trạng thái</th></tr>
            <tr><td>Ảnh vệ tinh</td><td>Composite mùa khô (tháng 1–3) mỗi năm, lọc mây theo lớp SCL</td><td class="updating">Đang cập nhật</td></tr>
            <tr><td>Đường bờ theo năm</td><td>Ranh giới nước–đất trích từ chỉ số nước NDWI / MNDWI / AWEI (ngưỡng Otsu)</td><td data-layer="shorelines"></td></tr>
            <tr><td>Transect</td><td>Mặt cắt vuông góc với đường cơ sở (baseline), cách đều nhau dọc bờ</td><td data-layer="transects"></td></tr>
            <tr><td>Hotspot lùi bờ</td><td>Đoạn bờ gộp từ các transect lùi bờ mạnh liên tiếp</td><td data-layer="hotspots"></td></tr>
            <tr><td>Sạt lở tham chiếu</td><td>Sự kiện 2019–2020 từ nguồn công bố chính thức, chỉ dùng để đối chiếu</td><td data-layer="reference_events"></td></tr>
            <tr><td>Ranh giới xã Bình Mỹ</td><td>Hiện là khung tạm</td><td class="updating">Đang cập nhật</td></tr>
        </table></div>
    </section>

    <section id="phuong-phap">
        <h2>Phương pháp phân tích biến động (kiểu DSAS)</h2>
        <ol>
            <li>Dựng một <strong>baseline</strong> song song xu hướng bờ sông và sinh các <strong>transect</strong> vuông góc, cách đều.</li>
            <li>Tại mỗi transect, đo <strong>khoảng cách baseline → đường bờ</strong> cho từng năm. Năm không có composite hợp lệ
                được bỏ trống, không nội suy.</li>
            <li>Tính các chỉ số:
                <dl class="defs">
                    <dt>NSM</dt><dd>Net Shoreline Movement — khoảng dịch chuyển giữa đường bờ năm đầu và năm cuối (m).</dd>
                    <dt>EPR</dt><dd>End Point Rate — NSM chia cho số năm giữa hai thời điểm (m/năm).</dd>
                    <dt>LRR</dt><dd>Linear Regression Rate — hệ số góc hồi quy tuyến tính trên toàn bộ chuỗi năm (m/năm).</dd>
                    <dt>R²</dt><dd>Mức độ phù hợp tuyến tính của chuỗi vị trí đường bờ.</dd>
                </dl>
            </li>
        </ol>
        <p>Quy ước dấu: giá trị <strong>dương = bồi</strong> (bờ tiến ra sông), <strong>âm = lùi bờ</strong>.</p>
    </section>

    <section id="phan-loai">
        <h2>Tiêu chí phân loại mức biến động (Mục 2.5 đề cương)</h2>
        <p>Transect có xu hướng biến động thực sự khi <strong>|LRR| &gt; 1,96 × SE(LRR)</strong>, tức khoảng tin cậy 95% không chứa 0.</p>
        <div class="table-wrap"><table>
            <tr><th>Mức</th><th>Điều kiện</th></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-accretion)"></span> Bồi</td><td>Có ý nghĩa thống kê và LRR &gt; 0</td></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-stable)"></span> Ổn định</td><td>|LRR| ≤ 1,96 × SE(LRR), chưa đủ cơ sở kết luận biến động</td></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-mild-erosion)"></span> Lùi nhẹ</td><td>Lùi bờ có ý nghĩa thống kê nhưng không đủ cả hai điều kiện "lùi mạnh"</td></tr>
            <tr><td><span class="legend-swatch inline" style="background:var(--c-severe-erosion)"></span> Lùi mạnh</td><td>LRR ≤ M − S (M, S là trung bình và độ lệch chuẩn LRR của các transect lùi bờ có ý nghĩa)
                <em>và</em> khoảng cách giảm liên tục ở ít nhất 3 mốc năm liên tiếp</td></tr>
        </table></div>
        <p>R² không phải ngưỡng loại trừ. Transect có <strong>R² &lt; 0,3</strong> vẫn giữ nếu có ý nghĩa thống kê, nhưng được ghi chú
            "xu hướng tuyến tính chưa rõ ràng, cần diễn giải thận trọng".</p>
    </section>

    <section id="bat-dinh">
        <h2>Độ bất định</h2>
        <p class="formula">U<sub>shoreline</sub> = √(U<sub>pixel</sub>² + U<sub>georef</sub>² + U<sub>classify</sub>²), với U<sub>pixel</sub> = 5 m
            (nửa pixel 10 m), U<sub>georef</sub> theo thông số ESA, U<sub>classify</sub> = RMSE so với đường bờ số hóa thủ công.
            Độ bất định LRR = 1,96 × SE(LRR); độ bất định EPR = √(U(t1)² + U(t2)²) / |t2 − t1|.</p>
    </section>

    <section id="sat-lo">
        <h2>Sạt lở tham chiếu</h2>
        <p>Mức độ sạt lở hiển thị theo cách phân loại của Quyết định 01/2011/QĐ-TTg (Quy chế xử lý sạt lở bờ sông, bờ biển), cũng là
            cách bản đồ sạt lở ĐBSCL của Tổng cục Phòng chống thiên tai sử dụng:</p>
        <ul class="levels">
            <li><span class="dot" style="background:#b71c1c"></span> <strong>Đặc biệt nguy hiểm</strong> — đe dọa trực tiếp, trong thời gian ngắn, tới đê điều, khu dân cư tập trung, công trình hạ tầng quan trọng (quốc lộ…)</li>
            <li><span class="dot" style="background:#ef6c00"></span> <strong>Nguy hiểm</strong> và
                <span class="dot" style="background:#7b3fe4"></span> <strong>Bình thường</strong> — theo tiêu chí tại Điều 4 của Quy chế</li>
            <li><span class="dot" style="background:#9e9e9e"></span> <strong>Chưa phân loại</strong> — đang cập nhật</li>
        </ul>
        <p>Dữ liệu này chỉ dùng để đối chiếu, không dùng để huấn luyện hay hiệu chỉnh pipeline.</p>
    </section>

    <section id="han-che">
        <h2>Hạn chế &amp; lưu ý khi sử dụng</h2>
        <ul>
            <li>Kết quả cho biết <strong>có biến động</strong>, không cho biết <strong>nguyên nhân</strong> (dòng chảy, khai thác cát, san lấp, kè…).</li>
            <li>Mỗi năm chỉ có một đường bờ mùa khô, nên không phản ánh biến động theo mùa, triều hay lũ, hoặc sạt lở đột ngột giữa hai năm.</li>
            <li>Độ phân giải 10 m: biến động nhỏ hơn độ bất định không được kết luận.</li>
            <li>Khu vực có bãi bồi, bùn, thực vật ven bờ, công trình hay tàu thuyền neo đậu có thể làm sai lệch đường bờ.</li>
            <li class="disclaimer">Thông tin mang tính tham khảo phục vụ nghiên cứu. Không dùng thay cho cảnh báo chính thức của cơ quan chức năng.</li>
        </ul>
    </section>

    <section id="tai-du-lieu">
        <h2>Tải dữ liệu</h2>
        @include('partials.downloads')
    </section>

    <section id="tham-khao">
        <h2>Tham khảo &amp; hệ thống tương tự</h2>
        <ul>
            <li><a href="https://knowledge.dea.ga.gov.au/data/product/dea-coastlines/" rel="noopener">Digital Earth Australia Coastlines</a> — đường bờ hằng năm, điểm tốc độ biến động có kiểm định ý nghĩa, biểu đồ theo điểm.</li>
            <li><a href="https://marine.usgs.gov/coastalchangehazardsportal/" rel="noopener">USGS Coastal Change Hazards Portal</a> — tốc độ biến động theo transect (DSAS), tải dữ liệu, chia sẻ liên kết.</li>
            <li><a href="https://shorelinemonitor.deltares.nl/" rel="noopener">Deltares ShorelineMonitor</a> — biến động bờ toàn cầu, màu đỏ (xói) / xanh (bồi).</li>
            <li>Bản đồ trực tuyến sạt lở bờ sông, bờ biển ĐBSCL — Tổng cục Phòng chống thiên tai (2018).</li>
            <li>Himmelstoss và cộng sự (2018), <em>Digital Shoreline Analysis System (DSAS) v5.0 User Guide</em>, USGS.</li>
        </ul>
    </section>
</main>
</div>
<script type="module">
    // Điền trạng thái từng lớp từ /api/status.
    const cells = document.querySelectorAll('[data-layer]');
    fetch('/api/status', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .catch(() => ({ data: {} }))
        .then((s) => cells.forEach((td) => {
            const n = s.data?.[td.dataset.layer];
            td.textContent = n ? `${n} bản ghi` : 'Đang cập nhật';
            td.className = n ? '' : 'updating';
        }));
</script>
</body>
</html>
