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
        <a href="/" @class(['active' => request()->is('/')]) @if (request()->is('/')) aria-current="page" @endif>Bản đồ</a>
        <a href="/gioi-thieu" @class(['active' => request()->is('gioi-thieu')]) @if (request()->is('gioi-thieu')) aria-current="page" @endif>Phương pháp</a>
        <a href="/admin" @class(['active' => request()->is('admin')]) @if (request()->is('admin')) aria-current="page" @endif>Quản trị</a>
    </nav>
</header>
