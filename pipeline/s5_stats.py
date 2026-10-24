"""Bước 5 — Chỉ số biến động, độ bất định, phân loại theo tiêu chí chốt trước (Mục 2.5) và xuất GeoJSON cho web.

Quy ước dấu: khoảng cách đo từ baseline (trên đất) ra sông, nên giảm = bờ lùi vào đất (xói), tăng = bồi.
  NSM = d(năm cuối) − d(năm đầu);  EPR = NSM / Δt;  LRR = hệ số góc OLS trên toàn chuỗi, kèm SE(LRR), R².
  Có ý nghĩa thống kê khi |LRR| > 1,96·SE(LRR).
  accretion: có ý nghĩa & LRR > 0 · stable: không có ý nghĩa · erosion: có ý nghĩa & LRR < 0
  severe_erosion: erosion & LRR ≤ M − S (M, S tính trên tập erosion) & khoảng cách giảm liên tục ≥ 3 mốc năm liền nhau
  mild_erosion: erosion còn lại.
consecutive_erosion_periods = số giai đoạn (khoảng giữa 2 năm liền nhau) giảm liên tục dài nhất; 3 mốc ⇔ 2 giai đoạn.
Đầu ra: output/{baseline,shorelines,transects,hotspots}.json (EPSG:4326) + output/summary.json.
"""
import json

import geopandas as gpd
import numpy as np
from shapely.geometry import LineString

import config as C

MIN_POINTS = 3


def u_shoreline(year):
    u_cls = C.U_CLASSIFY.get(year, C.U_CLASSIFY_DEFAULT)
    return float(np.sqrt(C.U_PIXEL ** 2 + C.U_GEOREF ** 2 + u_cls ** 2))


def longest_decline(series):
    """series: [(năm, d)] đã sắp xếp. Đếm số giai đoạn giảm liên tục dài nhất, ngắt khi thiếu năm."""
    best = run = 0
    for (y0, d0), (y1, d1) in zip(series, series[1:]):
        run = run + 1 if (y1 == y0 + 1 and d1 < d0) else 0
        best = max(best, run)
    return best


def despike(distances):
    vals = [d for d in distances.values() if d is not None]
    if not vals:
        return distances, 0
    med = float(np.median(vals))
    clean = {y: (d if d is not None and abs(d - med) <= C.DESPIKE_M else None) for y, d in distances.items()}
    return clean, sum(1 for y in distances if distances[y] is not None and clean[y] is None)


def stats(distances):
    s = sorted((int(y), d) for y, d in distances.items() if d is not None)
    if len(s) < MIN_POINTS:
        return None
    t = np.array([y for y, _ in s], float)
    d = np.array([v for _, v in s], float)
    n = len(t)
    slope, icpt = np.polyfit(t, d, 1)
    resid = d - (slope * t + icpt)
    sxx = ((t - t.mean()) ** 2).sum()
    se = float(np.sqrt((resid ** 2).sum() / (n - 2) / sxx)) if n > 2 else None
    sst = ((d - d.mean()) ** 2).sum()
    r2 = float(1 - (resid ** 2).sum() / sst) if sst > 0 else 0.0
    nsm = d[-1] - d[0]
    dt = t[-1] - t[0]
    u_first, u_last = u_shoreline(int(t[0])), u_shoreline(int(t[-1]))
    return {
        "nsm_m": round(float(nsm), 2),
        "epr_m_per_year": round(float(nsm / dt), 3),
        "unc_epr": round(float(np.hypot(u_first, u_last) / dt), 3),
        "lrr_m_per_year": round(float(slope), 3),
        "se_lrr": round(se, 4),
        "unc_lrr": round(C.Z95 * se, 3),
        "r_squared": round(r2, 3),
        "is_significant": bool(abs(slope) > C.Z95 * se),
        "consecutive_erosion_periods": longest_decline(s),
        "u_shoreline_avg": round(float(np.mean([u_shoreline(int(y)) for y in t])), 2),
        "n_years": n,
        "first_year": int(t[0]),
        "last_year": int(t[-1]),
    }


def classify(rows):
    ero = [r["stats"]["lrr_m_per_year"] for r in rows if r["stats"] and r["stats"]["is_significant"]
           and r["stats"]["lrr_m_per_year"] < 0]
    M, S = (float(np.mean(ero)), float(np.std(ero, ddof=1))) if len(ero) > 1 else (None, None)
    need_periods = C.MIN_CONSECUTIVE_DECLINE - 1
    for r in rows:
        st = r["stats"]
        if not st:
            continue
        lrr = st["lrr_m_per_year"]
        if not st["is_significant"]:
            cls = "stable"
        elif lrr > 0:
            cls = "accretion"
        elif M is not None and lrr <= M - S and st["consecutive_erosion_periods"] >= need_periods:
            cls = "severe_erosion"
        else:
            cls = "mild_erosion"
        st["change_class"] = cls
        st["low_r2_caution"] = st["r_squared"] < C.R2_CAUTION
    return M, S


def hotspots(gdf):
    """Gom các transect severe_erosion liền kề (theo thứ tự dọc baseline) thành đoạn lùi bờ mạnh."""
    out, group = [], []
    rows = gdf.sort_values("chainage_m").to_dict("records")

    def flush():
        if not group:
            return
        pts = [r["geometry"].intersection(r["_shore"]) if r["_shore"] is not None else None for r in group]
        # đoạn hotspot vẽ dọc theo gốc transect dịch ra đường bờ gần nhất; fallback gốc transect
        coords = []
        for r, p in zip(group, pts):
            g = p if (p is not None and not p.is_empty) else None
            if g is not None and g.geom_type != "Point":
                g = min(g.geoms, key=lambda q: q.distance(LineString(r["geometry"].coords).interpolate(0)))
            coords.append((g.x, g.y) if g is not None else r["geometry"].coords[0])
        geom = LineString(coords) if len(coords) > 1 else LineString([coords[0], coords[0]]).buffer(C.TRANSECT_SPACING / 2)
        rates = [-r["stats"]["lrr_m_per_year"] for r in group]
        out.append({
            "severity": round(float(np.mean(rates)), 2),
            "period_start_year": min(r["stats"]["first_year"] for r in group),
            "period_end_year": max(r["stats"]["last_year"] for r in group),
            "notes": f"Lùi bờ mạnh {group[0]['transect_code']}–{group[-1]['transect_code']} "
                     f"({len(group)} transect, ~{len(group) * C.TRANSECT_SPACING} m)",
            "geometry": geom,
        })

    prev = None
    for r in rows:
        severe = r["stats"] and r["stats"].get("change_class") == "severe_erosion"
        if severe and prev is not None and r["chainage_m"] - prev <= C.TRANSECT_SPACING * 1.5:
            group.append(r)
        else:
            flush()
            group = [r] if severe else []
        prev = r["chainage_m"] if severe else None
    flush()
    return out


def main():
    C.OUTPUT.mkdir(parents=True, exist_ok=True)
    tr = gpd.read_file(C.DATA / "transects.json")
    shores = {}
    for y in C.YEARS:
        f = C.DATA / "shoreline" / f"{y}.json"
        if f.exists():
            shores[y] = gpd.read_file(f)
    latest = shores[max(shores)].geometry.iloc[0] if shores else None

    rows = []
    for rec in tr.to_dict("records"):
        raw = rec["distances"] if isinstance(rec["distances"], dict) else json.loads(rec["distances"])
        d, removed = despike(raw)
        st = stats(d)
        if st:
            st["despiked"] = removed
        rows.append({**rec, "distances": d, "stats": st, "_shore": latest})
    M, S = classify(rows)

    hs = hotspots(gpd.GeoDataFrame(rows, crs=C.CRS))
    feats = gpd.GeoDataFrame([{
        "transect_code": r["transect_code"], "chainage_m": r["chainage_m"],
        "distances": r["distances"], "stats": r["stats"], "geometry": r["geometry"],
    } for r in rows], crs=C.CRS).to_crs(4326)
    _write(feats, "transects")

    sl = gpd.GeoDataFrame(
        [{"year": y, "water_index": g["water_index"].iloc[0].upper(),  # web dùng nhãn NDWI/MNDWI/AWEI
          "source_image_date": _image_date(y), "geometry": g.geometry.iloc[0]} for y, g in shores.items()],
        crs=C.CRS).to_crs(4326)
    _write(sl, "shorelines")
    _write(gpd.read_file(C.DATA / "baseline.json").to_crs(4326)[["geometry"]], "baseline")
    _write(gpd.GeoDataFrame(hs, crs=C.CRS).to_crs(4326) if hs else None, "hotspots")

    counts = {}
    for r in rows:
        k = r["stats"]["change_class"] if r["stats"] else "no_data"
        counts[k] = counts.get(k, 0) + 1
    summary = {"transects": len(rows), "classes": counts,
               "despiked_observations": sum((r["stats"] or {}).get("despiked", 0) for r in rows), "M_lrr_erosion": M, "S_lrr_erosion": S,
               "severe_threshold_M_minus_S": (M - S) if M is not None else None,
               "hotspots": len(hs), "water_index": C.DEFAULT_INDEX,
               "u_shoreline_by_year": {y: round(u_shoreline(y), 2) for y in shores},
               "u_classify_is_placeholder": not C.U_CLASSIFY}
    (C.OUTPUT / "summary.json").write_text(json.dumps(summary, indent=1, ensure_ascii=False))
    print(json.dumps(summary, indent=1, ensure_ascii=False))


def _image_date(year):
    f = C.DATA / "composite" / "scenes.json"
    return json.loads(f.read_text()).get(str(year), {}).get("source_image_date") if f.exists() else None


def _write(gdf, name):
    path = C.OUTPUT / f"{name}.json"
    if gdf is None or gdf.empty:
        path.unlink(missing_ok=True)
        return
    # ghi thủ công để giữ nguyên object lồng nhau (distances, stats) mà driver GDAL sẽ làm phẳng thành chuỗi
    fc = {"type": "FeatureCollection", "features": [
        {"type": "Feature", "properties": {k: v for k, v in r.items() if k != "geometry"},
         "geometry": r["geometry"].__geo_interface__} for r in gdf.to_dict("records")]}
    path.write_text(json.dumps(fc, ensure_ascii=False, default=_np))


def _np(o):
    if isinstance(o, (np.integer,)):
        return int(o)
    if isinstance(o, (np.floating,)):
        return float(o)
    raise TypeError(type(o))


if __name__ == "__main__":
    main()
