"""Bước 7 — Đánh giá độ chính xác (Mục 2.4) trên bộ dữ liệu kiểm chứng số hoá thủ công.

Chuẩn bị: `python gt_kit.py` rồi số hoá trong QGIS (chọn lớp water_<năm> trong so_hoa_ground_truth.qgz) vào
data/ground_truth/ground_truth.gpkg, lớp water_<năm>: polygon MỌI mặt nước trong ô mẫu, trường `loai` = song | khac.
  - Precision/Recall/F1/IoU: so pixel nước (ảnh raster từng chỉ số) với vùng nước tham chiếu, trong các ô mẫu.
  - Sai số đường bờ: đường bờ tham chiếu = viền polygon `song` (bỏ phần trùng cạnh ô mẫu); đo chênh khoảng cách
    từ gốc transect tới đường bờ tự động và tới đường bờ tham chiếu (mean, mean |.|, RMSE).
Chỉ đánh giá ô mẫu đã số hoá (có ít nhất 1 polygon) — ô chưa vẽ bị bỏ qua, không bị tính là "toàn đất".
Đầu ra: output/evaluation.json. Chỉ số được chọn bằng `select_index` (F1, hoà thì RMSE); RMSE của chỉ số đó là U_classify cần điền vào config.U_CLASSIFY (Mục 2.5b).
"""
import json

import geopandas as gpd
import numpy as np
import pyogrio
import rioxarray
from rasterio.features import geometry_mask
from shapely.geometry import Point

import config as C
from s3_shoreline import extract

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


def select_index(report, tie_f1=C.SELECT_TIE_F1):
    """Chọn chỉ số nước theo đề cương (Mục 3.4): F1 trung bình cao nhất; nếu chênh F1 so với chỉ số đứng đầu
    không quá `tie_f1` thì coi là hoà và chọn chỉ số có RMSE đường bờ trung bình thấp nhất.
    Trả về (chỉ số, giải thích, f1 trung bình, rmse trung bình)."""
    years = list(report)
    f1 = {i: float(np.mean([report[y][i]["f1"] for y in years])) for i in C.WATER_INDICES}
    rmse = {}
    for i in C.WATER_INDICES:
        v = [report[y]["shoreline_error"][i]["rmse_m"] for y in years if report[y].get("shoreline_error", {}).get(i)]
        rmse[i] = float(np.mean(v)) if v else None
    top = max(f1.values())
    tied = [i for i in C.WATER_INDICES if top - f1[i] <= tie_f1 + 1e-9]
    if len(tied) == 1:
        return tied[0], f"F1 trung bình cao nhất ({f1[tied[0]]:.4f})", f1, rmse
    scored = [i for i in tied if rmse[i] is not None]
    if not scored:
        best = max(tied, key=f1.get)
        return best, "hoà F1 nhưng thiếu RMSE, lấy F1 cao nhất", f1, rmse
    best = min(scored, key=rmse.get)
    return best, f"hoà F1 (chênh ≤ {tie_f1}) giữa {tied}; chọn RMSE đường bờ thấp nhất ({rmse[best]:.2f} m)", f1, rmse


def shoreline_error(year, ref, transects, zone, index):
    """Sai số đường bờ của chỉ số `index`: vector hoá lại từ raster nước của chỉ số đó, không đọc file đường bờ mặc định."""
    if ref is None or ref.is_empty or not (C.DATA / "water" / f"{year}_{index}.tif").exists():
        return None
    geom = extract(year, index)
    if geom is None:
        return None
    auto = geom
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


def _err_stats(diffs):
    d = np.asarray(diffs, float)
    if d.size == 0:
        return None
    return {"n_transects": int(d.size), "mean_m": round(float(d.mean()), 2),
            "mean_abs_m": round(float(np.abs(d).mean()), 2), "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2)}


def kept_after_despike(year):
    """Mã transect có quan sát năm `year` CÒN LẠI sau despike của s5 (output/transects.json); None nếu chưa có file."""
    f = C.OUTPUT / "transects.json"
    if not f.exists():
        return None
    rows = json.loads(f.read_text(encoding="utf-8")).get("features", [])
    return {r["properties"]["transect_code"] for r in rows if (r["properties"].get("distances") or {}).get(str(year)) is not None}


def main_shoreline_error(auto, ref, transects, codes, zone, kept=None):
    """Sai số của đường bờ CHÍNH đang dùng (data/shoreline/<năm>.json, theo C.SHORELINE_ENGINE), không vector hoá lại.

    Trả về hai số liệu trên cùng ô mẫu:
      all   — mọi transect cắt được cả hai đường bờ;
      kept  — chỉ các (transect, năm) mà s5 giữ lại sau despike, tức đúng tập quan sát dùng cho NSM/EPR/LRR. Quan sát bị despike
              (lệch > C.DESPIKE_M so với trung vị chuỗi) không phải là sai số định vị ngẫu nhiên mà là cắt nhầm bờ khác nên bị loại
              khỏi thống kê; U_classify tính trên tập còn lại cho nhất quán, còn `all` và số bị loại vẫn được báo cáo.
    """
    if auto is None or ref is None or ref.is_empty:
        return None
    rows = []
    for t, code in zip(transects, codes):
        if not t.intersects(zone):
            continue
        da, dr = nearest_hit(t, auto), nearest_hit(t, ref)
        if da is not None and dr is not None:
            rows.append((code, da - dr))
    if not rows:
        return None
    out = {"all": _err_stats([d for _, d in rows])}
    if kept is not None:
        out["kept"] = _err_stats([d for c, d in rows if c in kept])
        out["n_excluded_by_despike"] = sum(1 for c, _ in rows if c not in kept)
    return out


def main():
    if not GPKG.exists():
        raise SystemExit("Chưa có bộ số hoá — chạy `python gt_kit.py` trước.")
    windows = gpd.read_file(GPKG, layer="windows").to_crs(C.CRS)
    layers = [name for name, _ in pyogrio.list_layers(GPKG) if name.startswith("water_")]
    tgdf = gpd.read_file(C.DATA / "transects.json")
    transects, tcodes = list(tgdf.geometry), list(tgdf["transect_code"])
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
        ref_line = reference_shoreline(water, done)
        # So sánh CHỈ SỐ: cùng một cách trích xuất (vector hoá lại từ raster từng chỉ số) để chọn chỉ số công bằng.
        report[y]["shoreline_error"] = {i: shoreline_error(y, ref_line, transects, zone, i) for i in C.WATER_INDICES}
        # SẢN PHẨM chính đang dùng: đo đúng file đường bờ đã giao (QGIS hoặc Python theo C.SHORELINE_ENGINE).
        main_file = C.DATA / "shoreline" / f"{y}.json"
        if main_file.exists():
            auto = gpd.read_file(main_file).geometry.iloc[0]
            report[y]["main_shoreline_error"] = main_shoreline_error(auto, ref_line, transects, tcodes, zone, kept_after_despike(y))
    if not report:
        raise SystemExit("Các lớp water_<năm> còn trống — số hoá trong QGIS trước.")
    best, reason, mean_f1, mean_rmse = select_index(report)
    from_main = {y: report[y]["main_shoreline_error"]["kept"]["rmse_m"] for y in report
                 if (report[y].get("main_shoreline_error") or {}).get("kept")}
    if from_main:
        rmse, basis = from_main, f"đường bờ chính ({C.SHORELINE_ENGINE}), các quan sát còn lại sau despike"
    else:
        rmse = {y: report[y]["shoreline_error"][best]["rmse_m"] for y in report if report[y]["shoreline_error"].get(best)}
        basis = f"đường bờ trích xuất lại từ raster chỉ số {best} (chưa có đường bờ chính để đo)"
    out = {"by_year": report, "mean_f1": {k: round(v, 4) for k, v in mean_f1.items()},
           "mean_rmse_m": {k: (round(v, 2) if v is not None else None) for k, v in mean_rmse.items()},
           "selected_index": best, "selection_reason": reason,
           "best_index_by_mean_f1": max(mean_f1, key=mean_f1.get), "u_classify_suggestion": rmse,
           "u_classify_basis": basis,
           "note": f"Chỉ số được chọn: {best} ({reason}). Cần config.DEFAULT_INDEX = '{best}' và config.U_CLASSIFY = {rmse} "
                   f"(cơ sở: {basis}). RMSE từng chỉ số (mean_rmse_m) dùng cách trích xuất Python thống nhất để so sánh chỉ số; "
                   f"sai số sản phẩm chính nằm ở by_year[năm].main_shoreline_error."}
    C.OUTPUT.mkdir(parents=True, exist_ok=True)
    (C.OUTPUT / "evaluation.json").write_text(json.dumps(out, indent=1, ensure_ascii=False))
    print(json.dumps(out, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
