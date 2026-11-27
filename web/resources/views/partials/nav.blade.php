<a class="skip-link" href="#main-content">Bỏ qua điều hướng, đến nội dung chính</a>
<header class="appbar">
    @isset($withSidebarToggle)
        <button id="sidebar-toggle" class="icon-btn" type="button" aria-label="Mở/đóng bảng điều khiển" aria-controls="sidebar" aria-expanded="true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="1"/><path d="M9 4v16"/></svg>
        </button>
    @endisset
    <a class="brand" href="/">
        <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M3 17c3-2 5-2 8 0s5 2 10-1"/><path d="M3 11c3-2 5-2 8 0s5 2 10-1" opacity=".55"/></svg>
        </span>
        <span class="brand-text">
            <strong><span class="brand-prefix">WebGIS </span>Sông Hậu</strong>
            <small>Giám sát biến động bờ sông · xã Bình Mỹ, An Giang</small>
        </span>
    </a>
    <nav class="topnav" aria-label="Điều hướng chính">
        <a href="/" @class(['active' => request()->is('/')]) @if (request()->is('/')) aria-current="page" @endif>Bản đồ</a>
        <a href="/gioi-thieu" @class(['active' => request()->is('gioi-thieu')]) @if (request()->is('gioi-thieu')) aria-current="page" @endif>Phương pháp</a>
        <a href="/huong-dan" @class(['active' => request()->is('huong-dan')]) @if (request()->is('huong-dan')) aria-current="page" @endif>Hướng dẫn</a>
        @if (auth()->user()?->isAdmin())
            <a href="/bao-cao" @class(['active' => request()->is('bao-cao')]) @if (request()->is('bao-cao')) aria-current="page" @endif>Báo cáo</a>
            <a href="/admin" @class(['active' => request()->is('admin')]) @if (request()->is('admin')) aria-current="page" @endif>Quản trị</a>
        @endif
        @auth
            <form method="post" action="/dang-xuat" class="nav-logout">
                @csrf
                <button type="submit" title="Đang đăng nhập: {{ auth()->user()->email }}">Đăng xuất</button>
            </form>
        @else
            <a href="/dang-nhap" @class(['active' => request()->is('dang-nhap')]) @if (request()->is('dang-nhap')) aria-current="page" @endif>Đăng nhập</a>
        @endauth
    </nav>
</header>
