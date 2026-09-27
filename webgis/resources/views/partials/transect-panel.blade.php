<section class="inspector" id="inspector" aria-labelledby="inspector-title" hidden>
    <header class="inspector-head">
        <div>
            <p class="eyebrow">Chi tiết transect</p>
            <h2 id="inspector-title" class="inspector-title"><span id="transect-code">—</span><span class="class-badge" id="stat-class"></span></h2>
        </div>
        <button type="button" class="icon-btn" id="inspector-close" aria-label="Đóng chi tiết transect">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </header>
    <div class="inspector-body">
        <div id="transect-detail"><p class="hint">Chọn một transect trên bản đồ.</p></div>
        <h3 class="subhead">Lịch sử vị trí đường bờ</h3>
        <div class="chart-box">
            <canvas id="history-chart"></canvas>
            <p id="chart-empty" class="empty">Chọn một transect để xem biểu đồ.</p>
        </div>
        <p class="hint chart-note">Nét đứt: nối qua năm thiếu dữ liệu, không nội suy.</p>
    </div>
</section>
