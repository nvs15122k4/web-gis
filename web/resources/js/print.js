/** In bản đồ: dùng hộp thoại in của trình duyệt (chọn "Lưu thành PDF" để xuất); CSS in ẩn các bảng điều khiển. */
export function initPrint() {
    document.getElementById('map-print')?.addEventListener('click', () => window.print());
}
