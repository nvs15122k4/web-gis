<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WebGIS giám sát bờ sông Hậu — xã Bình Mỹ</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.WEBGIS = @json(['geoserver' => $geoserver, 'years' => $years]);</script>
</head>
<body>
<div class="app">
    <button id="sidebar-toggle" type="button" aria-label="Mở/đóng bảng điều khiển" aria-controls="sidebar">☰</button>
    <aside class="sidebar" id="sidebar">
        <h1>WebGIS Sông Hậu</h1>
        <p class="subtitle">Giám sát biến động bờ sông · xã Bình Mỹ, An Giang</p>
        <p class="sidelinks"><a href="/gioi-thieu">Giới thiệu &amp; phương pháp</a> · <a href="/admin">Quản trị ngưỡng</a></p>
        <div id="data-banner" class="banner" role="status" hidden></div>
        <p id="status" role="status"></p>
        @include('partials.summary-panel')
        @include('partials.layer-control')
        @include('partials.hotspot-filter')
        @include('partials.transect-panel')
        @include('partials.chart-panel')
        @include('partials.share-panel')
        <p class="disclaimer">Thông tin tham khảo phục vụ nghiên cứu, không thay thế cảnh báo chính thức của cơ quan chức năng.</p>
    </aside>
    <div id="map"></div>
</div>
</body>
</html>
