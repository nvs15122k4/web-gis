import L from 'leaflet';

/**
 * Lớp chủ quyền: luôn hiện trên MỌI nền (kể cả OpenStreetMap/Esri vốn không ghi Hoàng Sa, Trường Sa là của Việt Nam).
 * Vị trí lấy theo toạ độ trung tâm hai quần đảo; hộp bao là phạm vi địa lý của từng quần đảo.
 */
export const SOVEREIGNTY_PANE = 'sovereignty';

export const ARCHIPELAGOS = [
    { name: 'Quần đảo Hoàng Sa', admin: 'Thành phố Đà Nẵng, Việt Nam', center: [16.5, 112.0], bounds: [[15.7, 111.1], [17.2, 113.0]] },
    { name: 'Quần đảo Trường Sa', admin: 'Tỉnh Khánh Hòa, Việt Nam', center: [9.0, 114.0], bounds: [[6.5, 111.4], [12.3, 117.6]] },
];

/** Khung nhìn toàn Việt Nam gồm cả hai quần đảo. */
export const VIETNAM_BOUNDS = [[5.5, 101.5], [23.8, 118.5]];

export function initSovereignty(map) {
    map.createPane(SOVEREIGNTY_PANE).style.zIndex = 450;
    const group = L.layerGroup();

    ARCHIPELAGOS.forEach((a) => {
        L.rectangle(a.bounds, {
            pane: SOVEREIGNTY_PANE, color: '#c1272d', weight: 1, dashArray: '4 4', fillColor: '#c1272d', fillOpacity: 0.06,
        }).bindTooltip(`<b>${a.name}</b><br>${a.admin}`, { sticky: true }).addTo(group);
        L.marker(a.center, {
            pane: SOVEREIGNTY_PANE,
            interactive: false,
            keyboard: false,
            icon: L.divIcon({
                className: 'sovereignty-label',
                html: `<span>${a.name}<small>${a.admin}</small></span>`,
                iconSize: [0, 0],
            }),
        }).addTo(group);
    });

    L.marker([14.0, 112.5], {
        pane: SOVEREIGNTY_PANE, interactive: false, keyboard: false,
        icon: L.divIcon({ className: 'sovereignty-sea', html: '<span>BIỂN ĐÔNG</span>', iconSize: [0, 0] }),
    }).addTo(group);

    group.addTo(map);
    return group;
}

/** Nút "Toàn quốc": thu về khung nhìn thấy cả Hoàng Sa, Trường Sa. */
export function addVietnamButton(map) {
    const Btn = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
            const a = L.DomUtil.create('a', 'leaflet-bar leaflet-control vn-fit');
            a.href = '#';
            a.title = 'Toàn quốc: xem cả Hoàng Sa, Trường Sa';
            a.setAttribute('role', 'button');
            a.textContent = 'VN';
            L.DomEvent.on(a, 'click', (e) => {
                L.DomEvent.preventDefault(e);
                map.fitBounds(VIETNAM_BOUNDS);
            });
            L.DomEvent.disableClickPropagation(a);
            return a;
        },
    });
    new Btn().addTo(map);
}
