<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.head', ['title' => 'Không tìm thấy trang — WebGIS Sông Hậu', 'description' => 'Trang bạn tìm không tồn tại hoặc đã được chuyển.'])
    @vite(['resources/css/app.css'])
</head>
<body class="page">
@include('partials.nav')
<main class="sheet-page" id="main-content" tabindex="-1">
    <div class="sheet">
        <div class="sheet-margin">
            <p class="state-code" aria-hidden="true">404</p>
            <p>Tờ bản đồ này không có trong atlas.</p>
        </div>
        <div class="sheet-body">
            <h1>Không tìm thấy trang</h1>
            <p class="lead">Địa chỉ này không tồn tại hoặc đã được chuyển đi. Các trang hiện có được liệt kê dưới đây.</p>
            <ul class="index-list">
                <li><a href="/"><span class="idx-name">@include('partials.icon', ['name' => 'map', 'class' => 'ico idx-ico']) Về bản đồ</span><span class="idx-desc">đường bờ, transect, hotspot</span><span class="idx-lead" aria-hidden="true"></span><span class="idx-path">/</span></a></li>
                <li><a href="/gioi-thieu"><span class="idx-name">@include('partials.icon', ['name' => 'book', 'class' => 'ico idx-ico']) Phương pháp</span><span class="idx-desc">dữ liệu, cách tính, hạn chế</span><span class="idx-lead" aria-hidden="true"></span><span class="idx-path">/gioi-thieu</span></a></li>
                <li><a href="/huong-dan"><span class="idx-name">@include('partials.icon', ['name' => 'compass', 'class' => 'ico idx-ico']) Hướng dẫn</span><span class="idx-desc">cách đọc và dùng bản đồ</span><span class="idx-lead" aria-hidden="true"></span><span class="idx-path">/huong-dan</span></a></li>
            </ul>
            <p class="hint">WebGIS giám sát biến động bờ sông Hậu, xã Bình Mỹ, An Giang.</p>
        </div>
    </div>
</main>
</body>
</html>
