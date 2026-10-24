"""Bước 4 — Baseline, transect và khoảng cách baseline→đường bờ theo năm (Mục 2.3, phương pháp DSAS).

Baseline: lấy đoạn đường bờ dài nhất của năm đầu tiên có dữ liệu, làm trơn mạnh rồi dịch vào phía đất
BASELINE_OFFSET m (phía đất xác định bằng mặt nạ nước). Transect: vuông góc baseline, cách đều TRANSECT_SPACING m,
hướng ra sông. Khoảng cách mỗi năm = từ gốc transect tới giao điểm GẦN NHẤT với đường bờ năm đó
(tuỳ chọn "closest intersection" của DSAS); không giao thì để trống (không nội suy).
Đầu ra: data/transects.json (EPSG:32648), thuộc tính distances = {năm: m | null}.
"""
import json

import geopandas as gpd
import numpy as np
import rioxarray
from shapely.geometry import LineString, Point

import config as C

BASELINE_SMOOTH = 150   # m — dung sai đơn giản hoá trước khi dịch baseline
CHAIKIN_ITER = 4
TANGENT_WINDOW = 100    # m — ước lượng hướng baseline trên cửa sổ ±100 m để transect không bị xoắn


def load_shorelines():
    out = {}
    for y in C.YEARS:
        f = C.DATA / "shoreline" / f"{y}.json"
        if f.exists():
            out[y] = gpd.read_file(f).geometry.iloc[0]
    return out


def chaikin(line, n):
    pts = np.asarray(line.coords)
    for _ in range(n):
        q = 0.75 * pts[:-1] + 0.25 * pts[1:]
        r = 0.25 * pts[:-1] + 0.75 * pts[1:]
        pts = np.vstack([pts[:1], np.column_stack([q, r]).reshape(-1, 2), pts[-1:]])
    return LineString(pts)


def is_water(pt, water, tr):
    c, r = ~tr * (pt.x, pt.y)
    r, c = int(r), int(c)
    return 0 <= r < water.shape[0] and 0 <= c < water.shape[1] and water[r, c] == 1


def build_baseline(ref, water, tr):
    ref = max(getattr(ref, "geoms", [ref]), key=lambda g: g.length)
    smooth = chaikin(ref.simplify(BASELINE_SMOOTH), CHAIKIN_ITER)
    candidates = [smooth.offset_curve(d, join_style="round") for d in (C.BASELINE_OFFSET, -C.BASELINE_OFFSET)]
    candidates = [max(getattr(c, "geoms", [c]), key=lambda g: g.length) for c in candidates]

    def land_share(c):
        pts = [c.interpolate(f, normalized=True) for f in np.linspace(0.05, 0.95, 40)]
        return np.mean([not is_water(p, water, tr) for p in pts])

    return max(candidates, key=land_share)


def build_transects(baseline, water, tr):
    out = []
    L = baseline.length
    for i, s in enumerate(np.arange(C.TRANSECT_SPACING / 2, L, C.TRANSECT_SPACING)):
        p = baseline.interpolate(s)
        a = baseline.interpolate(max(s - TANGENT_WINDOW, 0))
        b = baseline.interpolate(min(s + TANGENT_WINDOW, L))
        dx, dy = b.x - a.x, b.y - a.y
        n = np.hypot(dx, dy)
        nx, ny = -dy / n, dx / n
        # chọn phía pháp tuyến hướng ra sông: điểm cuối nào rơi vào nước
        ends = [(p.x + k * nx * C.TRANSECT_LENGTH, p.y + k * ny * C.TRANSECT_LENGTH) for k in (1, -1)]
        end = max(ends, key=lambda e: sum(is_water(Point(p.x + (e[0] - p.x) * f, p.y + (e[1] - p.y) * f), water, tr)
                                           for f in np.linspace(0.3, 1, 15)))
        out.append((f"T{i + 1:04d}", LineString([(p.x, p.y), end]), round(s, 1)))
    return out


def drop_crossing(transects):
    """Chỗ baseline cong gắt (mũi cù lao) transect xoè cắt nhau -> lần lượt bỏ transect cắt nhiều nhất (như DSAS)."""
    keep = list(transects)
    while True:
        cross = [sum(a[1].crosses(b[1]) for b in keep if b is not a) for a in keep]
        worst = int(np.argmax(cross)) if keep else 0
        if not keep or cross[worst] == 0:
            return keep
        keep.pop(worst)


def distance(transect, shoreline):
    if shoreline is None:
        return None
    x = transect.intersection(shoreline)
    if x.is_empty:
        return None
    pts = [g for g in getattr(x, "geoms", [x]) if g.geom_type == "Point"]
    if not pts:
        return None
    origin = Point(transect.coords[0])
    return round(min(origin.distance(q) for q in pts), 2)


def main():
    shores = load_shorelines()
    if not shores:
        raise SystemExit("Chưa có đường bờ — chạy s3_shoreline.py trước")
    first = min(shores)
    wm = rioxarray.open_rasterio(C.DATA / "water" / f"{first}_{C.DEFAULT_INDEX}.tif").isel(band=0)
    water, tr = wm.values, wm.rio.transform()
    baseline = build_baseline(shores[first], water, tr)
    built = build_transects(baseline, water, tr)
    transects = drop_crossing(built)

    rows = []
    for code, line, chain in transects:
        d = {str(y): distance(line, shores.get(y)) for y in C.YEARS}
        rows.append({"transect_code": code, "chainage_m": chain, "distances": json.dumps(d), "geometry": line})
    gpd.GeoDataFrame(rows, crs=C.CRS).to_file(C.DATA / "transects.json", driver="GeoJSON")
    gpd.GeoDataFrame({"ref_year": [first]}, geometry=[baseline], crs=C.CRS).to_file(C.DATA / "baseline.json", driver="GeoJSON")
    hit = sum(any(v is not None for v in json.loads(r["distances"]).values()) for r in rows)
    print(f"Baseline {baseline.length / 1000:.2f} km (từ đường bờ {first}); {len(rows)} transect "
          f"(bỏ {len(built) - len(rows)} transect cắt nhau), {hit} cắt được đường bờ")


if __name__ == "__main__":
    main()
