@php
    $ico = [
        'map' => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2ZM9 4v14M15 6v14',
        'method' => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5ZM6 19h13',
        'guide' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM9.5 9.2a2.6 2.6 0 1 1 3.6 2.4c-.7.4-1.1.9-1.1 1.7M12 17h.01',
        'report' => 'M7 3h8l4 4v14H7V3ZM15 3v4h4M10 12h6M10 16h6',
        'admin' => 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z',
        'login' => 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3',
    ];
    $icon = fn (string $k) => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="'.$ico[$k].'"/></svg>';
@endphp
<a class="skip-link" href="#main-content">Bỏ qua điều hướng, đến nội dung chính</a>
<header class="appbar">
    @isset($withSidebarToggle)
        <button id="sidebar-toggle" class="icon-btn" type="button" aria-label="Mở/đóng bảng điều khiển" aria-controls="sidebar" aria-expanded="true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
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
        <a href="/" @class(['active' => request()->is('/')]) @if (request()->is('/')) aria-current="page" @endif>{!! $icon('map') !!}Bản đồ</a>
        <a href="/gioi-thieu" @class(['active' => request()->is('gioi-thieu')]) @if (request()->is('gioi-thieu')) aria-current="page" @endif>{!! $icon('method') !!}Phương pháp</a>
        <a href="/huong-dan" @class(['active' => request()->is('huong-dan')]) @if (request()->is('huong-dan')) aria-current="page" @endif>{!! $icon('guide') !!}Hướng dẫn</a>
        @if (auth()->user()?->isAdmin())
            <a href="/bao-cao" @class(['active' => request()->is('bao-cao')]) @if (request()->is('bao-cao')) aria-current="page" @endif>{!! $icon('report') !!}Báo cáo</a>
        @endif
        <a href="/admin" @class(['active' => request()->is('admin')]) @if (request()->is('admin')) aria-current="page" @endif>{!! $icon('admin') !!}Quản trị</a>
        @auth
            <form method="post" action="/dang-xuat" class="nav-logout">
                @csrf
                <button type="submit" title="Đang đăng nhập: {{ auth()->user()->email }}">Đăng xuất</button>
            </form>
        @else
            <a href="/dang-nhap" @class(['active' => request()->is('dang-nhap')])>{!! $icon('login') !!}Đăng nhập</a>
        @endauth
    </nav>
</header>
