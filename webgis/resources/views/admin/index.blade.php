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
    <h1>Quản trị ngưỡng hiển thị</h1>
    <p id="admin-message" role="status"></p>

    <form id="login-form">
        <label>Email <input type="email" name="email" required></label>
        <label>Mật khẩu <input type="password" name="password" required></label>
        <button type="submit">Đăng nhập</button>
    </form>

    <section id="admin-panel" hidden>
        <p>Đăng nhập: <strong id="admin-user"></strong> <button type="button" id="logout">Đăng xuất</button></p>
        <div id="thresholds"></div>
    </section>
</main>
</body>
</html>
