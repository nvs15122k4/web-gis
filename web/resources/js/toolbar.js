import L from 'leaflet';
import { formatArea, formatLength, pathLength, polygonArea } from './measure';

const ICONS = {
    in: '<path d="M12 5v14M5 12h14"/>',
    out: '<path d="M5 12h14"/>',
    locate: '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
    full: '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
    select: '<path d="m5 3 14 8-6 2-2 6L5 3Z"/>',
    distance: '<path d="M4 18 18 6"/><circle cx="4" cy="18" r="1.6"/><circle cx="18" cy="6" r="1.6"/>',
    area: '<path d="M4 8 12 4l8 6-3 9H7L4 8Z"/>',
    clear: '<path d="M6 6l12 12M18 6 6 18"/>',
};
const MEASURE_STYLE = { color: '#e94b35', weight: 2, dashArray: '5 4', fillColor: '#e94b35', fillOpacity: 0.08, interactive: false };

function button(group, key, label, onClick) {
    const b = L.DomUtil.create('button', 'tool-btn', group);
    b.type = 'button';
    b.dataset.tool = key;
    b.title = label;
    b.setAttribute('aria-label', label);
    b.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true">${ICONS[key]}</svg>`;
    L.DomEvent.on(b, 'click', (e) => {
        L.DomEvent.stop(e);
        onClick(b);
    });
    return b;
}

/**
 * Thanh công cụ nổi trên bản đồ: phóng to/thu nhỏ, định vị, toàn màn hình,
 * chọn đối tượng (mặc định), đo khoảng cách và diện tích.
 */
export function initToolbar(map, { home } = {}) {
    const wrap = document.getElementById('main-content');
    const readout = document.getElementById('measure-readout');
    let mode = 'select';
    let points = [];
    let shape = null;
    let preview = null;
    const buttons = {};

    const render = () => {
        if (shape) { shape.remove(); shape = null; }
        if (points.length) {
            const latlngs = points.concat(preview ? [preview] : []);
            shape = (mode === 'area' && latlngs.length >= 3 ? L.polygon(latlngs, MEASURE_STYLE) : L.polyline(latlngs, MEASURE_STYLE)).addTo(map);
        }
        if (!readout) return;
        const pts = points.concat(preview ? [preview] : []);
        const value = mode === 'area' ? formatArea(polygonArea(pts)) : formatLength(pathLength(pts));
        readout.textContent = points.length ? `${mode === 'area' ? 'Diện tích' : 'Chiều dài'}: ${value}` : 'Nhấp vào bản đồ để bắt đầu đo. Nhấp đúp để kết thúc.';
        readout.hidden = false;
    };

    const reset = () => {
        points = [];
        preview = null;
        if (shape) { shape.remove(); shape = null; }
    };

    const setMode = (next) => {
        mode = mode === next ? 'select' : next;
        reset();
        const measuring = mode !== 'select';
        wrap.classList.toggle('measuring', measuring);
        map.getContainer().style.cursor = measuring ? 'crosshair' : '';
        if (measuring) map.doubleClickZoom.disable(); else map.doubleClickZoom.enable();
        for (const key of ['select', 'distance', 'area']) buttons[key].setAttribute('aria-pressed', String(key === mode));
        if (measuring) render(); else if (readout) readout.hidden = true;
    };

    map.on('click', (e) => {
        if (mode === 'select') return;
        points.push([e.latlng.lat, e.latlng.lng]);
        render();
    });
    map.on('mousemove', (e) => {
        if (mode === 'select' || !points.length) return;
        preview = [e.latlng.lat, e.latlng.lng];
        render();
    });
    map.on('dblclick', () => {
        if (mode === 'select') return;
        preview = null;
        render();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mode !== 'select') setMode(mode);
    });

    const Toolbar = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            const bar = L.DomUtil.create('div', 'float-toolbar');
            bar.setAttribute('role', 'toolbar');
            bar.setAttribute('aria-label', 'Công cụ bản đồ');
            const nav = L.DomUtil.create('div', 'tool-group', bar);
            button(nav, 'in', 'Phóng to', () => map.zoomIn());
            button(nav, 'out', 'Thu nhỏ', () => map.zoomOut());
            button(nav, 'locate', 'Vị trí của tôi', () => map.locate({ setView: true, maxZoom: 16 }));
            if (document.fullscreenEnabled) {
                button(nav, 'full', 'Toàn màn hình', () => {
                    if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen();
                });
            }
            const tools = L.DomUtil.create('div', 'tool-group', bar);
            buttons.select = button(tools, 'select', 'Chọn đối tượng', () => setMode('select'));
            buttons.distance = button(tools, 'distance', 'Đo khoảng cách', () => setMode('distance'));
            buttons.area = button(tools, 'area', 'Đo diện tích', () => setMode('area'));
            button(tools, 'clear', 'Xoá phép đo', () => { reset(); render(); if (mode === 'select' && readout) readout.hidden = true; });
            buttons.select.setAttribute('aria-pressed', 'true');
            buttons.distance.setAttribute('aria-pressed', 'false');
            buttons.area.setAttribute('aria-pressed', 'false');
            L.DomEvent.disableClickPropagation(bar);
            return bar;
        },
    });
    new Toolbar().addTo(map);
    map.on('locationerror', () => {
        if (readout) { readout.textContent = 'Không xác định được vị trí của bạn.'; readout.hidden = false; }
    });
    return { setMode, home };
}
