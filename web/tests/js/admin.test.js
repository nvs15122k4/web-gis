import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const DOM = `
<form id="login-form"><input name="email" value="admin@example.test"><input name="password" value="secret"><button type="submit"></button></form>
<div id="admin-panel" hidden><span id="admin-user"></span><button id="logout"></button><div id="thresholds"></div></div>
<p id="admin-message"></p>`;

const reply = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });
const flush = () => new Promise((r) => setTimeout(r, 0));
const msg = () => document.getElementById('admin-message');

// admin.js gắn listener ở cấp module nên nạp lại cho từng test.
const boot = async () => {
    vi.resetModules();
    await import('../../resources/js/admin');
};
const submit = (el) => el.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

describe('admin page', () => {
    beforeEach(() => {
        document.body.innerHTML = DOM;
        sessionStorage.clear();
    });
    afterEach(() => { delete globalThis.fetch; });

    it('đăng nhập sai: hiện thông báo từ server, không lưu token, không mở panel', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(reply(422, { message: 'Sai thông tin.' }));
        await boot();
        submit(document.getElementById('login-form'));
        await flush();
        expect(msg().textContent).toBe('Sai thông tin.');
        expect(msg().className).toBe('msg-error');
        expect(sessionStorage.getItem('webgis_admin_token')).toBeNull();
        expect(document.getElementById('admin-panel').hidden).toBe(true);
    });

    it('đăng nhập sai không kèm message thì dùng thông báo mặc định', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(reply(500, {}));
        await boot();
        submit(document.getElementById('login-form'));
        await flush();
        expect(msg().textContent).toBe('Đăng nhập thất bại.');
    });

    it('đăng nhập đúng: gửi email/password dạng JSON, lưu token, mở panel và nạp ngưỡng', async () => {
        globalThis.fetch = vi.fn()
            .mockResolvedValueOnce(reply(200, { token: 'tok123', user: { email: 'a@x.test', role: 'admin' } }))
            .mockResolvedValueOnce(reply(200, [{ key: 'mild', description: 'Ngưỡng nhẹ', value: 0.5 }]));
        await boot();
        submit(document.getElementById('login-form'));
        await flush();

        const [url, opts] = fetch.mock.calls[0];
        expect(url).toBe('/api/auth/login');
        expect(opts.method).toBe('POST');
        expect(JSON.parse(opts.body)).toEqual({ email: 'admin@example.test', password: 'secret' });
        expect(sessionStorage.getItem('webgis_admin_token')).toBe('tok123');
        expect(msg().className).toBe('msg-ok');
        expect(document.getElementById('login-form').hidden).toBe(true);
        expect(document.getElementById('admin-panel').hidden).toBe(false);
        expect(document.getElementById('admin-user').textContent).toBe('a@x.test (admin)');
        expect(document.querySelectorAll('.threshold-form')).toHaveLength(1);
    });

    it('danh sách ngưỡng rỗng hoặc API lỗi thì hiện thông báo đang cập nhật', async () => {
        const login = reply(200, { token: 't', user: { email: 'a@x.test', role: 'admin' } });
        globalThis.fetch = vi.fn().mockResolvedValueOnce(login).mockResolvedValueOnce(reply(200, []));
        await boot();
        submit(document.getElementById('login-form'));
        await flush();
        expect(document.getElementById('thresholds').textContent).toContain('Chưa có ngưỡng nào');

        document.body.innerHTML = DOM;
        globalThis.fetch = vi.fn().mockResolvedValueOnce(login).mockResolvedValueOnce(reply(500, {}));
        await boot();
        submit(document.getElementById('login-form'));
        await flush();
        expect(document.getElementById('thresholds').textContent).toContain('Cấu hình ngưỡng đang cập nhật');
    });

    describe('cập nhật ngưỡng', () => {
        const openPanel = async (putResponse) => {
            sessionStorage.setItem('webgis_admin_token', 'tok123');
            globalThis.fetch = vi.fn()
                .mockResolvedValueOnce(reply(200, { token: 'tok123', user: { email: 'a@x.test', role: 'admin' } }))
                .mockResolvedValueOnce(reply(200, [{ key: 'severe', description: null, value: 2 }]))
                .mockResolvedValueOnce(putResponse);
            await boot();
            submit(document.getElementById('login-form'));
            await flush();
            const form = document.querySelector('.threshold-form');
            form.elements.value.value = '3.5';
            submit(form);
            await flush();
            return fetch.mock.calls[2];
        };

        it('PUT kèm Bearer token và giá trị mới, báo thành công', async () => {
            const [url, opts] = await openPanel(reply(200, { value: 3.5 }));
            expect(url).toBe('/api/admin/config/thresholds/severe');
            expect(opts.method).toBe('PUT');
            expect(opts.headers.Authorization).toBe('Bearer tok123');
            expect(JSON.parse(opts.body)).toEqual({ value: '3.5' });
            expect(msg().textContent).toBe('Đã cập nhật severe = 3.5.');
            expect(msg().className).toBe('msg-ok');
        });

        it.each([401, 403])('HTTP %i báo không đủ quyền', async (status) => {
            await openPanel(reply(status, {}));
            expect(msg().textContent).toContain('Không đủ quyền');
            expect(msg().className).toBe('msg-error');
        });

        it('lỗi validate hiện message của server, thiếu message thì dùng mặc định', async () => {
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

    it('không có token thì request không gửi header Authorization', async () => {
        const sent = [];
        globalThis.fetch = async (url, opts) => { sent.push({ url, headers: opts.headers }); return reply(401, {}); };
        await boot();
        submit(document.getElementById('login-form'));
        await flush();
        expect(sent).toHaveLength(1);
        expect(Object.keys(sent[0].headers)).toEqual(['Accept', 'Content-Type']);
    });

    it('đăng xuất xoá token khỏi sessionStorage', async () => {
        sessionStorage.setItem('webgis_admin_token', 'tok');
        // jsdom không cài location.reload nên thay bằng stub để kiểm tra việc gọi.
        const reload = vi.fn();
        vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, reload });
        await boot();
        document.getElementById('logout').click();
        expect(sessionStorage.getItem('webgis_admin_token')).toBeNull();
        expect(reload).toHaveBeenCalled();
    });
});
