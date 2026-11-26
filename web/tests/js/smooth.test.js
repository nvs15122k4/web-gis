import { describe, expect, it } from 'vitest';
import { chaikin, smoothCoords, smoothGeoJSON } from '../../resources/js/smooth';

const line = [[0, 0], [4, 0], [4, 4]];
const ring = [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]];

describe('chaikin', () => {
    it('đường hở giữ nguyên hai đầu mút và nhân đôi số đoạn', () => {
        const out = chaikin(line);
        expect(out[0]).toEqual([0, 0]);
        expect(out.at(-1)).toEqual([4, 4]);
        expect(out).toHaveLength(2 + 2 * 2);
        expect(out[1]).toEqual([1, 0]);
        expect(out[2]).toEqual([3, 0]);
    });

    it('vòng khép kín vẫn khép và có 2n điểm + điểm đóng', () => {
        const out = chaikin(ring, true);
        expect(out[0]).toEqual(out.at(-1));
        expect(out).toHaveLength(4 * 2 + 1);
    });

    it('dãy quá ngắn thì trả về bản sao nguyên vẹn', () => {
        const two = [[0, 0], [1, 1]];
        const out = chaikin(two);
        expect(out).toEqual(two);
        expect(out).not.toBe(two);
    });

    it('điểm mới nằm trong bao hộp của dữ liệu gốc', () => {
        for (const [x, y] of smoothCoords(ring, true, 2)) {
            expect(x).toBeGreaterThanOrEqual(0);
            expect(x).toBeLessThanOrEqual(4);
            expect(y).toBeGreaterThanOrEqual(0);
            expect(y).toBeLessThanOrEqual(4);
        }
    });
});

describe('smoothGeoJSON', () => {
    const fc = {
        type: 'FeatureCollection',
        features: [
            { type: 'Feature', properties: { role: 'commune' }, geometry: { type: 'Polygon', coordinates: [ring] } },
            { type: 'Feature', properties: { role: 'study' }, geometry: { type: 'MultiPolygon', coordinates: [[ring]] } },
            { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: line } },
            { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: [1, 2] } },
        ],
    };

    it('không sửa dữ liệu gốc, giữ thuộc tính, làm mượt mọi kiểu hình học', () => {
        const before = JSON.stringify(fc);
        const out = smoothGeoJSON(fc, 2);
        expect(JSON.stringify(fc)).toBe(before);
        expect(out.features.map((f) => f.properties.role)).toEqual(['commune', 'study', undefined, undefined]);
        expect(out.features[0].geometry.coordinates[0].length).toBeGreaterThan(ring.length);
        expect(out.features[1].geometry.coordinates[0][0].length).toBeGreaterThan(ring.length);
        expect(out.features[2].geometry.coordinates.length).toBeGreaterThan(line.length);
        expect(out.features[3].geometry.coordinates).toEqual([1, 2]);
    });

    it('iterations = 0 giữ nguyên hình học', () => {
        expect(smoothGeoJSON(fc, 0).features[0].geometry.coordinates).toEqual([ring]);
    });
});
