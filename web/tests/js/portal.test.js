import { beforeAll, describe, expect, it } from 'vitest';
import { centroid, cellText, COLUMNS, inBounds, sortRows, toCsv } from '../../resources/js/attr-table';
import { parseCoords, matchTransects } from '../../resources/js/search';
import { statusRows } from '../../resources/js/admin-map';

const f = (id, code, lrr, lat = 10.5, lng = 105.3) => ({
    id,
    geometry: { type: 'LineString', coordinates: [[lng, lat], [lng + 0.001, lat + 0.001]] },
    properties: { transect_code: code, change_class: 'severe_erosion', epr_m_per_year: lrr, lrr_m_per_year: lrr, is_significant: true },
});

describe('bảng thuộc tính', () => {
    it('sắp xếp số, giá trị thiếu luôn xuống cuối', () => {
        const rows = [f(1, 'T1', 2), f(2, 'T2', null), f(3, 'T3', -5)];
        expect(sortRows(rows, 'lrr_m_per_year', 1, 'num').map((r) => r.id)).toEqual([3, 1, 2]);
        expect(sortRows(rows, 'lrr_m_per_year', -1, 'num').map((r) => r.id)).toEqual([1, 3, 2]);
    });
    it('mã transect sắp theo số tự nhiên', () => {
        expect(sortRows([f(1, 'T10', 0), f(2, 'T2', 0)], 'transect_code').map((r) => r.id)).toEqual([2, 1]);
    });
    it('thiếu dữ liệu hiện "Đang cập nhật", không bịa số', () => {
        expect(cellText(COLUMNS[3], { lrr_m_per_year: null })).toBe('Đang cập nhật');
        expect(cellText(COLUMNS[3], { lrr_m_per_year: 1.234 })).toBe('1.23');
    });
    it('lọc theo khung nhìn', () => {
        const rows = [f(1, 'A', 1, 10.5, 105.3), f(2, 'B', 1, 12, 108)];
        expect(inBounds(rows, [[10, 105], [11, 106]]).map((r) => r.id)).toEqual([1]);
        expect(centroid(null)).toBeNull();
    });
    it('CSV có BOM và escape dấu ngoặc kép', () => {
        const csv = toCsv([f(1, 'T"1', 1)]);
        expect(csv.startsWith('﻿"Mã transect"')).toBe(true);
        expect(csv).toContain('"T""1"');
    });
});

let clipPolygon;
beforeAll(async () => {
    window.WEBGIS = { geoserver: {}, years: { all: [], available: [] } };
    ({ clipPolygon } = await import('../../resources/js/swipe'));
});

describe('vuốt so sánh', () => {
    it('A giữ phần bên trái, B giữ phần bên phải đường vuốt', () => {
        expect(clipPolygon('a', 100)).toContain('100px');
        expect(clipPolygon('a', 100).startsWith('polygon(-99999px')).toBe(true);
        expect(clipPolygon('b', 100).startsWith('polygon(100px')).toBe(true);
    });
});

describe('tìm kiếm', () => {
    it('đọc toạ độ trong Việt Nam, bỏ qua ngoài phạm vi', () => {
        expect(parseCoords('10.54, 105.29')).toEqual([10.54, 105.29]);
        expect(parseCoords('10,54 105,29')).toEqual([10.54, 105.29]);
        expect(parseCoords('50, 10')).toBeNull();
        expect(parseCoords('T12')).toBeNull();
    });
    it('gợi ý theo mã, chỉ từ dữ liệu có thật', () => {
        const fs = [f(1, 'BM-001', 0), f(2, 'BM-002', 0)];
        expect(matchTransects(fs, 'bm-002').map((x) => x.id)).toEqual([2]);
        expect(matchTransects(fs, '')).toEqual([]);
    });
});

describe('quản trị', () => {
    it('trạng thái thiếu số liệu hiện "Đang cập nhật"', () => {
        const rows = statusRows({ data: { transects: 12, shorelines: null }, mysql: true, postgis: false, geoserver: null });
        expect(rows.find((r) => r[0] === 'Transect')[1]).toBe('12');
        expect(rows.find((r) => r[0] === 'Hotspot')[1]).toBe('Đang cập nhật');
        expect(rows.find((r) => r[0] === 'GeoServer')[1]).toBe('Chế độ GeoJSON');
    });
});
