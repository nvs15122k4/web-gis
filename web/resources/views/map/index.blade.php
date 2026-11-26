<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WebGIS giám sát bờ sông Hậu — xã Bình Mỹ</title>
    <link rel="preload" href="/data/aoi.json" as="fetch" crossorigin="anonymous">
    @if (! empty($geoserver['url']))
        <link rel="preconnect" href="{{ $geoserver['url'] }}" crossorigin>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>window.WEBGIS = @json(['geoserver' => $geoserver, 'years' => $years, 'vietmapKey' => $vietmapKey]);</script>
</head>
<body class="map-page">
@include('partials.nav', ['withSidebarToggle' => true])
<div class="app">
    <aside class="sidebar" id="sidebar" aria-label="Bảng điều khiển">
        <div class="rail tabs" role="tablist" aria-label="Chức năng" aria-orientation="vertical">
            <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview" aria-selected="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg><span>Tổng quan</span>
            </button>
            <button type="button" role="tab" class="tab" id="tab-layers" data-tab="layers" aria-controls="panel-layers" aria-selected="false" tabindex="-1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/></svg><span>Lớp dữ liệu</span>
            </button>
            <button type="button" role="tab" class="tab" id="tab-hotspots" data-tab="hotspots" aria-controls="panel-hotspots" aria-selected="false" tabindex="-1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v5M12 17.5v.01"/></svg><span>Hotspot</span>
            </button>
            <button type="button" role="tab" class="tab" id="tab-share" data-tab="share" aria-controls="panel-share" aria-selected="false" tabindex="-1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg><span>Chia sẻ</span>
            </button>
        </div>

        <div class="sidebar-body">
            <header class="sidebar-head">
                <p class="eyebrow">Khu vực nghiên cứu</p>
                <h1 class="sidebar-title">Xã Bình Mỹ, An Giang</h1>
                <p class="sidebar-meta"><span class="chip">Sông Hậu</span>@if (! empty($years['all']))<span class="chip">{{ $years['all'][0] }}–{{ end($years['all']) }}</span>@endif<span class="chip">Sentinel-2</span></p>
            </header>
            <div class="sidebar-alerts">
                <div id="data-banner" class="banner" role="status" hidden></div>
                <p id="status" class="status" role="status"></p>
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
        </div>
    </aside>

    <main class="map-wrap" id="main-content" tabindex="-1" aria-label="Bản đồ">
        <div id="map"></div>
        <form class="map-search" id="map-search" role="search" autocomplete="off">
            <input type="search" placeholder="Tìm mã transect hoặc toạ độ (10.54, 105.29)" aria-label="Tìm kiếm trên bản đồ">
            <ul class="search-results" hidden></ul>
        </form>
        <div class="map-actions">
            <button type="button" class="tool-btn" id="map-print" title="In / xuất PDF bản đồ" aria-label="In bản đồ"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/></svg></button>
            <button type="button" class="tool-btn" id="attr-toggle" title="Bảng thuộc tính" aria-label="Mở bảng thuộc tính" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM3 10h18M9 5v14"/></svg></button>
        </div>
        <div class="swipe-bar" id="swipe-bar" hidden>
            <span class="swipe-tag a" id="swipe-label-a"></span><span class="swipe-tag b" id="swipe-label-b"></span>
            <div class="swipe-handle" role="slider" tabindex="0" aria-label="Thanh vuốt so sánh" aria-valuemin="2" aria-valuemax="98" aria-valuenow="50"></div>
        </div>
        <p id="measure-readout" class="measure-readout" role="status" hidden></p>
        @include('partials.transect-panel')
        @include('partials.legend')
        @include('partials.attr-table')
        @include('partials.timeline')
    </main>
</div>
</body>
</html>
