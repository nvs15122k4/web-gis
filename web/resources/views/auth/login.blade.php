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
<div class="auth-split">
    <aside class="auth-aside" aria-hidden="true">
        <svg class="auth-waves" viewBox="0 0 400 200" preserveAspectRatio="none"><path d="M0 120c60-30 100-30 160 0s100 30 240-10v90H0z" opacity=".25"/><path d="M0 150c60-30 100-30 160 0s100 30 240-10v60H0z" opacity=".35"/></svg>
        <p class="eyebrow">WebGIS Sông Hậu</p>
        <p class="auth-claim">Theo dõi từng đoạn bờ, từng năm.</p>
        <p class="auth-sub">Khu vực quản trị dành cho người phụ trách số liệu và báo cáo công việc.</p>
    </aside>
    <main class="admin" id="main-content" tabindex="-1">
        <div class="admin-card">
            <h1>Đăng nhập</h1>
            <p class="lead">Dành cho tài khoản quản trị để xem các trang nội bộ như báo cáo công việc.</p>

            <form method="post" action="/dang-nhap" id="session-login-form">
                @csrf
                <label>Email <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required @if ($errors->any()) aria-describedby="login-error" aria-invalid="true" @endif></label>
                <label>Mật khẩu <input type="password" name="password" autocomplete="current-password" required @if ($errors->any()) aria-describedby="login-error" aria-invalid="true" @endif></label>
                @if ($errors->any())
                    <p class="field-error" id="login-error" role="alert">{{ $errors->first() }}</p>
                @endif
                <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
