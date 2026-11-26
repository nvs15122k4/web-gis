<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản trị ngưỡng — WebGIS Sông Hậu</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js', 'resources/js/admin-map.js'])
</head>
<body class="page">
@include('partials.nav')
<main class="admin admin-split" id="main-content" tabindex="-1">
    <aside class="admin-side" aria-label="Quản trị">
        <h1>Quản trị</h1>
        <p class="lead">Web chỉ đọc: kết quả do pipeline tính sẵn. Tài khoản admin chỉ chỉnh ngưỡng hiển thị hotspot.</p>
        <p id="admin-message" role="status"></p>

        <form id="login-form">
            <label>Email <input type="email" name="email" autocomplete="username" required></label>
            <label>Mật khẩu <input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
        </form>

        <section id="admin-panel" hidden>
            <div class="admin-user"><span>Đăng nhập: <strong id="admin-user"></strong></span> <button type="button" class="btn btn-ghost" id="logout">Đăng xuất</button></div>
            <h2 class="section-title">Ngưỡng hiển thị</h2>
            <div id="thresholds"></div>
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
