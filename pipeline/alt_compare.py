"""So sánh bản data chính với hai bản đối chứng (data/alt/s2c1, data/alt/landsat). Đầu ra: output/alt_compare.json.

Ba tầng so sánh, mỗi tầng trả lời một câu hỏi khác nhau:
  1. water      — mặt nước MNDWI từng năm của bản đối chứng so với bản chính (P/R/F1/IoU, bản chính làm chuẩn so sánh;
                  KHÔNG phải ground truth) và độ lệch đường bờ dọc transect (dương = bản đối chứng nằm phía sông).
  2. trend      — dựng lại khoảng cách theo năm trên CÙNG bộ transect, chạy đúng despike/stats/classify của bước 5, rồi so
                  LRR và nhóm biến động với bản chính (Pearson, Spearman, thiên lệch, khớp dấu, ma trận nhóm, điểm nóng).
  3. groundtruth — NDWI/MNDWI/AWEI của chính bản đối chứng so với ground truth số hoá (cùng ô mẫu, cùng s7_evaluate).
Năm có < 3 cảnh (alt_data: low_confidence) được bỏ khỏi tầng trend ở biến thể "tin cậy" và giữ ở biến thể "đủ năm".
"""
import json

import geopandas as gpd
import numpy as np
import pyogrio
import rioxarray
from rasterio.features import geometry_mask
from rasterio.enums import Resampling
from scipy import stats as sps

import config as C
import s2_water
import s4_transects
import s5_stats
from s7_evaluate import GPKG, nearest_hit, pixel_metrics, reference_shoreline

ALT = C.DATA / "alt"
NAMES = ["s2c1", "landsat"]
INDEX = C.DEFAULT_INDEX


def main_water(y, idx=INDEX):
    return rioxarray.open_rasterio(C.DATA / "water" / f"{y}_{idx}.tif").isel(band=0)


def alt_water(name, y, idx=INDEX):
    f = ALT / name / "water" / f"{y}_{idx}.tif"
    return rioxarray.open_rasterio(f).isel(band=0) if f.exists() else None


def alt_line(name, y, idx=INDEX, engine="python"):
    """Đường bờ của bản đối chứng. engine="python": alt_data.py (contour dưới pixel, đủ 3 chỉ số); engine="qgis":
    qgis_interpret_alt.py (cùng chuỗi QGIS với bản chính, chỉ MNDWI)."""
    f = ALT / name / "qgis_shoreline" / f"{y}.json" if engine == "qgis" else ALT / name / "shoreline" / f"{y}_{idx}.json"
    return gpd.read_file(f).geometry.iloc[0] if f.exists() else None


def default_engine():
    """Cùng phương pháp tách đường bờ với bản chính: QGIS khi config.SHORELINE_ENGINE = "qgis" và đã chạy qgis_interpret_alt.py."""
    have = all((ALT / n / "qgis_shoreline").exists() for n in NAMES if (ALT / n / "scenes.json").exists())
    return "qgis" if C.SHORELINE_ENGINE == "qgis" and have else "python"


def main_line(y):
    f = C.DATA / "shoreline" / f"{y}.json"
    return gpd.read_file(f).geometry.iloc[0] if f.exists() else None


def offsets(a, b, transects):
    d = []
    for t in transects:
        x, z = nearest_hit(t, a), nearest_hit(t, b)
        if x is not None and z is not None:
            d.append(x - z)
    if not d:
        return None
    d = np.array(d)
    return {"n_transects": len(d), "mean_m": round(float(d.mean()), 2), "mean_abs_m": round(float(np.abs(d).mean()), 2),
            "median_m": round(float(np.median(d)), 2), "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2),
            "n_over_50m": int((np.abs(d) > 50).sum())}


def line_offsets(name, transects, engine):
    """Độ lệch đường bờ bản đối chứng so với bản chính dọc transect, từng năm, theo phương pháp tách bờ `engine`."""
    out = {}
    for y in C.YEARS:
        al, ml = alt_line(name, y, engine=engine), main_line(y)
        if al is not None and ml is not None:
            out[y] = offsets(al, ml, transects)
    return out


def water_level(name, transects, engine="python"):
    out = {}
    for y in C.YEARS:
        aw, mw = alt_water(name, y), main_water(y)
        if aw is None:
            continue
        aw = aw.rio.reproject_match(mw, resampling=Resampling.nearest)
        zone = s2_water.analysis_mask(mw.expand_dims("band"))
        valid = zone & (aw.values != 255) & (mw.values != 255)
        pm = pixel_metrics(aw.values[valid] == 1, mw.values[valid] == 1)
        p, q = aw.values[valid] == 1, mw.values[valid] == 1
        po = (p == q).mean()
        pe = p.mean() * q.mean() + (1 - p.mean()) * (1 - q.mean())
        pm["kappa"] = round(float((po - pe) / (1 - pe)), 4) if pe < 1 else None
        al, ml = alt_line(name, y, engine=engine), main_line(y)
        off = offsets(al, ml, transects) if al is not None and ml is not None else None
        out[y] = {"agreement_vs_main": pm, "shoreline_offset_vs_main": off}
    return out


def trend(name, transects_gdf, low_years, engine="python"):
    """LRR/nhóm biến động của bản đối chứng trên cùng transect, so với output/transects.json (bản chính)."""
    main = gpd.read_file(C.OUTPUT / "transects.json")
    mstats = {r.transect_code: (json.loads(r.stats) if isinstance(r.stats, str) else r.stats) for r in main.itertuples()}
    lines = {y: alt_line(name, y, engine=engine) for y in C.YEARS}
    res = {}
    for label, skip in (("all_years", set()), ("reliable_years", set(low_years))):
        rows = []
        for rec in transects_gdf.itertuples():
            dist = {y: s4_transects.distance(rec.geometry, lines[y]) for y in C.YEARS if y not in skip and lines[y] is not None}
            d, _ = s5_stats.despike(dist)
            st = s5_stats.stats(d)
            rows.append({"transect_code": rec.transect_code, "stats": st})
        s5_stats.classify(rows)
        pairs = [(r["stats"], mstats.get(r["transect_code"])) for r in rows]
        pairs = [(a, b) for a, b in pairs if a and b]
        if len(pairs) < 10:
            res[label] = {"n": len(pairs)}
            continue
        la = np.array([a["lrr_m_per_year"] for a, _ in pairs]); lm = np.array([b["lrr_m_per_year"] for _, b in pairs])
        ca = [a["change_class"] for a, _ in pairs]; cm = [b["change_class"] for _, b in pairs]
        classes = ["accretion", "stable", "mild_erosion", "severe_erosion"]
        conf = {m: {a: sum(1 for x, y in zip(cm, ca) if x == m and y == a) for a in classes} for m in classes}
        sev_m = [i for i, c in enumerate(cm) if c == "severe_erosion"]
        res[label] = {
            "years_used": sorted(set(C.YEARS) - skip if name else []),
            "n_transects": len(pairs),
            "pearson_lrr": round(float(sps.pearsonr(la, lm)[0]), 3),
            "spearman_lrr": round(float(sps.spearmanr(la, lm)[0]), 3),
            "mean_lrr_alt": round(float(la.mean()), 3), "mean_lrr_main": round(float(lm.mean()), 3),
            "bias_lrr_alt_minus_main": round(float((la - lm).mean()), 3),
            "rmse_lrr": round(float(np.sqrt(((la - lm) ** 2).mean())), 3),
            "sign_agreement": round(float((np.sign(la) == np.sign(lm)).mean()), 3),
            "class_agreement": round(float(np.mean([a == b for a, b in zip(ca, cm)])), 3),
            "classes_alt": {k: ca.count(k) for k in classes}, "classes_main": {k: cm.count(k) for k in classes},
            "confusion_main_rows_alt_cols": conf,
            "main_severe_transects": len(sev_m),
            "main_severe_lrr_in_alt": [round(float(la[i]), 2) for i in sev_m],
            "main_severe_class_in_alt": [ca[i] for i in sev_m],
            "alt_severe_transects": ca.count("severe_erosion"),
        }
    return res


def groundtruth(name, transects):
    windows = gpd.read_file(GPKG, layer="windows").to_crs(C.CRS)
    out = {}
    for layer in sorted(n for n, _ in pyogrio.list_layers(GPKG) if n.startswith("water_")):
        y = int(layer.split("_")[1])
        water = gpd.read_file(GPKG, layer=layer).to_crs(C.CRS)
        water = water[water.geometry.notna() & ~water.geometry.is_empty]
        if water.empty:
            continue
        done = windows[windows.intersects(water.union_all())]
        zone = done.union_all()
        ref_line = reference_shoreline(water, done)
        out[y] = {}
        for idx in C.WATER_INDICES:
            a = alt_water(name, y, idx)
            if a is None:
                continue
            kw = {"out_shape": a.shape, "transform": a.rio.transform(), "invert": True}
            extent = geometry_mask(done.geometry, **kw)
            ref = geometry_mask(water.geometry.intersection(zone), **kw)
            valid = extent & (a.values != 255)
            r = pixel_metrics(a.values[valid] == 1, ref[valid])
            al = alt_line(name, y, idx)
            if al is not None and ref_line is not None and not ref_line.is_empty:
                d = [nearest_hit(t, al) - nearest_hit(t, ref_line) for t in transects
                     if t.intersects(zone) and nearest_hit(t, al) is not None and nearest_hit(t, ref_line) is not None]
                if d:
                    d = np.array(d)
                    r["shoreline_error"] = {"n_transects": len(d), "mean_m": round(float(d.mean()), 2),
                                            "mean_abs_m": round(float(np.abs(d).mean()), 2),
                                            "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2)}
            out[y][idx] = r
    mean = {i: {"f1": round(float(np.mean([out[y][i]["f1"] for y in out if i in out[y]])), 4),
                "iou": round(float(np.mean([out[y][i]["iou"] for y in out if i in out[y]])), 4),
                "rmse_m": round(float(np.mean([out[y][i]["shoreline_error"]["rmse_m"] for y in out
                                               if i in out[y] and "shoreline_error" in out[y][i]])), 2)}
            for i in C.WATER_INDICES}
    return {"by_year": out, "mean": mean}


def export_transects(tgdf, low_years_by_name):
    """output/alt_transects.json: LRR/nhóm biến động từng transect của ba nguồn (để vẽ bản đồ so sánh trong QGIS).
    Sentinel-2 C1 bỏ các năm ít cảnh (tin cậy); Landsat dùng đủ 9 năm."""
    main = gpd.read_file(C.OUTPUT / "transects.json")
    ms = {r.transect_code: (json.loads(r.stats) if isinstance(r.stats, str) else r.stats) for r in main.itertuples()}
    cols = {"main_lrr": [], "main_class": []}
    rows_by = {}
    for name in NAMES:
        if not (ALT / name / "scenes.json").exists():
            continue
        skip = set(low_years_by_name.get(name, []))
        lines = {y: alt_line(name, y, engine=default_engine()) for y in C.YEARS}
        rows = []
        for rec in tgdf.itertuples():
            dist = {y: s4_transects.distance(rec.geometry, lines[y]) for y in C.YEARS if y not in skip and lines[y] is not None}
            d, _ = s5_stats.despike(dist)
            rows.append({"transect_code": rec.transect_code, "stats": s5_stats.stats(d)})
        s5_stats.classify(rows)
        rows_by[name] = {r["transect_code"]: r["stats"] for r in rows}
    feats = []
    for rec in tgdf.itertuples():
        m = ms.get(rec.transect_code) or {}
        p = {"transect_code": rec.transect_code, "chainage_m": rec.chainage_m,
             "main_lrr": m.get("lrr_m_per_year"), "main_class": m.get("change_class")}
        for name, st in rows_by.items():
            s = st.get(rec.transect_code) or {}
            p[f"{name}_lrr"] = s.get("lrr_m_per_year"); p[f"{name}_class"] = s.get("change_class")
            p[f"{name}_diff"] = (round(s["lrr_m_per_year"] - m["lrr_m_per_year"], 3)
                                 if s.get("lrr_m_per_year") is not None and m.get("lrr_m_per_year") is not None else None)
        feats.append({**p, "geometry": rec.geometry})
    gpd.GeoDataFrame(feats, crs=C.CRS).to_file(C.OUTPUT / "alt_transects.json", driver="GeoJSON")


def main():
    tgdf = gpd.read_file(C.DATA / "transects.json")
    transects = list(tgdf.geometry)
    report = {}
    for name in NAMES:
        if not (ALT / name / "scenes.json").exists():
            continue
        scenes = json.loads((ALT / name / "scenes.json").read_text())
        low = [int(y) for y, v in scenes.items() if v.get("low_confidence")]
        eng = default_engine()
        report[name] = {"scenes_used": {y: v["used"] for y, v in scenes.items()}, "low_confidence_years": low,
                        "engine": eng,   # phương pháp tách đường bờ dùng cho shoreline_offset và trend (cùng phương pháp với bản chính)
                        "water": water_level(name, transects, eng), "trend": trend(name, tgdf, low, eng),
                        "groundtruth": groundtruth(name, transects)}
        if eng != "python":   # độ nhạy theo phương pháp: cùng nguồn ảnh, đường bờ tách bằng code Python
            report[name]["sensitivity_python"] = {"offsets": line_offsets(name, transects, "python"),
                                                  "trend": trend(name, tgdf, low, "python")}
        print(name, "xong", "(engine:", eng + ")")
    export_transects(tgdf, {n: r["low_confidence_years"] for n, r in report.items() if n == "s2c1"})
    (C.OUTPUT / "alt_compare.json").write_text(json.dumps(report, indent=1, ensure_ascii=False))
    print(json.dumps({n: {"trend": {k: {m: v.get(m) for m in ("pearson_lrr", "class_agreement", "sign_agreement", "bias_lrr_alt_minus_main")}
                                    for k, v in r["trend"].items()},
                          "gt_mean": r["groundtruth"]["mean"]} for n, r in report.items()}, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
