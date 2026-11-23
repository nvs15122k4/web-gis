<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập — WebGIS Sông Hậu</title>
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<main class="admin" id="main-content" tabindex="-1">
    <div class="admin-card">
        <h1>Đăng nhập</h1>
        <p class="lead">Dành cho tài khoản quản trị để xem các trang nội bộ như báo cáo công việc.</p>

        <form method="post" action="/dang-nhap" id="session-login-form">
            @csrf
            <label>Email <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required></label>
            <label>Mật khẩu <input type="password" name="password" autocomplete="current-password" required></label>
            @if ($errors->any())
                <p class="field-error" role="alert">{{ $errors->first() }}</p>
            @endif
            <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
        </form>
    </div>
</main>
</body>
</html>
