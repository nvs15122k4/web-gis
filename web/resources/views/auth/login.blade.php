<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.head', ['title' => 'Đăng nhập — WebGIS Sông Hậu', 'description' => 'Đăng nhập khu vực quản trị WebGIS giám sát biến động bờ sông Hậu.'])
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<div class="auth-split">
    <aside class="auth-aside" aria-hidden="true">
        <p class="eyebrow">WebGIS Sông Hậu</p>
        <p class="auth-claim">Theo dõi từng đoạn bờ, từng năm.</p>
        <p class="auth-sub">Khu vực quản trị dành cho người phụ trách số liệu và báo cáo công việc.</p>
        <dl class="auth-facts">
            <div><dt>Sông</dt><dd>Hậu</dd></div>
            <div><dt>Ảnh</dt><dd>S-2</dd></div>
            <div><dt>Giai đoạn</dt><dd>2018–2026</dd></div>
        </dl>
    </aside>
    <main class="sheet-page" id="main-content" tabindex="-1">
        <div class="sheet">
            <div class="sheet-body">
                <h1>Đăng nhập</h1>
                <p class="lead">Dành cho tài khoản quản trị để xem các trang nội bộ như báo cáo công việc.</p>

                <form method="post" action="/dang-nhap" id="session-login-form" class="sheet-form">
                    @csrf
                    <label>Email <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required @if ($errors->any()) aria-describedby="login-error" aria-invalid="true" @endif></label>
                    <label>Mật khẩu <input type="password" name="password" autocomplete="current-password" required @if ($errors->any()) aria-describedby="login-error" aria-invalid="true" @endif></label>
                    @if ($errors->any())
                        <p class="field-error" id="login-error" role="alert">{{ $errors->first() }}</p>
                    @endif
                    <button type="submit" class="btn btn-primary btn-block">@include('partials.icon', ['name' => 'login']) Đăng nhập</button>
                </form>

                <ol class="sheet-notes" aria-label="Ghi chú">
                    <li>Bản đồ, phương pháp và hướng dẫn xem được mà không cần đăng nhập.</li>
                    <li>Sau 5 lần nhập sai liên tiếp, địa chỉ mạng và email đó bị khoá tạm thời.</li>
                    <li>Tài khoản quản trị chỉ chỉnh được ngưỡng hiển thị hotspot; kết quả phân tích do pipeline tính sẵn.</li>
                </ol>
            </div>
        </div>
    </main>
</div>
</body>
</html>
