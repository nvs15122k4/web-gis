<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản trị ngưỡng — WebGIS Sông Hậu</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="page">
@include('partials.nav')
<main class="admin">
    <div class="admin-card">
        <h1>Quản trị ngưỡng hiển thị</h1>
        <p class="lead">Ngưỡng dùng để lọc hotspot trên bản đồ. Chỉ tài khoản admin được sửa.</p>
        <p id="admin-message" role="status"></p>

        <form id="login-form">
            <label>Email <input type="email" name="email" autocomplete="username" required></label>
            <label>Mật khẩu <input type="password" name="password" autocomplete="current-password" required></label>
            <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
        </form>

        <section id="admin-panel" hidden>
            <div class="admin-user"><span>Đăng nhập: <strong id="admin-user"></strong></span> <button type="button" class="btn btn-ghost" id="logout">Đăng xuất</button></div>
            <div id="thresholds"></div>
        </section>
    </div>
</main>
</body>
</html>
