<ul class="downloads">
    @foreach (['transects' => 'Transect + chỉ số NSM/EPR/LRR', 'shorelines' => 'Đường bờ theo năm', 'hotspots' => 'Hotspot lùi bờ', 'reference' => 'Sạt lở tham chiếu'] as $layer => $label)
        <li>{{ $label }}:
            <a href="/api/export/{{ $layer }}.geojson" download>GeoJSON</a> ·
            <a href="/api/export/{{ $layer }}.csv" download>CSV</a>
        </li>
    @endforeach
</ul>
<p class="hint">Hệ toạ độ EPSG:4326. Lớp chưa có dữ liệu thì tệp tải về không có đối tượng nào (đang cập nhật).</p>
