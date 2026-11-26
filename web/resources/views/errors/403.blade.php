<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Không có quyền truy cập — WebGIS Sông Hậu</title>
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<main class="sheet-page" id="main-content" tabindex="-1">
    <div class="sheet">
        <div class="sheet-margin">
            <p class="state-code" aria-hidden="true">403</p>
            <p>Tờ này chỉ dành cho người giữ khoá.</p>
        </div>
        <div class="sheet-body">
            <h1>Không có quyền truy cập</h1>
            <p class="lead">Trang này chỉ dành cho tài khoản quản trị (admin). Tài khoản hiện tại không có quyền xem.</p>
            <ul class="index-list">
                <li><a href="/"><span class="idx-name">Về bản đồ</span><span class="idx-desc">xem công khai, không cần đăng nhập</span><span class="idx-lead" aria-hidden="true"></span><span class="idx-path">/</span></a></li>
                <li><a href="/huong-dan"><span class="idx-name">Hướng dẫn</span><span class="idx-desc">cách đọc và dùng bản đồ</span><span class="idx-lead" aria-hidden="true"></span><span class="idx-path">/huong-dan</span></a></li>
            </ul>
        </div>
    </div>
</main>
</body>
</html>
