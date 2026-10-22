"""Bước 7 — Đánh giá độ chính xác (Mục 2.4) trên bộ dữ liệu kiểm chứng số hoá thủ công.

Chuẩn bị: `python gt_kit.py` rồi số hoá trong QGIS (chọn lớp water_<năm> trong so_hoa_ground_truth.qgz) vào
data/ground_truth/ground_truth.gpkg, lớp water_<năm>: polygon MỌI mặt nước trong ô mẫu, trường `loai` = song | khac.
  - Precision/Recall/F1/IoU: so pixel nước (ảnh raster từng chỉ số) với vùng nước tham chiếu, trong các ô mẫu.
  - Sai số đường bờ: đường bờ tham chiếu = viền polygon `song` (bỏ phần trùng cạnh ô mẫu); đo chênh khoảng cách
    từ gốc transect tới đường bờ tự động và tới đường bờ tham chiếu (mean, mean |.|, RMSE).
Chỉ đánh giá ô mẫu đã số hoá (có ít nhất 1 polygon) — ô chưa vẽ bị bỏ qua, không bị tính là "toàn đất".
Đầu ra: output/evaluation.json. RMSE của chỉ số được chọn là U_classify cần điền vào config.U_CLASSIFY (Mục 2.5b).
"""
import json

import geopandas as gpd
import numpy as np
import pyogrio
import rioxarray
from rasterio.features import geometry_mask
from shapely.geometry import Point

import config as C

GPKG = C.DATA / "ground_truth" / "ground_truth.gpkg"


def pixel_metrics(pred, ref):
    tp = int((pred & ref).sum()); fp = int((pred & ~ref).sum()); fn = int((~pred & ref).sum())
    p = tp / (tp + fp) if tp + fp else 0.0
    r = tp / (tp + fn) if tp + fn else 0.0
    return {"precision": round(p, 4), "recall": round(r, 4),
            "f1": round(2 * p * r / (p + r), 4) if p + r else 0.0,
            "iou": round(tp / (tp + fp + fn), 4) if tp + fp + fn else 0.0, "pixels": int(pred.size)}


def reference_shoreline(water, windows):
    river = water[water["loai"].fillna("").str.strip().str.lower() == "song"]
    if river.empty:
        return None
    edge = windows.boundary.union_all().buffer(C.RESOLUTION / 2)  # mép ô mẫu không phải bờ thật
    return river.union_all().intersection(windows.union_all()).boundary.difference(edge)


def nearest_hit(transect, line):
    x = transect.intersection(line)
    pts = [q for q in getattr(x, "geoms", [x]) if q.geom_type == "Point"]
    o = Point(transect.coords[0])
    return min((o.distance(q) for q in pts), default=None)


def shoreline_error(year, ref, transects, zone):
    f = C.DATA / "shoreline" / f"{year}.geojson"
    if ref is None or ref.is_empty or not f.exists():
        return None
    auto = gpd.read_file(f).geometry.union_all()
    diffs = []
    for t in transects:
        if not t.intersects(zone):
            continue
        da, dr = nearest_hit(t, auto), nearest_hit(t, ref)
        if da is not None and dr is not None:
            diffs.append(da - dr)
    if not diffs:
        return None
    d = np.array(diffs)
    return {"n_transects": len(d), "mean_m": round(float(d.mean()), 2),
            "mean_abs_m": round(float(np.abs(d).mean()), 2), "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2)}


def main():
    if not GPKG.exists():
        raise SystemExit("Chưa có bộ số hoá — chạy `python gt_kit.py` trước.")
    windows = gpd.read_file(GPKG, layer="windows").to_crs(C.CRS)
    layers = [name for name, _ in pyogrio.list_layers(GPKG) if name.startswith("water_")]
    transects = list(gpd.read_file(C.DATA / "transects.geojson").geometry)
    report = {}
    for layer in sorted(layers):
        y = int(layer.split("_")[1])
        water = gpd.read_file(GPKG, layer=layer).to_crs(C.CRS)
        water = water[water.geometry.notna() & ~water.geometry.is_empty]
        if water.empty:
            continue
        done = windows[windows.intersects(water.union_all())]
        zone = done.union_all()
        report[y] = {"windows": sorted(done["name"].tolist())}
        for idx in C.WATER_INDICES:
            a = rioxarray.open_rasterio(C.DATA / "water" / f"{y}_{idx}.tif").isel(band=0)
            kw = {"out_shape": a.shape, "transform": a.rio.transform(), "invert": True}
            extent = geometry_mask(done.geometry, **kw)
            ref = geometry_mask(water.geometry.intersection(zone), **kw)
            valid = extent & (a.values != 255)
            report[y][idx] = pixel_metrics(a.values[valid] == 1, ref[valid])
        report[y]["shoreline_error"] = shoreline_error(y, reference_shoreline(water, done), transects, zone)
    if not report:
        raise SystemExit("Các lớp water_<năm> còn trống — số hoá trong QGIS trước.")
    years = list(report)
    best = max(C.WATER_INDICES, key=lambda i: np.mean([report[y][i]["f1"] for y in years]))
    rmse = {y: report[y]["shoreline_error"]["rmse_m"] for y in years if report[y]["shoreline_error"]}
    out = {"by_year": report, "best_index_by_mean_f1": best,
           "u_classify_suggestion": rmse,
           "note": f"Đổi config.DEFAULT_INDEX = '{best}' và điền config.U_CLASSIFY = {rmse}, rồi chạy lại "
                   "`run.py --skip-download` (nếu đổi chỉ số, chạy lại gt RMSE vì đường bờ tự động đổi theo)."}
    C.OUTPUT.mkdir(parents=True, exist_ok=True)
    (C.OUTPUT / "evaluation.json").write_text(json.dumps(out, indent=1, ensure_ascii=False))
    print(json.dumps(out, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
