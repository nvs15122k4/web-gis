import { get } from './api';

const TOKEN_KEY = 'webgis_admin_token';
const $ = (id) => document.getElementById(id);
const message = (text, ok = false) => {
    $('admin-message').textContent = text;
    $('admin-message').className = ok ? 'msg-ok' : 'msg-error';
};

async function request(path, options = {}) {
    const token = sessionStorage.getItem(TOKEN_KEY);
    const res = await fetch(`/api${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    });
    return { res, body: await res.json().catch(() => ({})) };
}

async function renderThresholds() {
    let list;
    try {
        list = await get('/config/thresholds');
    } catch {
        $('thresholds').innerHTML = '<p class="updating">Cấu hình ngưỡng đang cập nhật.</p>';
        return;
    }
    if (!list.length) {
        $('thresholds').innerHTML = '<p class="updating">Chưa có ngưỡng nào — đang cập nhật.</p>';
        return;
    }
    $('thresholds').innerHTML = list
        .map((c) => `<form class="threshold-form" data-key="${c.key}">
            <label>${c.description ?? c.key} <input type="number" step="0.1" min="0" name="value" value="${c.value}"></label>
            <button type="submit" class="btn btn-primary">Lưu</button></form>`)
        .join('');
    document.querySelectorAll('.threshold-form').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const key = form.dataset.key;
            const { res, body } = await request(`/admin/config/thresholds/${key}`, {
                method: 'PUT',
                body: JSON.stringify({ value: form.elements.value.value }),
            });
            if (res.ok) message(`Đã cập nhật ${key} = ${body.value}.`, true);
            else if (res.status === 401 || res.status === 403) message('Không đủ quyền để sửa ngưỡng (cần tài khoản admin).');
            else message(body.message ?? 'Cập nhật thất bại.');
        });
    });
}

function showPanel(user) {
    $('login-form').hidden = true;
    $('admin-panel').hidden = false;
    $('admin-user').textContent = `${user.email} (${user.role})`;
    renderThresholds();
}

$('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const { res, body } = await request('/auth/login', { method: 'POST', body: JSON.stringify(data) });
    if (!res.ok) {
        message(body.message ?? 'Đăng nhập thất bại.');
        return;
    }
    sessionStorage.setItem(TOKEN_KEY, body.token);
    message('Đăng nhập thành công.', true);
    showPanel(body.user);
});

$('logout').addEventListener('click', () => {
    sessionStorage.removeItem(TOKEN_KEY);
    location.reload();
});
