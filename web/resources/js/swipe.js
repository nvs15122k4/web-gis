import L from 'leaflet';
import { api } from './api';
import { USE_WMS, wms } from './layers';
import { setStatus, UPDATING } from './status';

const COLORS = { a: '#1f6feb', b: '#f0883e' };
const BIG = 99999;

/** Đa giác cắt (toạ độ cục bộ của pane) giữ phần bên trái (side='a') hoặc bên phải (side='b') đường vuốt x. */
export function clipPolygon(side, x) {
    const [l, r] = side === 'a' ? [-BIG, x] : [x, BIG];
    return `polygon(${l}px ${-BIG}px, ${r}px ${-BIG}px, ${r}px ${BIG}px, ${l}px ${BIG}px)`;
}

function yearLayer(year, color, pane) {
    if (USE_WMS) return wms('shorelines', { cql_filter: `year=${year}`, env: `color:${color.replace('#', '')}`, pane });
    const layer = L.geoJSON(null, { pane, style: { color, weight: 3.5, opacity: 0.95 }, interactive: false });
    api.shorelines(year).then((fc) => layer.addData(fc)).catch(() => setStatus(`Dữ liệu đường bờ năm ${year}: ${UPDATING.toLowerCase()}.`));
    return layer;
}

/**
 * So sánh vuốt (swipe) giữa hai năm: đường bờ năm A hiện bên trái thanh vuốt, năm B bên phải.
 * Chỉ dùng đường bờ do pipeline tính sẵn; năm chưa có dữ liệu thì báo "Đang cập nhật".
 */
export function initSwipe(map, years) {
    const bar = document.getElementById('swipe-bar');
    const toggle = document.getElementById('swipe-toggle');
    if (!bar || !toggle) return null;
    const selA = document.getElementById('compare-a');
    const selB = document.getElementById('compare-b');
    const handle = bar.querySelector('.swipe-handle');

    map.createPane('swipeA').style.zIndex = 450;
    map.createPane('swipeB').style.zIndex = 451;
    const layers = { a: null, b: null };
    let ratio = 0.5;
    let active = false;

    const x = () => map.getSize().x * ratio;
    const clip = () => {
        const lx = map.containerPointToLayerPoint([x(), 0]).x;
        map.getPane('swipeA').style.clipPath = clipPolygon('a', lx);
        map.getPane('swipeB').style.clipPath = clipPolygon('b', lx);
        handle.style.left = `${ratio * 100}%`;
        handle.setAttribute('aria-valuenow', String(Math.round(ratio * 100)));
    };
    const load = () => {
        for (const k of ['a', 'b']) if (layers[k]) map.removeLayer(layers[k]);
        const missing = [];
        for (const [k, sel] of [['a', selA], ['b', selB]]) {
            const year = Number(sel.value);
            if (!years.available.includes(year)) {
                missing.push(year);
                layers[k] = null;
                continue;
            }
            layers[k] = yearLayer(year, COLORS[k], k === 'a' ? 'swipeA' : 'swipeB').addTo(map);
        }
        document.getElementById('swipe-label-a').textContent = selA.value;
        document.getElementById('swipe-label-b').textContent = selB.value;
        setStatus(missing.length ? `Không có dữ liệu đường bờ năm ${missing.join(', ')} — ${UPDATING.toLowerCase()}.` : '');
    };
    const setActive = (on) => {
        active = on;
        toggle.setAttribute('aria-pressed', String(on));
        bar.hidden = !on;
        if (on) {
            load();
            clip();
        } else {
            for (const k of ['a', 'b']) if (layers[k]) map.removeLayer(layers[k]);
            layers.a = layers.b = null;
            setStatus('');
        }
    };

    toggle.addEventListener('click', () => setActive(!active));
    selA.addEventListener('change', () => active && load());
    selB.addEventListener('change', () => active && load());
    map.on('move zoom resize viewreset', () => active && clip());

    // Kéo thanh vuốt bằng chuột/cảm ứng/bút; phím mũi tên cho bàn phím.
    const move = (ev) => {
        const r = map.getContainer().getBoundingClientRect();
        ratio = Math.min(0.98, Math.max(0.02, (ev.clientX - r.left) / r.width));
        clip();
    };
    handle.addEventListener('pointerdown', (e) => {
        handle.setPointerCapture(e.pointerId);
        map.dragging.disable();
        handle.addEventListener('pointermove', move);
    });
    const release = (e) => {
        handle.removeEventListener('pointermove', move);
        if (handle.hasPointerCapture?.(e.pointerId)) handle.releasePointerCapture(e.pointerId);
        map.dragging.enable();
    };
    handle.addEventListener('pointerup', release);
    handle.addEventListener('pointercancel', release);
    handle.addEventListener('keydown', (e) => {
        const step = { ArrowLeft: -0.02, ArrowRight: 0.02 }[e.key];
        if (!step) return;
        e.preventDefault();
        ratio = Math.min(0.98, Math.max(0.02, ratio + step));
        clip();
    });
    return { setActive, clip };
}
