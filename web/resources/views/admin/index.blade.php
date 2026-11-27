<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.head', ['title' => 'Quản trị ngưỡng — WebGIS Sông Hậu', 'description' => 'Quản trị ngưỡng hiển thị hotspot sạt lở.'])
    @vite(['resources/css/app.css', 'resources/js/admin.js', 'resources/js/admin-map.js'])
    <script>window.WEBGIS = @json(['geoserver' => $geoserver, 'years' => $years, 'vietmapKey' => $vietmapKey]);</script>
</head>
<body class="page">
@include('partials.nav')
<main class="admin admin-split" id="main-content" tabindex="-1">
    <aside class="admin-side" aria-label="Quản trị">
        <h1>Quản trị</h1>
        <p class="lead">Web chỉ đọc: kết quả do pipeline tính sẵn. Tài khoản admin chỉ chỉnh ngưỡng hiển thị hotspot.</p>
        <p id="admin-message" role="status"></p>

        <section id="admin-panel">
            <div class="admin-user"><span>Đăng nhập: <strong id="admin-user">{{ auth()->user()->email }} ({{ auth()->user()->role }})</strong></span></div>
            <h2 class="section-title">Ngưỡng hiển thị</h2>
            <div id="thresholds"></div>
            <h2 class="section-title">Quản lý lớp bản đồ</h2>
            <p class="hint">Đặt trạng thái mặc định của lớp trên bản đồ công khai: bật/tắt, độ trong suốt, thứ tự chồng lớp (trên cùng nằm trên).</p>
            <ol id="layer-admin" class="layer-admin"></ol>
        </section>

        <h2 class="section-title">Dữ liệu trong hệ thống</h2>
        <dl class="status-grid" id="admin-status"><dt>Trạng thái</dt><dd>Đang cập nhật</dd></dl>
        <p class="hint">Chọn một dòng trong bảng để phóng tới transect trên bản đồ; bấm transect trên bản đồ để chọn dòng tương ứng.</p>
    </aside>
    <div class="admin-main">
        <div id="admin-map" role="application" aria-label="Bản đồ transect"></div>
        @include('partials.attr-table')
    </div>
</main>
</body>
</html>
