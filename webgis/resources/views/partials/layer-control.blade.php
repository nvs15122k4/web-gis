<section id="layer-control">
    <h2>Lớp dữ liệu</h2>
    <label><input type="checkbox" id="layer-transects" checked> Transect (màu theo mức biến động)</label>
    <label class="sub"><input type="checkbox" id="only-significant"> Chỉ transect có ý nghĩa thống kê (95%)</label>
    <label><input type="checkbox" id="layer-hotspots" checked> Hotspot lùi bờ mạnh</label>
    <label><input type="checkbox" id="layer-reference"> Sạt lở tham chiếu 2019–2020</label>
    <p id="reference-status" class="hint"></p>
</section>
<section id="years">
    <h2>Đường bờ theo năm</h2>
    <div id="year-list"></div>
</section>
<section id="compare">
    <h2>So sánh 2 năm</h2>
    <div class="row">
        <select id="compare-a" aria-label="Năm A"></select>
        <select id="compare-b" aria-label="Năm B"></select>
    </div>
    <div class="row">
        <button type="button" id="compare-apply">So sánh</button>
        <button type="button" id="compare-clear">Bỏ so sánh</button>
    </div>
</section>
