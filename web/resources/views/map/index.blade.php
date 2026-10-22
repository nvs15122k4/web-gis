<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WebGIS giám sát bờ sông Hậu — xã Bình Mỹ</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.WEBGIS = @json(['geoserver' => $geoserver, 'years' => $years, 'vietmapKey' => $vietmapKey]);</script>
</head>
<body class="map-page">
@include('partials.nav', ['withSidebarToggle' => true])
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Bảng điều khiển">
        <div class="sidebar-alerts">
            <div id="data-banner" class="banner" role="status" hidden></div>
            <p id="status" class="status" role="status"></p>
        </div>

        <div class="tabs" role="tablist" aria-label="Chức năng">
            <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview" aria-selected="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Tổng quan
            </button>
            <button type="button" role="tab" class="tab" id="tab-layers" data-tab="layers" aria-controls="panel-layers" aria-selected="false" tabindex="-1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/></svg>Lớp dữ liệu
            </button>
            <button type="button" role="tab" class="tab" id="tab-hotspots" data-tab="hotspots" aria-controls="panel-hotspots" aria-selected="false" tabindex="-1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c1 4 5 6 5 11a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-4-1-6 1-10Z"/></svg>Hotspot
            </button>
            <button type="button" role="tab" class="tab" id="tab-share" data-tab="share" aria-controls="panel-share" aria-selected="false" tabindex="-1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>Chia sẻ
            </button>
        </div>

        <div class="panels">
            <div class="panel" role="tabpanel" id="panel-overview" aria-labelledby="tab-overview">
                @include('partials.summary-panel')
            </div>
            <div class="panel" role="tabpanel" id="panel-layers" aria-labelledby="tab-layers" hidden>
                @include('partials.layer-control')
            </div>
            <div class="panel" role="tabpanel" id="panel-hotspots" aria-labelledby="tab-hotspots" hidden>
                @include('partials.hotspot-filter')
            </div>
            <div class="panel" role="tabpanel" id="panel-share" aria-labelledby="tab-share" hidden>
                @include('partials.share-panel')
            </div>
        </div>

        <p class="disclaimer">Thông tin tham khảo phục vụ nghiên cứu, không thay thế cảnh báo chính thức của cơ quan chức năng.</p>
    </aside>

    <div class="map-wrap" id="map-wrap">
        <div id="map"></div>
        @include('partials.transect-panel')
        @include('partials.legend')
    </div>
</div>
</body>
</html>
