import { get } from './api';

const $ = (id) => document.getElementById(id);
const message = (text, ok = false) => {
    $('admin-message').textContent = text;
    $('admin-message').className = ok ? 'msg-ok' : 'msg-error';
};

/** Sanctum stateful: CSRF token nằm trong cookie XSRF-TOKEN (đã mã hoá URL), gửi lại qua header. */
const readCookie = (name) => {
    const m = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return m ? decodeURIComponent(m[1]) : null;
};

async function request(path, options = {}) {
    const xsrf = readCookie('XSRF-TOKEN');
    const res = await fetch(`/api${path}`, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
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
            <label><span>${c.description ?? c.key}${c.description ? `<code>${c.key}</code>` : ''}</span><input type="number" step="0.1" min="0" name="value" value="${c.value}"></label>
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

const LAYER_LABELS = { transects: 'Transect (đường cắt)', rates: 'Điểm tốc độ biến động', hotspots: 'Hotspot lùi bờ mạnh', reference: 'Sạt lở tham chiếu 2019–2020' };

async function saveLayer(key, body) {
    const { res, body: out } = await request(`/admin/config/layers/${key}`, { method: 'PUT', body: JSON.stringify(body) });
    if (res.ok) message(`Đã lưu cấu hình lớp ${LAYER_LABELS[key] ?? key}.`, true);
    else if (res.status === 401 || res.status === 403) message('Không đủ quyền để sửa lớp (cần tài khoản admin).');
    else message(out.message ?? 'Lưu cấu hình lớp thất bại.');
    return res.ok;
}

/** Danh sách lớp, hiển thị từ trên (nằm trên cùng) xuống dưới; nút ↑↓ đổi chỗ sort_order của hai lớp kề nhau. */
async function renderLayers() {
    const el = $('layer-admin');
    if (!el) return;
    let list;
    try {
        list = await get('/config/layers');
    } catch {
        el.innerHTML = '<li class="updating">Cấu hình lớp đang cập nhật.</li>';
        return;
    }
    const top = [...list].sort((a, b) => b.sort_order - a.sort_order);
    el.innerHTML = top.map((l, i) => `<li data-key="${l.key}">
        <label class="switch-row"><span><b>${l.label}</b></span><input type="checkbox" class="switch" data-f="visible" ${l.visible ? 'checked' : ''} aria-label="Hiển thị mặc định ${l.label}"></label>
        <label class="layer-opts">Độ trong suốt <input type="range" min="10" max="100" step="5" value="${l.opacity}" data-f="opacity" aria-label="Độ mờ mặc định ${l.label}"><output>${l.opacity}%</output></label>
        <span class="order-btns"><button type="button" class="btn btn-ghost" data-move="up" ${i === 0 ? 'disabled' : ''} aria-label="Đưa ${l.label} lên trên">↑</button><button type="button" class="btn btn-ghost" data-move="down" ${i === top.length - 1 ? 'disabled' : ''} aria-label="Đưa ${l.label} xuống dưới">↓</button></span>
    </li>`).join('');
    el.querySelectorAll('li').forEach((li) => {
        const key = li.dataset.key;
        li.querySelector('[data-f=visible]').addEventListener('change', (e) => saveLayer(key, { visible: e.target.checked }));
        const range = li.querySelector('[data-f=opacity]');
        range.addEventListener('input', () => { li.querySelector('output').textContent = `${range.value}%`; });
        range.addEventListener('change', () => saveLayer(key, { opacity: Number(range.value) }));
        li.querySelectorAll('[data-move]').forEach((b) => b.addEventListener('click', async () => {
            const idx = top.findIndex((l) => l.key === key);
            const other = top[b.dataset.move === 'up' ? idx - 1 : idx + 1];
            const me = top[idx];
            if (!other) return;
            const ok = await saveLayer(key, { sort_order: other.sort_order }) && await saveLayer(other.key, { sort_order: me.sort_order });
            if (ok) renderLayers();
        }));
    });
}

// Trang chỉ vào được khi đã đăng nhập bằng phiên với quyền admin: nạp cấu hình ngay.
renderThresholds();
renderLayers();
