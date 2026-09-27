"""Bước 7 — Đánh giá độ chính xác (Mục 2.4) trên bộ dữ liệu kiểm chứng số hoá thủ công.

Chuẩn bị (QGIS, số hoá trên ảnh composite hoặc ảnh độ phân giải cao, ĐỘC LẬP với kết quả tự động):
  data/ground_truth/<năm>_water.geojson     Polygon vùng nước tham chiếu
  data/ground_truth/<năm>_extent.geojson    Polygon vùng đã số hoá (chỉ đánh giá pixel trong vùng này)
  data/ground_truth/<năm>_shoreline.geojson LineString đường bờ tham chiếu
Đầu ra: output/evaluation.json — Precision/Recall/F1/IoU từng chỉ số, sai số khoảng cách (mean, RMSE) dọc transect.
RMSE của chỉ số được chọn chính là U_classify cần điền vào config.U_CLASSIFY (Mục 2.5b).
"""
import json

import geopandas as gpd
import numpy as np
import rioxarray
from rasterio.features import geometry_mask
from shapely.geometry import Point

import config as C

GT = C.DATA / "ground_truth"


def pixel_metrics(pred, ref):
    tp = int((pred & ref).sum()); fp = int((pred & ~ref).sum()); fn = int((~pred & ref).sum())
    p = tp / (tp + fp) if tp + fp else 0.0
    r = tp / (tp + fn) if tp + fn else 0.0
    return {"precision": round(p, 4), "recall": round(r, 4),
            "f1": round(2 * p * r / (p + r), 4) if p + r else 0.0,
            "iou": round(tp / (tp + fp + fn), 4) if tp + fp + fn else 0.0, "pixels": int(pred.size)}


def shoreline_error(year, transects):
    auto_f = C.DATA / "shoreline" / f"{year}.geojson"
    ref_f = GT / f"{year}_shoreline.geojson"
    if not (auto_f.exists() and ref_f.exists()):
        return None
    auto = gpd.read_file(auto_f).to_crs(C.CRS).geometry.union_all()
    ref = gpd.read_file(ref_f).to_crs(C.CRS).geometry.union_all()
    diffs = []
    for t in transects:
        o = Point(t.coords[0])
        da, dr = [min((o.distance(q) for q in getattr(x, "geoms", [x]) if q.geom_type == "Point"), default=None)
                  for x in (t.intersection(auto), t.intersection(ref))]
        if da is not None and dr is not None:
            diffs.append(da - dr)
    if not diffs:
        return None
    d = np.array(diffs)
    return {"n_transects": len(d), "mean_m": round(float(d.mean()), 2),
            "mean_abs_m": round(float(np.abs(d).mean()), 2), "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2)}


def main():
    years = sorted({int(f.name[:4]) for f in GT.glob("*_water.geojson")}) if GT.exists() else []
    if not years:
        raise SystemExit(f"Chưa có dữ liệu kiểm chứng trong {GT} — xem docstring để chuẩn bị.")
    tr_f = C.DATA / "transects.geojson"
    transects = list(gpd.read_file(tr_f).geometry) if tr_f.exists() else []
    report = {}
    for y in years:
        report[y] = {}
        for idx in C.WATER_INDICES:
            a = rioxarray.open_rasterio(C.DATA / "water" / f"{y}_{idx}.tif").isel(band=0)
            shp = a.shape
            tf = a.rio.transform()
            extent = geometry_mask(gpd.read_file(GT / f"{y}_extent.geojson").to_crs(C.CRS).geometry,
                                   out_shape=shp, transform=tf, invert=True)
            ref = geometry_mask(gpd.read_file(GT / f"{y}_water.geojson").to_crs(C.CRS).geometry,
                                out_shape=shp, transform=tf, invert=True)
            valid = extent & (a.values != 255)
            report[y][idx] = pixel_metrics(a.values[valid] == 1, ref[valid])
        report[y]["shoreline_error"] = shoreline_error(y, transects)
    best = max(C.WATER_INDICES, key=lambda i: np.mean([report[y][i]["f1"] for y in years]))
    out = {"by_year": report, "best_index_by_mean_f1": best}
    C.OUTPUT.mkdir(parents=True, exist_ok=True)
    (C.OUTPUT / "evaluation.json").write_text(json.dumps(out, indent=1))
    print(json.dumps(out, indent=1))


if __name__ == "__main__":
    main()
