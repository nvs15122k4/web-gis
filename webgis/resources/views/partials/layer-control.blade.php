<section id="layer-control" class="card">
    <h2 class="section-title">Lớp chuyên đề</h2>
    <label class="switch-row">
        <span><b>Transect</b><small>Màu theo mức biến động</small></span>
        <input type="checkbox" class="switch" id="layer-transects" checked>
    </label>
    <label class="switch-row sub">
        <span>Chỉ transect có ý nghĩa thống kê (95%)</span>
        <input type="checkbox" class="switch" id="only-significant">
    </label>
    <label class="switch-row">
        <span><b>Hotspot lùi bờ mạnh</b><small>Đoạn bờ gộp từ transect lùi mạnh</small></span>
        <input type="checkbox" class="switch" id="layer-hotspots" checked>
    </label>
    <label class="switch-row">
        <span><b>Sạt lở tham chiếu 2019–2020</b><small>Theo mức độ QĐ 01/2011/QĐ-TTg</small></span>
        <input type="checkbox" class="switch" id="layer-reference">
    </label>
    <p id="reference-status" class="hint" role="status"></p>
</section>

<section id="years" class="card">
    <h2 class="section-title">Đường bờ theo năm</h2>
    <p class="hint">Bật nhiều năm để xem dịch chuyển; năm mờ là chưa có ảnh hợp lệ.</p>
    <div id="year-list" class="year-grid"></div>
</section>

<section id="compare" class="card">
    <h2 class="section-title">So sánh hai năm</h2>
    <div class="compare-row">
        <label class="field"><span><i class="line-key" data-compare="a"></i>Năm A</span><select id="compare-a" aria-label="Năm A"></select></label>
        <span class="compare-arrow" aria-hidden="true">→</span>
        <label class="field"><span><i class="line-key" data-compare="b"></i>Năm B</span><select id="compare-b" aria-label="Năm B"></select></label>
    </div>
    <div class="btn-row">
        <button type="button" class="btn btn-primary" id="compare-apply">So sánh</button>
        <button type="button" class="btn btn-ghost" id="compare-clear">Bỏ so sánh</button>
    </div>
</section>
