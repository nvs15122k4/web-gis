<section id="share" class="card">
    <h2 class="section-title">Chia sẻ khung nhìn</h2>
    <p class="hint">Liên kết giữ nguyên vị trí, mức phóng và transect đang chọn.</p>
    <button type="button" class="btn btn-primary btn-block" id="share-link">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
        Sao chép liên kết
    </button>
    <p id="share-message" class="hint share-message" role="status"></p>
</section>
<section class="card">
    <h2 class="section-title">Tải dữ liệu</h2>
    @include('partials.downloads')
</section>
