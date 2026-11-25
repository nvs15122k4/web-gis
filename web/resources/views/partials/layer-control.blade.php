<section id="layer-control" class="card">
    <h2 class="section-title">Lớp chuyên đề</h2>
    <label class="switch-row">
        <span><b>Transect</b><small>Màu theo mức biến động</small>
            <span class="swatch-row" aria-hidden="true"><i style="background:var(--c-accretion)"></i><i style="background:var(--c-stable)"></i><i style="background:var(--c-mild-erosion)"></i><i style="background:var(--c-severe-erosion)"></i></span>
        </span>
        <input type="checkbox" class="switch" id="layer-transects" checked>
    </label>
    <label class="switch-row sub">
        <span>Chỉ transect có ý nghĩa thống kê (95%)</span>
        <input type="checkbox" class="switch" id="only-significant">
    </label>
    <label class="switch-row">
        <span><b>Hotspot lùi bờ mạnh</b><small>Đoạn bờ gộp từ transect lùi mạnh</small>
            <span class="swatch-row" aria-hidden="true"><i style="background:var(--c-severe-erosion);height:4px"></i></span></span>
        <input type="checkbox" class="switch" id="layer-hotspots" checked>
    </label>
    <label class="switch-row">
        <span><b>Sạt lở tham chiếu 2019–2020</b><small>Theo mức độ QĐ 01/2011/QĐ-TTg</small>
            <span class="swatch-row" aria-hidden="true"><i class="r" style="background:#b71c1c"></i><i class="r" style="background:#ef6c00"></i><i class="r" style="background:#7b3fe4"></i></span></span>
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
        <span class="compare-arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        <label class="field"><span><i class="line-key" data-compare="b"></i>Năm B</span><select id="compare-b" aria-label="Năm B"></select></label>
    </div>
    <div class="btn-row">
        <button type="button" class="btn btn-primary" id="compare-apply">So sánh</button>
        <button type="button" class="btn btn-ghost" id="compare-clear">Bỏ so sánh</button>
    </div>
</section>
