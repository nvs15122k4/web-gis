<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Không tìm thấy trang — WebGIS Sông Hậu</title>
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<main class="admin state-page" id="main-content" tabindex="-1">
    <div class="admin-card">
        <p class="state-code" aria-hidden="true">404</p>
        <h1>Không tìm thấy trang</h1>
        <p class="lead">Địa chỉ này không tồn tại hoặc đã được chuyển đi. Hãy quay về bản đồ hoặc xem hướng dẫn sử dụng.</p>
        <div class="btn-row">
            <a class="btn btn-primary" href="/">Về bản đồ</a>
            <a class="btn" href="/huong-dan">Hướng dẫn</a>
        </div>
    </div>
</main>
</body>
</html>
