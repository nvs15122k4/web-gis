<section id="hotspot-filter">
    <h2 class="section-title">Hotspot lùi bờ</h2>
    <p class="section-lead">Lọc đoạn bờ theo tốc độ lùi tối thiểu. Bấm một đoạn để phóng tới trên bản đồ.</p>
    <div class="input-group">
        <input type="number" id="min-rate" min="0" step="0.1" aria-label="Ngưỡng lùi bờ (m/năm)">
        <span class="input-suffix">m/năm</span>
        <button type="button" class="btn btn-primary" id="apply-filter">Lọc</button>
    </div>
    <p id="hotspot-count" class="hint" role="status"></p>
    <ul id="hotspot-list" class="item-list"></ul>
</section>
