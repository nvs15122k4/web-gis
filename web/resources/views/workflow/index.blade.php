<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.head', ['title' => 'Quy trình giải đoán ảnh bằng QGIS — WebGIS Sông Hậu', 'description' => 'Chín bước làm bằng giao diện QGIS 3.40, từ ảnh Sentinel-2 đến đường bờ, transect và tốc độ biến động bờ sông Hậu, xã Bình Mỹ.'])
    @vite(['resources/css/app.css', 'resources/js/steps.js', 'resources/js/back-to-top.js'])
</head>
<body class="page">
@include('partials.nav')
<main id="main-content" tabindex="-1">
<header class="page-head">
    <div class="page-head-inner">
        <p class="eyebrow">Quy trình xử lý dữ liệu</p>
        <h1>Quy trình giải đoán ảnh bằng QGIS</h1>
        <p class="lead">Cách dữ liệu của WebGIS được dựng, từng bước, từ ảnh Sentinel-2 đến đường bờ {{ $years[0] }}–{{ end($years) }}, transect và tốc độ biến động.
        Mục tiêu, dữ liệu, tiêu chí phân loại, độ bất định và hạn chế nằm ở trang <a href="/gioi-thieu">Phương pháp</a>.</p>
        <dl class="page-stamp" aria-label="Thông tin quy trình">
            <div><dt>Phần mềm</dt><dd>QGIS 3.40</dd></div>
            <div><dt>Số bước</dt><dd>9</dd></div>
            <div><dt>Giai đoạn</dt><dd>{{ $years[0] }}–{{ end($years) }}</dd></div>
        </dl>
    </div>
</header>
@include('partials.giai-doan-anh')
</main>
@include('partials.back-to-top')
</body>
</html>
