import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const DOM = `
<div id="admin-panel"><span id="admin-user">a@x.test (admin)</span><div id="thresholds"></div><ol id="layer-admin"></ol></div>
<p id="admin-message"></p>`;

const reply = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });
const flush = () => new Promise((r) => setTimeout(r, 0));
const msg = () => document.getElementById('admin-message');

// admin.js chạy ngay ở cấp module nên nạp lại cho từng test.
const boot = async () => {
    vi.resetModules();
    await import('../../resources/js/admin');
    await flush();
};
const submit = (el) => el.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
const setXsrf = (v) => { document.cookie = `XSRF-TOKEN=${v}; path=/`; };
const clearXsrf = () => { document.cookie = 'XSRF-TOKEN=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT'; };

describe('admin page', () => {
    beforeEach(() => {
        document.body.innerHTML = DOM;
        sessionStorage.clear();
        clearXsrf();
    });
    afterEach(() => { delete globalThis.fetch; clearXsrf(); });

    it('nạp ngay ngưỡng khi mở trang, không có form đăng nhập hay token', async () => {
        globalThis.fetch = vi.fn()
            .mockResolvedValueOnce(reply(200, [{ key: 'mild', description: 'Ngưỡng nhẹ', value: 0.5 }]))
            .mockResolvedValueOnce(reply(200, []));
        await boot();
        expect(fetch.mock.calls[0][0]).toBe('/api/config/thresholds');
        expect(document.querySelectorAll('.threshold-form')).toHaveLength(1);
        expect(document.getElementById('login-form')).toBeNull();
        expect(sessionStorage.length).toBe(0);
    });

    it('danh sách ngưỡng rỗng hoặc API lỗi thì hiện thông báo đang cập nhật', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(reply(200, []));
        await boot();
        expect(document.getElementById('thresholds').textContent).toContain('Chưa có ngưỡng nào');

        document.body.innerHTML = DOM;
        globalThis.fetch = vi.fn().mockResolvedValue(reply(500, {}));
        await boot();
        expect(document.getElementById('thresholds').textContent).toContain('Cấu hình ngưỡng đang cập nhật');
    });

    describe('cập nhật ngưỡng', () => {
        const openPanel = async (putResponse) => {
            globalThis.fetch = vi.fn()
                .mockResolvedValueOnce(reply(200, [{ key: 'severe', description: null, value: 2 }]))
                .mockResolvedValueOnce(reply(200, []))
                .mockResolvedValueOnce(putResponse);
            await boot();
            const form = document.querySelector('.threshold-form');
            form.elements.value.value = '3.5';
            submit(form);
            await flush();
            return fetch.mock.calls[2];
        };

        it('PUT dùng phiên (cookie) kèm CSRF, không gửi Bearer, báo thành công', async () => {
            setXsrf('abc%3D%3D');
            const [url, opts] = await openPanel(reply(200, { value: 3.5 }));
            expect(url).toBe('/api/admin/config/thresholds/severe');
            expect(opts.method).toBe('PUT');
            expect(opts.credentials).toBe('same-origin');
            expect(opts.headers['X-XSRF-TOKEN']).toBe('abc==');
            expect(opts.headers.Authorization).toBeUndefined();
            expect(JSON.parse(opts.body)).toEqual({ value: '3.5' });
            expect(msg().textContent).toBe('Đã cập nhật severe = 3.5.');
            expect(msg().className).toBe('msg-ok');
        });

        it('không có cookie XSRF thì không gửi header X-XSRF-TOKEN', async () => {
            const [, opts] = await openPanel(reply(200, { value: 3.5 }));
            expect(opts.headers['X-XSRF-TOKEN']).toBeUndefined();
        });

        it.each([401, 403])('HTTP %i báo không đủ quyền', async (status) => {
            await openPanel(reply(status, {}));
            expect(msg().textContent).toContain('Không đủ quyền');
            expect(msg().className).toBe('msg-error');
        });

        it('lỗi validate hiện message của server', async () => {
            await openPanel(reply(422, { message: 'Giá trị không hợp lệ.' }));
            expect(msg().textContent).toBe('Giá trị không hợp lệ.');
        });

        it('lỗi không rõ dùng thông báo mặc định', async () => {
            await openPanel(reply(500, {}));
            expect(msg().textContent).toBe('Cập nhật thất bại.');
        });

        it('thiếu description thì nhãn dùng key', async () => {
            await openPanel(reply(200, { value: 3.5 }));
            expect(document.querySelector('.threshold-form label').textContent).toContain('severe');
        });
    });
});
