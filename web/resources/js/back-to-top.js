// Nút "Lên đầu trang": chỉ hiện khi đã cuộn quá một màn hình.
const btn = document.getElementById('back-to-top');
if (btn) {
    const update = () => { btn.hidden = window.scrollY < window.innerHeight; };
    window.addEventListener('scroll', update, { passive: true });
    btn.addEventListener('click', () => {
        const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: smooth ? 'smooth' : 'auto' });
    });
    update();
}
