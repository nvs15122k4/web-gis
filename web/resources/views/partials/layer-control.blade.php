<section id="layer-control" class="card tree">
    <h2 class="section-title">Cây lớp bản đồ</h2>
    <details class="tree-group" open>
        <summary>Biến động đường bờ</summary>
        <label class="switch-row">
            <span><b>Điểm tốc độ biến động (LRR)</b><small>Mỗi transect một chấm trên đường bờ mới nhất; chấm to có ý nghĩa thống kê</small>
                <span class="swatch-row ramp" aria-hidden="true"><i style="background:#b2182b"></i><i style="background:#ef6c4d"></i><i style="background:#f6b48e"></i><i style="background:#d9d9d9"></i><i style="background:#9ecae1"></i><i style="background:#4a98c9"></i><i style="background:#08519c"></i></span>
            </span>
            <input type="checkbox" class="switch" id="layer-rates" checked>
        </label>
        <div class="layer-opts" data-layer="rates">
            <label>Độ trong suốt <input type="range" min="10" max="100" value="100" aria-label="Độ mờ lớp điểm tốc độ biến động"><output>100%</output></label>
            <span class="order-btns"><button type="button" class="btn btn-ghost" data-order="up" title="Đưa lên trên" aria-label="Đưa lớp điểm tốc độ lên trên">↑</button><button type="button" class="btn btn-ghost" data-order="down" title="Đưa xuống dưới" aria-label="Đưa lớp điểm tốc độ xuống dưới">↓</button></span>
        </div>
        <label class="switch-row">
            <span><b>Transect (đường cắt vuông góc bờ)</b><small>Màu theo mức biến động; nên phóng to để xem</small>
                <span class="swatch-row" aria-hidden="true"><i style="background:var(--c-accretion)"></i><i style="background:var(--c-stable)"></i><i style="background:var(--c-mild-erosion)"></i><i style="background:var(--c-severe-erosion)"></i></span>
            </span>
            <input type="checkbox" class="switch" id="layer-transects">
        </label>
        <div class="layer-opts" data-layer="transects">
            <label>Độ trong suốt <input type="range" min="10" max="100" value="100" aria-label="Độ mờ lớp Transect"><output>100%</output></label>
            <span class="order-btns"><button type="button" class="btn btn-ghost" data-order="up" title="Đưa lên trên" aria-label="Đưa lớp Transect lên trên">↑</button><button type="button" class="btn btn-ghost" data-order="down" title="Đưa xuống dưới" aria-label="Đưa lớp Transect xuống dưới">↓</button></span>
        </div>
        <label class="switch-row sub">
            <span>Chỉ transect có ý nghĩa thống kê (95%)</span>
            <input type="checkbox" class="switch" id="only-significant">
        </label>
    </details>
    <details class="tree-group" open>
        <summary>Cảnh báo sạt lở</summary>
        <label class="switch-row">
            <span><b>Hotspot lùi bờ mạnh</b><small>Đoạn bờ gộp từ transect lùi mạnh</small>
                <span class="swatch-row" aria-hidden="true"><i style="background:var(--c-severe-erosion);height:4px"></i></span></span>
            <input type="checkbox" class="switch" id="layer-hotspots" checked>
        </label>
        <div class="layer-opts" data-layer="hotspots">
            <label>Độ trong suốt <input type="range" min="10" max="100" value="100" aria-label="Độ mờ lớp Hotspot"><output>100%</output></label>
            <span class="order-btns"><button type="button" class="btn btn-ghost" data-order="up" title="Đưa lên trên" aria-label="Đưa lớp Hotspot lên trên">↑</button><button type="button" class="btn btn-ghost" data-order="down" title="Đưa xuống dưới" aria-label="Đưa lớp Hotspot xuống dưới">↓</button></span>
        </div>
        <label class="switch-row">
            <span><b>Sạt lở tham chiếu 2019–2020</b><small>Theo mức độ QĐ 01/2011/QĐ-TTg</small>
                <span class="swatch-row" aria-hidden="true"><i class="r" style="background:#b71c1c"></i><i class="r" style="background:#ef6c00"></i><i class="r" style="background:#7b3fe4"></i></span></span>
            <input type="checkbox" class="switch" id="layer-reference">
        </label>
        <div class="layer-opts" data-layer="reference">
            <label>Độ trong suốt <input type="range" min="10" max="100" value="100" aria-label="Độ mờ lớp Sạt lở tham chiếu"><output>100%</output></label>
            <span class="order-btns"><button type="button" class="btn btn-ghost" data-order="up" title="Đưa lên trên" aria-label="Đưa lớp Sạt lở tham chiếu lên trên">↑</button><button type="button" class="btn btn-ghost" data-order="down" title="Đưa xuống dưới" aria-label="Đưa lớp Sạt lở tham chiếu xuống dưới">↓</button></span>
        </div>
        <p id="reference-status" class="hint" role="status"></p>
    </details>
</section>

<section id="years" class="card tree">
    <h2 class="section-title">Đường bờ theo năm</h2>
    <p class="hint">Bật nhiều năm để xem dịch chuyển; năm mờ là chưa có ảnh hợp lệ.</p>
    <div id="year-list" class="year-grid"></div>
</section>

<section id="alt-sources" class="card tree" aria-labelledby="alt-sources-title">
    <h2 class="section-title" id="alt-sources-title">Đường bờ đối chứng</h2>
    <p class="hint">Hai bản dữ liệu ảnh vệ tinh khác (Sentinel-2 Collection 1, Landsat 8/9) đặt cạnh bản chính để kiểm tra chéo. Đây là bản đối chứng độc lập, không thay thế bản chính và không phải dự báo sạt lở.</p>
    <div id="alt-source-list" class="alt-source-list"><p class="hint">Đang tải…</p></div>
    <p id="alt-low-note" class="note" role="note" hidden></p>
    <div class="btn-row"><button type="button" class="btn btn-ghost" id="alt-clear">Tắt hết đường bờ đối chứng</button></div>
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

<section id="swipe-card" class="card">
    <h2 class="section-title">So sánh vuốt (swipe)</h2>
    <p class="hint">Kéo thanh dọc trên bản đồ: đường bờ năm A bên trái, năm B bên phải.</p>
    <div class="compare-row">
        <label class="field"><span><i class="line-key" style="background:#1f6feb"></i>Năm A</span><select id="swipe-a" aria-label="Năm A (bên trái)"></select></label>
        <span class="compare-arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        <label class="field"><span><i class="line-key" style="background:#f0883e"></i>Năm B</span><select id="swipe-b" aria-label="Năm B (bên phải)"></select></label>
    </div>
    <div class="btn-row"><button type="button" class="btn btn-primary" id="swipe-toggle" aria-pressed="false">Bật/tắt vuốt</button></div>
</section>
