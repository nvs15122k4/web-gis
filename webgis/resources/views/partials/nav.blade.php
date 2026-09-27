<nav class="topnav" aria-label="Điều hướng chính">
    <a href="/" @class(['active' => request()->is('/')])>Bản đồ</a>
    <a href="/gioi-thieu" @class(['active' => request()->is('gioi-thieu')])>Giới thiệu &amp; phương pháp</a>
    <a href="/admin" @class(['active' => request()->is('admin')])>Quản trị</a>
</nav>
