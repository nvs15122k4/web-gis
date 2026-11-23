import { beforeEach, describe, expect, it, vi } from 'vitest';

const fakeMap = (zoom = 16, lat = 10.578, lng = 105.208) => {
    const handlers = {};
    return {
        getZoom: () => zoom,
        getCenter: () => ({ lat, lng }),
        on: (evt, fn) => { handlers[evt] = fn; },
        fire: (evt) => handlers[evt]?.(),
    };
};

// Module giữ state ở cấp module nên nạp lại mỗi test để cô lập.
const load = async () => {
    vi.resetModules();
    return import('../../resources/js/permalink');
};

describe('readPermalink', () => {
    beforeEach(() => {
        location.hash = '';
    });

    it('đọc khung nhìn và transect từ hash', async () => {
        location.hash = '#map=16/10.57800/105.20800&t=12';
        const { readPermalink } = await load();
        expect(readPermalink()).toEqual({ view: { zoom: 16, center: [10.578, 105.208] }, transectId: 12 });
    });

    it('hash rỗng cho view null và transectId null', async () => {
        const { readPermalink } = await load();
        expect(readPermalink()).toEqual({ view: null, transectId: null });
    });

    it('khung nhìn thiếu phần hoặc không phải số thì bị bỏ qua', async () => {
        const { readPermalink } = await load();
        for (const h of ['#map=16/10.5', '#map=a/b/c', '#map=']) {
            location.hash = h;
            expect(readPermalink().view).toBeNull();
        }
    });

    it('transect không hợp lệ (0, âm, thập phân, chữ) trả về null', async () => {
        const { readPermalink } = await load();
        for (const t of ['0', '-3', '1.5', 'abc', '']) {
            location.hash = `#t=${t}`;
            expect(readPermalink().transectId).toBeNull();
        }
    });
});

describe('initPermalink', () => {
    beforeEach(() => {
        location.hash = '';
        document.body.innerHTML = '<button id="share-link"></button><p id="share-message"></p>';
    });

    it('ghi hash khi bản đồ moveend, làm tròn 5 chữ số và giữ dấu "/"', async () => {
        const { initPermalink } = await load();
        const map = fakeMap(14, 10.123456789, 105.987654321);
        initPermalink(map);
        map.fire('moveend');
        expect(location.hash).toBe('#map=14/10.12346/105.98765');
    });

    it('setTransect thêm tham số t vào hash', async () => {
        const { initPermalink } = await load();
        const map = fakeMap();
        const link = initPermalink(map);
        link.setTransect(42);
        expect(location.hash).toContain('t=42');
        expect(location.hash).toContain('map=16/10.57800/105.20800');
    });

    it('bấm Share sao chép URL vào clipboard và báo thành công', async () => {
        const { initPermalink } = await load();
        const writeText = vi.fn().mockResolvedValue();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        initPermalink(fakeMap());
        document.getElementById('share-link').click();
        await vi.waitFor(() => expect(writeText).toHaveBeenCalledWith(location.href));
        await vi.waitFor(() => expect(document.getElementById('share-message').textContent).toBe('Đã sao chép liên kết.'));
    });

    it('không có quyền clipboard thì hiện URL để người dùng tự chép', async () => {
        const { initPermalink } = await load();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText: vi.fn().mockRejectedValue(new Error('denied')) }, configurable: true });
        initPermalink(fakeMap());
        document.getElementById('share-link').click();
        await vi.waitFor(() => expect(document.getElementById('share-message').textContent).toBe(location.href));
    });

    it('thiếu nút Share trong DOM vẫn ghi hash theo bản đồ', async () => {
        document.body.innerHTML = '';
        const { initPermalink } = await load();
        const map = fakeMap(12, 10.5, 105.2);
        initPermalink(map);
        map.fire('moveend');
        expect(location.hash).toBe('#map=12/10.50000/105.20000');
    });
});
