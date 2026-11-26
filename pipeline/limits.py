"""Năm phân tích bổ sung cho mục "Hạn chế của quy trình" (trang /gioi-thieu). Mỗi phân tích ghi một file output/limits_*.json.

NGUYÊN TẮC: chỉ báo cáo kết quả đo được, kể cả khi bất lợi; không đổi config.py và không chọn tham số cho đẹp. Mọi ngưỡng/quy ước
mới dưới đây là QUY ƯỚC CỦA ĐỒ ÁN (không gán cho tài liệu nào) và đặt TRƯỚC khi chạy:
  - KTC LRR có trọng số: cộng bình phương SE phần dư và SE do sai số từng đường bờ (thận trọng, có thể tính đôi nhiễu).
  - Độ lệch ngưỡng δ khi ước lượng U_classify = độ lệch chuẩn (n−1) của ngưỡng Otsu MNDWI giữa các năm; thử 0,5δ/1δ/2δ, lấy 1δ làm mốc.
  - Ngưỡng cố định thay Otsu: (a) 0,0 (mặc định kinh điển của MNDWI, Xu 2006), (b) trung vị ngưỡng Otsu 9 năm. Báo cáo cả hai.
  - Lưới ngưỡng quét khi điều tra độ lệch với sản phẩm tham chiếu: −0,20 … +0,40 bước 0,05 cộng ngưỡng Otsu.
  - Cửa sổ thời gian thử mùa/mực nước: 6 cửa sổ hai tháng trong năm.
  - "Kết luận đổi" ở phân tích 4 = nhóm biến động (change_class) khác nhau giữa hai cách chạy.

Chạy: python limits.py uclass|wls|ref|fixed|select|season|all   (thứ tự phụ thuộc: uclass trước wls; season cần mạng)
"""
import json
import sys

import geopandas as gpd
import numpy as np
import pyogrio
import rioxarray
from rasterio.enums import Resampling
from rasterio.features import geometry_mask
from scipy import stats as sps

import config as C
import s2_water
import s3_shoreline
import s4_transects
import s5_stats
from s7_evaluate import GPKG, nearest_hit, pixel_metrics, reference_shoreline, select_index

OUT = C.OUTPUT
LIM = C.DATA / "limits"
INDEX = C.DEFAULT_INDEX
CLASSES = ["accretion", "stable", "mild_erosion", "severe_erosion"]
THRESHOLD_GRID = [round(x, 2) for x in np.arange(-0.20, 0.4001, 0.05)]
DELTA_MULTIPLES = (0.5, 1.0, 2.0)
SEASON_WINDOWS = [("01-01", "02-28"), ("03-01", "04-30"), ("05-01", "06-30"), ("07-01", "08-31"), ("09-01", "10-31"), ("11-01", "12-31")]


# ---------------------------------------------------------------------------------------------------------------
# Hàm thuần (có test)
# ---------------------------------------------------------------------------------------------------------------
def u_total(year, u_classify):
    """U_shoreline 1σ của một năm với U_classify cho trước (cùng công thức s5_stats.u_shoreline)."""
    return float(np.sqrt(C.U_PIXEL ** 2 + C.U_GEOREF ** 2 + u_classify[year] ** 2))


def classify_one(lrr, significant, consecutive_periods):
    """Cùng luật với s5_stats.classify nhưng cho một transect, để áp lên LRR/ý nghĩa tính theo cách khác."""
    if not significant:
        return "stable"
    if lrr > 0:
        return "accretion"
    if lrr <= C.SEVERE_LRR and consecutive_periods >= C.MIN_CONSECUTIVE_DECLINE - 1:
        return "severe_erosion"
    return "mild_erosion"


def weighted_lrr(years, d, u):
    """Hồi quy tuyến tính có trọng số w = 1/U² (U = U_shoreline 1σ từng năm). Trả về hệ số góc và ba SE:
      se_meas  — SE chỉ do sai số đường bờ đã biết, 1/sqrt(Σw(t−t̄w)²)
      se_resid — SE theo độ phân tán phần dư có trọng số (χ²/(n−2)), như OLS nhưng có trọng số
      se_comb  — sqrt(se_resid² + se_meas²): "KTC LRR cộng sai số từng đường bờ" (quy ước của đồ án)
    Cần n ≥ 3."""
    t = np.asarray(years, float); d = np.asarray(d, float); w = 1.0 / np.asarray(u, float) ** 2
    n = len(t)
    tw, dw = (w * t).sum() / w.sum(), (w * d).sum() / w.sum()
    sxx = (w * (t - tw) ** 2).sum()
    slope = (w * (t - tw) * (d - dw)).sum() / sxx
    icpt = dw - slope * tw
    chi2 = (w * (d - (slope * t + icpt)) ** 2).sum()
    se_meas = 1.0 / np.sqrt(sxx)
    se_resid = np.sqrt(chi2 / (n - 2) / sxx)
    return {"slope": float(slope), "se_meas": float(se_meas), "se_resid": float(se_resid),
            "se_comb": float(np.hypot(se_resid, se_meas)), "chi2_red": float(chi2 / (n - 2))}


def ols_meas_se(years, u):
    """SE của hệ số góc OLS do sai số đường bờ độc lập từng năm: sqrt(Σ((t−t̄)/Sxx)² U²)."""
    t = np.asarray(years, float); u = np.asarray(u, float)
    sxx = ((t - t.mean()) ** 2).sum()
    return float(np.sqrt((((t - t.mean()) / sxx) ** 2 * u ** 2).sum()))


def offset_stats(d):
    """Thống kê độ lệch (m) giữa hai đường bờ trên các transect; d = chênh khoảng cách (dương = đường thứ nhất phía sông)."""
    d = np.asarray(d, float)
    if d.size == 0:
        return None
    big = np.abs(d) > C.DESPIKE_M
    ss = float((d ** 2).sum())
    return {"n_transects": int(d.size), "mean_m": round(float(d.mean()), 2), "median_m": round(float(np.median(d)), 2),
            "mad_m": round(float(np.median(np.abs(d - np.median(d)))), 2),
            "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2), "n_over_50m": int(big.sum()),
            "rmse_excl_over_50m": round(float(np.sqrt((d[~big] ** 2).mean())), 2) if (~big).any() else None,
            "ss_share_over_50m": round(float((d[big] ** 2).sum() / ss), 3) if ss > 0 else 0.0}


def clusters(chainages, gap=100.0):
    """Gom các chainage (m) cách nhau ≤ gap thành đoạn [(đầu, cuối, số transect)]."""
    out = []
    for c in sorted(chainages):
        if out and c - out[-1][1] <= gap:
            out[-1][1] = c; out[-1][2] += 1
        else:
            out.append([c, c, 1])
    return [tuple(x) for x in out]


def zero_crossing(xs, ys):
    """Nội suy tuyến tính x tại đó ys đổi dấu lần đầu; None nếu không đổi dấu trong khoảng đã quét."""
    for (x0, y0), (x1, y1) in zip(zip(xs, ys), list(zip(xs, ys))[1:]):
        if y0 is None or y1 is None:
            continue
        if y0 == 0:
            return float(x0)
        if y0 * y1 < 0:
            return float(x0 + (x1 - x0) * (-y0) / (y1 - y0))
    return None


def compare_trends(a, b, min_n=10):
    """a, b: {mã transect: stats (có change_class)}. So b với a (a = bản chính). Trả về chỉ số tổng hợp và danh sách transect đổi nhóm."""
    codes = [c for c in a if a[c] and b.get(c)]
    if len(codes) < min_n:
        return {"n_transects": len(codes)}
    la = np.array([a[c]["lrr_m_per_year"] for c in codes]); lb = np.array([b[c]["lrr_m_per_year"] for c in codes])
    ca = [a[c]["change_class"] for c in codes]; cb = [b[c]["change_class"] for c in codes]
    changed = [{"transect": c, "lrr_a": a[c]["lrr_m_per_year"], "lrr_b": b[c]["lrr_m_per_year"],
                "class_a": a[c]["change_class"], "class_b": b[c]["change_class"],
                "significant_a": a[c]["is_significant"], "significant_b": b[c]["is_significant"]}
               for c in codes if a[c]["change_class"] != b[c]["change_class"]]
    return {
        "n_transects": len(codes),
        "pearson_lrr": round(float(sps.pearsonr(la, lb)[0]), 3), "spearman_lrr": round(float(sps.spearmanr(la, lb)[0]), 3),
        "mean_lrr_a": round(float(la.mean()), 3), "mean_lrr_b": round(float(lb.mean()), 3),
        "bias_lrr_b_minus_a": round(float((lb - la).mean()), 3), "rmse_lrr": round(float(np.sqrt(((lb - la) ** 2).mean())), 3),
        "max_abs_lrr_diff": round(float(np.abs(lb - la).max()), 3),
        "sign_agreement": round(float((np.sign(la) == np.sign(lb)).mean()), 3),
        "class_agreement": round(float(np.mean([x == y for x, y in zip(ca, cb)])), 3),
        "n_class_changed": len(changed),
        "n_significance_changed": sum(1 for c in codes if a[c]["is_significant"] != b[c]["is_significant"]),
        "classes_a": {k: ca.count(k) for k in CLASSES}, "classes_b": {k: cb.count(k) for k in CLASSES},
        "confusion_a_rows_b_cols": {m: {n: sum(1 for x, y in zip(ca, cb) if x == m and y == n) for n in CLASSES} for m in CLASSES},
        "severe_a": ca.count("severe_erosion"), "severe_b": cb.count("severe_erosion"),
        "severe_a_kept_severe_in_b": sum(1 for x, y in zip(ca, cb) if x == "severe_erosion" and y == "severe_erosion"),
        "changed_transects": changed,
    }


def sweep_tie(report, ties):
    """Chỉ số được chọn theo s7_evaluate.select_index khi đổi ngưỡng hoà SELECT_TIE_F1."""
    return {str(t): select_index(report, t)[0] for t in ties}


def leave_out(report, drop):
    """Chọn chỉ số khi bỏ các năm trong `drop` (ngưỡng hoà giữ nguyên theo config)."""
    sub = {y: v for y, v in report.items() if y not in drop}
    best, reason, f1, rmse = select_index(sub)
    return {"selected": best, "reason": reason, "mean_f1": {k: round(v, 4) for k, v in f1.items()},
            "mean_rmse_m": {k: (round(v, 2) if v is not None else None) for k, v in rmse.items()}}


# ---------------------------------------------------------------------------------------------------------------
# Hạ tầng dùng lại: đường bờ tại ngưỡng bất kỳ, chuỗi khoảng cách, thống kê theo đúng luật bước 5
# ---------------------------------------------------------------------------------------------------------------
_comp_cache = {}


def composite(year):
    if year not in _comp_cache:
        _comp_cache[year] = rioxarray.open_rasterio(C.DATA / "composite" / f"{year}.tif")
    return _comp_cache[year]


def otsu(year, index=INDEX):
    return json.loads((C.DATA / "water" / "thresholds.json").read_text())[str(year)][index]


def raw_water(a, index, t):
    idx = s2_water.indices(a)[index]
    raw = np.full(idx.shape, 255, np.uint8)
    fin = np.isfinite(idx)
    raw[fin] = (idx[fin] > t).astype(np.uint8)
    return raw, idx


def shoreline_at(a, t, index=INDEX):
    """Đường bờ (MultiLineString, EPSG:32648) khi phân loại nước bằng chỉ số > t, đúng thủ tục bước 3 (s3_shoreline.vectorize)."""
    raw, idx = raw_water(a, index, t)
    return s3_shoreline.vectorize(raw, a.rio.transform(), idx - t)


def trend(lines, tgdf, years=None):
    """Chạy đúng despike → stats → classify của bước 5 trên cùng bộ transect. Trả về {mã: stats}."""
    years = years or C.YEARS
    rows = []
    for rec in tgdf.itertuples():
        dist = {y: s4_transects.distance(rec.geometry, lines.get(y)) for y in years if lines.get(y) is not None}
        d, _ = s5_stats.despike(dist)
        rows.append({"transect_code": rec.transect_code, "stats": s5_stats.stats(d), "distances": d})
    s5_stats.classify(rows)
    return {r["transect_code"]: r["stats"] for r in rows}


def main_stats():
    g = gpd.read_file(OUT / "transects.json")
    return {r.transect_code: (json.loads(r.stats) if isinstance(r.stats, str) else r.stats) for r in g.itertuples()}, \
           {r.transect_code: (json.loads(r.distances) if isinstance(r.distances, str) else r.distances) for r in g.itertuples()}


def main_line(y):
    return gpd.read_file(C.DATA / "shoreline" / f"{y}.json").geometry.iloc[0]


def diffs(a, b, tgdf, only=None):
    """[(mã, chainage, d_a − d_b)] trên transect cắt cả hai đường bờ (nếu `only`: chỉ transect cắt vùng `only`)."""
    out = []
    for rec in tgdf.itertuples():
        if only is not None and not rec.geometry.intersects(only):
            continue
        x, z = nearest_hit(rec.geometry, a), nearest_hit(rec.geometry, b)
        if x is not None and z is not None:
            out.append((rec.transect_code, float(rec.chainage_m), x - z))
    return out


def gt_context(year):
    """(vùng ô mẫu đã số hoá, đường bờ tham chiếu số hoá) của một năm có ground truth; None nếu không có."""
    layers = {n for n, _ in pyogrio.list_layers(GPKG)}
    if f"water_{year}" not in layers:
        return None
    windows = gpd.read_file(GPKG, layer="windows").to_crs(C.CRS)
    water = gpd.read_file(GPKG, layer=f"water_{year}").to_crs(C.CRS)
    water = water[water.geometry.notna() & ~water.geometry.is_empty]
    if water.empty:
        return None
    done = windows[windows.intersects(water.union_all())]
    return done.union_all(), reference_shoreline(water, done)


def ci_summary(v):
    return {"mean": round(float(np.mean(v)), 3), "sd": round(float(np.std(v, ddof=1)), 3) if len(v) > 1 else None,
            "min": round(float(np.min(v)), 3), "max": round(float(np.max(v)), 3)}


def dump(name, obj):
    (OUT / f"limits_{name}.json").write_text(json.dumps(obj, indent=1, ensure_ascii=False, default=_np))


def _np(o):
    if isinstance(o, np.integer):
        return int(o)
    if isinstance(o, np.floating):
        return float(o)
    if isinstance(o, np.bool_):
        return bool(o)
    raise TypeError(type(o))


# ---------------------------------------------------------------------------------------------------------------
# (3) U_classify riêng từng năm bằng độ nhạy theo ngưỡng
# ---------------------------------------------------------------------------------------------------------------
def run_uclassify():
    """Phương pháp: với mỗi năm y, dịch ngưỡng Otsu MNDWI ±δ, vector hoá lại, đo độ dịch đường bờ trên từng transect
    (khoảng cách tới giao điểm gần nhất, như bước 4); U_est(y) = căn bình phương trung bình của độ dịch qua cả hai chiều và mọi transect.
    δ = độ lệch chuẩn ngưỡng Otsu giữa các năm (quy ước của đồ án). Hiệu chuẩn: ở 3 năm có ground truth đem U_est so với RMSE đo
    được (config.U_CLASSIFY); hệ số k = trung bình(RMSE đo / U_est). Với 6 năm còn lại: U = k·U_est. Đây là ƯỚC LƯỢNG, không phải phép đo."""
    tgdf = gpd.read_file(C.DATA / "transects.json")
    thr = {y: otsu(y) for y in C.YEARS}
    sd = float(np.std(list(thr.values()), ddof=1))
    est, est_all, n_jump = {}, {}, {}
    for m in DELTA_MULTIPLES:
        est_all[m], n_jump[m] = {}, {}
        delta = m * sd
        est[m] = {}
        for y in C.YEARS:
            a = composite(y)
            base = shoreline_at(a, thr[y])
            sq = []
            for sgn in (-1, 1):
                alt = shoreline_at(a, thr[y] + sgn * delta)
                for rec in tgdf.itertuples():
                    d0, d1 = s4_transects.distance(rec.geometry, base), s4_transects.distance(rec.geometry, alt)
                    if d0 is not None and d1 is not None:
                        sq.append(d1 - d0)
            sq = np.array(sq)
            keep = np.abs(sq) <= C.DESPIKE_M   # cùng quy ước khử đột biến 50 m của bước 5: bỏ chỗ đường bờ nhảy sang bờ/kênh khác
            est[m][y] = float(np.sqrt(np.mean(sq[keep] ** 2)))
            est_all[m][y] = float(np.sqrt(np.mean(sq ** 2)))
            n_jump[m][y] = int((~keep).sum())
    measured = dict(C.U_CLASSIFY)
    gt_years = sorted(measured)
    cal = {}
    for m in DELTA_MULTIPLES:
        ratios = [measured[y] / est[m][y] for y in gt_years if est[m][y]]
        k = float(np.mean(ratios))
        cal[m] = {"k_mean": round(k, 3), "k_by_gt_year": {y: round(measured[y] / est[m][y], 3) for y in gt_years},
                  "k_cv": round(float(np.std(ratios, ddof=1) / k), 3) if len(ratios) > 1 else None}
    k1 = cal[1.0]["k_mean"]
    hybrid = {y: (measured[y] if y in measured else round(k1 * est[1.0][y], 2)) for y in C.YEARS}
    res = {
        "method": ("U_est(y) = RMS độ dịch đường bờ trên transect khi ngưỡng Otsu MNDWI dịch ±δ; δ = SD ngưỡng Otsu giữa các năm; "
                   "hiệu chuẩn bằng hệ số k = TB(RMSE đo / U_est) ở 3 năm có ground truth. Mọi lựa chọn (δ, k) là quy ước của đồ án."),
        "otsu_thresholds": thr, "delta_sd_threshold": round(sd, 4),
        "u_est_raw_m": {str(m): {y: round(v, 2) for y, v in e.items()} for m, e in est.items()},
        "u_est_raw_m_note": "đã bỏ độ dịch > 50 m (đường bờ nhảy sang bờ/kênh khác); bản không bỏ ở u_est_incl_jumps_m",
        "u_est_incl_jumps_m": {str(m): {y: round(v, 2) for y, v in e.items()} for m, e in est_all.items()},
        "n_jumps_over_50m": {str(m): e for m, e in n_jump.items()},
        "empirical_range_from_gt_years_m": [min(measured.values()), max(measured.values())],
        "empirical_mean_from_gt_years_m": round(float(np.mean(list(measured.values()))), 2),
        "measured_rmse_vs_ground_truth_m": measured, "calibration": {str(m): c for m, c in cal.items()},
        "u_calibrated_m": {str(m): {y: round(cal[m]["k_mean"] * v, 2) for y, v in est[m].items()} for m in DELTA_MULTIPLES},
        "u_hybrid_m": hybrid,
        "u_hybrid_note": "năm có ground truth: RMSE đo; năm còn lại: k(1δ)·U_est(1δ). config.U_CLASSIFY_DEFAULT hiện dùng 20,0 m cho 6 năm đó.",
        "current_default_m": C.U_CLASSIFY_DEFAULT,
        "years_using_default": [y for y in C.YEARS if y not in measured],
    }
    dump("uclassify", res)
    return res


# ---------------------------------------------------------------------------------------------------------------
# (1) LRR có trọng số
# ---------------------------------------------------------------------------------------------------------------
def run_wls():
    stats_main, dists = main_stats()
    tc_geo = gpd.read_file(OUT / "transects.json")
    variants = {"config": {y: C.U_CLASSIFY.get(y, C.U_CLASSIFY_DEFAULT) for y in C.YEARS}}
    uf = OUT / "limits_uclassify.json"
    if uf.exists():
        variants["hybrid"] = {int(y): v for y, v in json.loads(uf.read_text())["u_hybrid_m"].items()}
    res = {"method": ("LRR trọng số w = 1/U_shoreline(năm)². KTC95% = t(df=n−2)·sqrt(SE_phần_dư² + SE_do_đường_bờ²); "
                      "cách cộng bình phương là quy ước của đồ án (thận trọng: nhiễu có thể bị tính hai lần). Phân phối t cho SE_phần_dư; "
                      "EPR báo cáo như bước 5 (hệ số phủ 1,96)."), "variants": {}}
    for vname, ucls in variants.items():
        rows, cls_changes = [], []
        for rec in tc_geo.itertuples():
            st = stats_main[rec.transect_code]
            if not st:
                continue
            dist = {int(y): v for y, v in dists[rec.transect_code].items() if v is not None}
            ys = sorted(dist)
            if len(ys) < 3:
                continue
            u = [u_total(y, ucls) for y in ys]
            w = weighted_lrr(ys, [dist[y] for y in ys], u)
            n = len(ys); tc = s5_stats.t_crit(n)
            se_ols_meas = ols_meas_se(ys, u)
            ci_ols = st["unc_lrr"]
            ci_ols_comb = tc * float(np.hypot(st["se_lrr"], se_ols_meas))
            ci_w_comb = tc * w["se_comb"]
            u_epr = float(C.Z95 * np.hypot(u_total(ys[0], ucls), u_total(ys[-1], ucls)) / (ys[-1] - ys[0]))
            sig_ols = bool(st["is_significant"])
            sig_epr = abs(st["epr_m_per_year"]) > u_epr
            sig_ols_comb = abs(st["lrr_m_per_year"]) > ci_ols_comb
            sig_w = abs(w["slope"]) > tc * w["se_resid"]
            sig_w_comb = abs(w["slope"]) > ci_w_comb
            cls_old = st["change_class"]
            cls_new = classify_one(w["slope"], sig_w_comb, st["consecutive_erosion_periods"])
            rows.append({"transect": rec.transect_code, "n": n, "epr": st["epr_m_per_year"], "unc_epr": round(u_epr, 3),
                         "lrr_ols": st["lrr_m_per_year"], "ci_ols": ci_ols, "ci_ols_plus_meas": round(ci_ols_comb, 3),
                         "lrr_wls": round(w["slope"], 3), "ci_wls_resid": round(tc * w["se_resid"], 3),
                         "ci_wls_meas_only": round(C.Z95 * w["se_meas"], 3), "ci_wls_combined": round(ci_w_comb, 3),
                         "sig_ols": sig_ols, "sig_epr": bool(sig_epr), "sig_ols_plus_meas": bool(sig_ols_comb),
                         "sig_wls_resid": bool(sig_w), "sig_wls_combined": bool(sig_w_comb), "chi2_red": round(w["chi2_red"], 4),
                         "class_ols": cls_old, "class_wls_combined": cls_new})
            if cls_old != cls_new:
                cls_changes.append({"transect": rec.transect_code, "lrr_ols": st["lrr_m_per_year"],
                                    "lrr_wls": round(w["slope"], 3), "from": cls_old, "to": cls_new})
        a = np.array([r["lrr_wls"] - r["lrr_ols"] for r in rows])
        cross = lambda k1, k2: {"both": sum(r[k1] and r[k2] for r in rows), "only_first": sum(r[k1] and not r[k2] for r in rows),
                                "only_second": sum((not r[k1]) and r[k2] for r in rows), "neither": sum((not r[k1]) and (not r[k2]) for r in rows)}
        t94 = next((r for r in rows if r["transect"] == "T0094"), None)
        res["variants"][vname] = {
            "u_classify_m": ucls,
            "u_shoreline_m": {y: round(u_total(y, ucls), 2) for y in C.YEARS},
            "n_transects": len(rows),
            "ci_halfwidth_ratio_wls_combined_over_ols": ci_summary([r["ci_wls_combined"] / r["ci_ols"] for r in rows if r["ci_ols"] > 0]),
            "lrr_wls_minus_ols": ci_summary(a),
            "chi2_reduced": {**ci_summary([r["chi2_red"] for r in rows]), "median": round(float(np.median([r["chi2_red"] for r in rows])), 4),
                             "share_below_1": round(float(np.mean([r["chi2_red"] < 1 for r in rows])), 3),
                             "note": ("χ²/(n−2) của phần dư so với U_shoreline giả định; ≈1 nếu U mô tả đúng nhiễu giữa các năm, ≪1 nếu U lớn hơn nhiều "
                                      "so với độ phân tán thật giữa các năm (sai số có tính hệ thống/tương quan giữa các năm, KTC cộng sai số bị phóng đại)")},
            "n_significant": {k: sum(r[k] for r in rows) for k in ("sig_ols", "sig_epr", "sig_ols_plus_meas", "sig_wls_resid", "sig_wls_combined")},
            "crosstab_ols_vs_epr": cross("sig_ols", "sig_epr"),
            "crosstab_wls_combined_vs_epr": cross("sig_wls_combined", "sig_epr"),
            "n_class_changed_ols_to_wls_combined": len(cls_changes),
            "classes_ols": {k: sum(r["class_ols"] == k for r in rows) for k in CLASSES},
            "classes_wls_combined": {k: sum(r["class_wls_combined"] == k for r in rows) for k in CLASSES},
            "class_changes": cls_changes,
            "T0094": t94,
            "per_transect": rows,
        }
    dump("wls", res)
    return res


# ---------------------------------------------------------------------------------------------------------------
# (2) Điều tra độ lệch với WorldCover / Impact Observatory
# ---------------------------------------------------------------------------------------------------------------
REFS = {"worldcover": [2020, 2021], "io_lulc": list(range(2018, 2024))}


def ref_line(src, y):
    f = C.DATA / "crosscheck" / f"{src}_{y}.json"
    return gpd.read_file(f).geometry.iloc[0] if f.exists() else None


def ref_mask(src, y, like):
    f = C.DATA / "crosscheck" / f"{src}_{y}.tif"
    return rioxarray.open_rasterio(f).isel(band=0) if f.exists() else None


def run_ref():
    tgdf = gpd.read_file(C.DATA / "transects.json")
    res = {"definition": "độ lệch = khoảng cách gốc transect→đường bờ pipeline − tới đường bờ tham chiếu; dương = pipeline nằm phía sông (ít nước hơn)."}
    # a) phân bố độ lệch, đuôi, cụm vị trí
    base = {}
    for src, years in REFS.items():
        for y in years:
            rl = ref_line(src, y)
            if rl is None:
                continue
            d = diffs(main_line(y), rl, tgdf)
            s = offset_stats(np.array([x[2] for x in d]))
            big = [(c, ch) for c, ch, v in d if abs(v) > C.DESPIKE_M]
            s["outlier_clusters_chainage_m"] = [{"from": a, "to": b, "n": n} for a, b, n in clusters([ch for _, ch in big])]
            s["share_of_transects_with_pipeline_toward_river"] = round(float(np.mean([v > 0 for _, _, v in d])), 3)
            base[f"{src}_{y}"] = s
    res["pipeline_vs_reference"] = base
    # b) hai sản phẩm tham chiếu với nhau, và các nguồn ảnh độc lập với tham chiếu
    inter = {}
    for y in (2020, 2021):
        w, i = ref_line("worldcover", y), ref_line("io_lulc", y)
        if w is not None and i is not None:
            inter[str(y)] = {"io_minus_worldcover": offset_stats(np.array([x[2] for x in diffs(i, w, tgdf)]))}
    res["reference_vs_reference"] = inter
    other = {}
    for name in ("s2c1", "landsat"):
        for src, years in REFS.items():
            for y in years:
                f = C.DATA / "alt" / name / "shoreline" / f"{y}_{INDEX}.json"
                rl = ref_line(src, y)
                if f.exists() and rl is not None:
                    other[f"{name}_vs_{src}_{y}"] = offset_stats(np.array([x[2] for x in diffs(gpd.read_file(f).geometry.iloc[0], rl, tgdf)]))
    res["independent_imagery_vs_reference"] = other
    # c) so với ground truth số hoá thủ công, cùng ô mẫu cùng transect
    gt = {}
    for y in sorted(set(range(2018, 2027))):
        ctx = gt_context(y)
        if ctx is None:
            continue
        zone, gl = ctx
        if gl is None or gl.is_empty:
            continue
        ml = main_line(y)
        entry = {"pipeline_minus_ground_truth": offset_stats(np.array([x[2] for x in diffs(ml, gl, tgdf, zone)]))}
        rl = ref_line("io_lulc", y)
        if rl is not None:
            entry["io_minus_ground_truth"] = offset_stats(np.array([x[2] for x in diffs(rl, gl, tgdf, zone)]))
            entry["pipeline_minus_io_same_transects"] = offset_stats(np.array([x[2] for x in diffs(ml, rl, tgdf, zone)]))
        gt[str(y)] = entry
    res["ground_truth_windows"] = gt
    # d) quét ngưỡng: ngưỡng nào làm triệt tiêu độ lệch, và cái giá so với ground truth
    sweep, grid_cache = {}, {}
    ctxs = {y: gt_context(y) for y in range(2018, 2027)}
    years_needed = sorted({y for ys in REFS.values() for y in ys} | {y for y, c in ctxs.items() if c})
    for y in years_needed:
        a = composite(y)
        ts = sorted(set(THRESHOLD_GRID + [round(otsu(y), 4)]))
        zone_a = s2_water.analysis_mask(a)
        for t in ts:
            line = shoreline_at(a, t)
            raw, _ = raw_water(a, INDEX, t)
            entry = {}
            for src, years in REFS.items():
                if y in years and ref_line(src, y) is not None:
                    d = diffs(line, ref_line(src, y), tgdf)
                    m = ref_mask(src, y, a).values
                    valid = zone_a & (raw != 255) & (m != 255)
                    entry[src] = {**offset_stats(np.array([x[2] for x in d])), "f1": pixel_metrics(raw[valid] == 1, m[valid] == 1)["f1"]}
            if ctxs.get(y):
                zone, gl = ctxs[y]
                dg = diffs(line, gl, tgdf, zone)
                if dg:
                    entry["ground_truth"] = offset_stats(np.array([x[2] for x in dg]))
            grid_cache[(y, t)] = entry
    for y in years_needed:
        ts = sorted(t for (yy, t) in grid_cache if yy == y)
        item = {"otsu": round(otsu(y), 4), "by_threshold": {str(t): grid_cache[(y, t)] for t in ts}}
        for src in list(REFS) + ["ground_truth"]:
            pts = [(t, grid_cache[(y, t)][src]) for t in ts if src in grid_cache[(y, t)]]
            if not pts:
                continue
            xs = [t for t, _ in pts]
            item[f"threshold_zero_mean_offset_vs_{src}"] = zero_crossing(xs, [p["mean_m"] for _, p in pts])
            item[f"threshold_min_rmse_vs_{src}"] = min(pts, key=lambda q: q[1]["rmse_m"])[0]
            if src != "ground_truth":
                item[f"threshold_max_f1_vs_{src}"] = max(pts, key=lambda q: q[1]["f1"])[0]
        z = {src: item.get(f"threshold_zero_mean_offset_vs_{src}") for src in REFS}
        # cái giá: sai số so với ground truth tại ngưỡng khớp tham chiếu nhất (gần nhất trên lưới)
        if ctxs.get(y):
            cost = {}
            for src, tz in z.items():
                if tz is not None:
                    tn = min((t for (yy, t) in grid_cache if yy == y and "ground_truth" in grid_cache[(yy, t)]), key=lambda t: abs(t - tz))
                    cost[src] = {"nearest_grid_threshold": tn, "ground_truth_rmse_m": grid_cache[(y, tn)]["ground_truth"]["rmse_m"],
                                 "ground_truth_mean_m": grid_cache[(y, tn)]["ground_truth"]["mean_m"],
                                 "at_otsu_ground_truth_rmse_m": grid_cache[(y, round(otsu(y), 4))]["ground_truth"]["rmse_m"]}
            item["ground_truth_cost_at_reference_matching_threshold"] = cost
        sweep[str(y)] = item
    res["threshold_sweep"] = sweep
    # e) mùa / mực nước (nếu đã chạy `season`)
    sf = OUT / "limits_season.json"
    if sf.exists():
        res["season"] = json.loads(sf.read_text())
    dump("reference_offset", res)
    return res


def run_season(years=(2020, 2021)):
    """Dựng composite cho từng cửa sổ hai tháng (cần mạng), vector hoá ở ngưỡng Otsu của CHÍNH composite đó, đo độ lệch so với
    đường bờ tham chiếu cùng năm và so với đường bờ chính (mùa khô tháng 1–3). Dùng mùa như đại diện cho mực nước (không có số liệu trạm đo trong repo)."""
    import planetary_computer as pc
    import pystac_client
    from skimage.filters import threshold_otsu
    import s1_composite
    LIM.mkdir(parents=True, exist_ok=True)
    cat = pystac_client.Client.open(C.STAC_URL, modifier=pc.sign_inplace)
    tgdf = gpd.read_file(C.DATA / "transects.json")
    saved = C.SEASON
    res = {}
    try:
        for y in years:
            main_l = main_line(y)
            for s0, s1 in SEASON_WINDOWS:
                key = f"{y}_{s0}_{s1}"
                C.SEASON = (s0, s1)
                f = LIM / f"{key}.tif"
                if f.exists():
                    med = rioxarray.open_rasterio(f)
                    scenes = json.loads((LIM / f"{key}.json").read_text())
                else:
                    med, scenes = s1_composite.composite_year(cat, y)
                    (LIM / f"{key}.json").write_text(json.dumps(scenes, indent=1))
                    if med is not None:
                        med.rio.to_raster(f, compress="deflate")
                        med = rioxarray.open_rasterio(f)   # băng tần đánh số 1–4 như composite chính
                used = sum(s["used"] for s in scenes)
                entry = {"window": [s0, s1], "scenes_total": len(scenes), "scenes_used": used}
                if med is None:
                    entry["note"] = "không có composite hợp lệ (mây)"
                    res[key] = entry
                    continue
                idx = s2_water.indices(med)[INDEX]
                zone = s2_water.analysis_mask(med)
                t = float(threshold_otsu(idx[zone & np.isfinite(idx)]))
                line = shoreline_at(med, t)
                entry["otsu_mndwi"] = round(t, 4)
                entry["water_share_analysis_zone"] = round(float((idx[zone & np.isfinite(idx)] > t).mean()), 4)
                if line is None:
                    entry["note"] = "không trích được đường bờ"
                    res[key] = entry
                    continue
                entry["vs_main_dry_season"] = offset_stats(np.array([x[2] for x in diffs(line, main_l, tgdf)]))
                for src in REFS:
                    rl = ref_line(src, y)
                    if rl is not None:
                        entry[f"vs_{src}"] = offset_stats(np.array([x[2] for x in diffs(line, rl, tgdf)]))
                res[key] = entry
                print(key, used, entry.get("vs_main_dry_season", {}).get("mean_m"), flush=True)
    finally:
        C.SEASON = saved
    (OUT / "limits_season.json").write_text(json.dumps(res, indent=1, ensure_ascii=False, default=_np))
    return res


# ---------------------------------------------------------------------------------------------------------------
# (4) Ngưỡng cố định thay Otsu
# ---------------------------------------------------------------------------------------------------------------
def run_fixed():
    tgdf = gpd.read_file(C.DATA / "transects.json")
    stats_main, _ = main_stats()
    thr = {y: otsu(y) for y in C.YEARS}
    fixed = {"fixed_0.0": 0.0, "fixed_median_otsu": round(float(np.median(list(thr.values()))), 4)}
    res = {"method": ("Thay ngưỡng Otsu từng năm bằng một ngưỡng MNDWI duy nhất cho cả 9 năm, giữ nguyên mọi bước còn lại "
                      "(vector hoá, transect, despike, LRR, ý nghĩa, phân loại). Hai ngưỡng đặt trước: 0,0 (mặc định kinh điển của MNDWI) và trung vị Otsu 9 năm."),
           "otsu_by_year": thr, "otsu_sd": round(float(np.std(list(thr.values()), ddof=1)), 4),
           "otsu_range": [min(thr.values()), max(thr.values())], "variants": {}}
    gt = {y: gt_context(y) for y in C.YEARS}
    for name, t in fixed.items():
        lines = {y: shoreline_at(composite(y), t) for y in C.YEARS}
        st = trend(lines, tgdf)
        cmp_ = compare_trends(stats_main, st)
        shift = {}
        for y in C.YEARS:
            d = [v for _, _, v in diffs(lines[y], main_line(y), tgdf)]
            shift[str(y)] = {"threshold_minus_otsu": round(t - thr[y], 4), **(offset_stats(np.array(d)) or {})}
        gterr = {}
        for y in C.YEARS:
            if gt[y] and gt[y][1] is not None and not gt[y][1].is_empty and lines[y] is not None:
                zone, gl = gt[y]
                gterr[str(y)] = {"fixed": offset_stats(np.array([x[2] for x in diffs(lines[y], gl, tgdf, zone)])),
                                 "otsu": offset_stats(np.array([x[2] for x in diffs(main_line(y), gl, tgdf, zone)]))}
        # transect "đổi kết luận" kèm hướng; nhóm xói đáng chú ý
        res["variants"][name] = {"threshold": t, "shoreline_shift_vs_otsu_line": shift, "trend_comparison": cmp_,
                                 "ground_truth_shoreline_error": gterr}
    dump("fixed_threshold", res)
    return res


# ---------------------------------------------------------------------------------------------------------------
# (5) Độ nhạy lựa chọn MNDWI vs AWEI theo ngưỡng hoà
# ---------------------------------------------------------------------------------------------------------------
def run_select():
    ev = json.loads((OUT / "evaluation.json").read_text())
    report = ev["by_year"]
    years = list(report)
    ties = sorted(set([0.0, 0.00005, 0.0001, 0.0002, 0.0005, 0.001, 0.002, 0.003, 0.004, 0.005, 0.0058, 0.006, 0.007, 0.0075,
                       0.01, 0.015, 0.02, 0.03, 0.05]))
    f1 = {i: float(np.mean([report[y][i]["f1"] for y in years])) for i in C.WATER_INDICES}
    top = max(f1, key=f1.get)
    gap = {i: round(f1[top] - f1[i], 6) for i in C.WATER_INDICES}
    sweep = sweep_tie(report, ties)
    # mốc đổi lựa chọn: quét mịn
    fine = np.round(np.arange(0.0, 0.0301, 0.00001), 5)
    flips, prev = [], None
    for t in fine:
        cur = select_index(report, float(t))[0]
        if cur != prev:
            flips.append({"tie_from": float(t), "selected": cur})
            prev = cur
    loo = {"none": leave_out(report, set())}
    for y in years:
        loo[f"drop_{y}"] = leave_out(report, {y})
    f1_by_year = {y: {i: report[y][i]["f1"] for i in C.WATER_INDICES} for y in years}
    rmse_by_year = {y: {i: (report[y]["shoreline_error"][i] or {}).get("rmse_m") for i in C.WATER_INDICES} for y in years}
    res = {"current_tie_f1": C.SELECT_TIE_F1, "selected_current": select_index(report)[0],
           "mean_f1_unrounded": {i: round(v, 6) for i, v in f1.items()}, "f1_gap_to_top": gap, "top_by_f1": top,
           "mean_rmse_m": ev["mean_rmse_m"], "selection_by_tie": sweep, "selection_flip_points": flips,
           "leave_one_year_out": loo, "f1_by_year": f1_by_year, "rmse_by_year": rmse_by_year,
           "years_where_awei_has_higher_f1_than_mndwi": [y for y in years if f1_by_year[y]["awei"] > f1_by_year[y]["mndwi"]],
           "years_where_mndwi_has_lower_rmse_than_awei": [y for y in years if rmse_by_year[y]["mndwi"] < rmse_by_year[y]["awei"]]}
    dump("index_selection", res)
    return res


def run_summary():
    """Số liệu đầu mối để cập nhật mục "Hạn chế của quy trình" (đọc lại các output/limits_*.json)."""
    rd = lambda n: json.loads((OUT / f"limits_{n}.json").read_text())
    w, u, r, f, sel = rd("wls"), rd("uclassify"), rd("reference_offset"), rd("fixed_threshold"), rd("index_selection")
    wc = w["variants"]["config"]
    pv = r["pipeline_vs_reference"]
    out = {
        "wls": {"n_transects": wc["n_transects"], "n_significant": wc["n_significant"], "crosstab_ols_vs_epr": wc["crosstab_ols_vs_epr"],
                "T0094": wc["T0094"], "chi2_reduced_median": wc["chi2_reduced"]["median"],
                "ci_ratio_combined_over_ols_mean": wc["ci_halfwidth_ratio_wls_combined_over_ols"]["mean"],
                "n_class_changed": wc["n_class_changed_ols_to_wls_combined"], "classes_wls_combined": wc["classes_wls_combined"]},
        "reference_offset": {
            "rmse_m_range": [min(v["rmse_m"] for v in pv.values()), max(v["rmse_m"] for v in pv.values())],
            "mean_m_range": [min(v["mean_m"] for v in pv.values()), max(v["mean_m"] for v in pv.values())],
            "median_m_range": [min(v["median_m"] for v in pv.values()), max(v["median_m"] for v in pv.values())],
            "rmse_excl_over_50m_range": [min(v["rmse_excl_over_50m"] for v in pv.values()), max(v["rmse_excl_over_50m"] for v in pv.values())],
            "ss_share_over_50m_range": [min(v["ss_share_over_50m"] for v in pv.values()), max(v["ss_share_over_50m"] for v in pv.values())],
            "ground_truth_windows": r["ground_truth_windows"],
            "threshold_zero_mean_offset": {y: {k: v for k, v in x.items() if k.startswith("threshold_zero")} for y, x in r["threshold_sweep"].items()},
            "has_season": "season" in r},
        "uclassify": {"u_hybrid_m": u["u_hybrid_m"], "u_est_1delta_m": u["u_est_raw_m"]["1.0"], "k_1delta": u["calibration"]["1.0"],
                      "empirical_range_from_gt_years_m": u["empirical_range_from_gt_years_m"]},
        "fixed_threshold": {n: {k: v["trend_comparison"][k] for k in ("pearson_lrr", "class_agreement", "n_class_changed", "severe_a", "severe_b", "classes_b")}
                            for n, v in f["variants"].items()},
        "index_selection": {"f1_gap_mndwi_to_top": sel["f1_gap_to_top"]["mndwi"], "flip_points": sel["selection_flip_points"],
                            "leave_one_year_out": {k: v["selected"] for k, v in sel["leave_one_year_out"].items()}},
    }
    dump("summary", out)
    return out


ALL = {"uclass": run_uclassify, "wls": run_wls, "ref": run_ref, "fixed": run_fixed, "select": run_select, "summary": run_summary}

if __name__ == "__main__":
    what = sys.argv[1:] or ["all"]
    if what == ["all"]:
        what = list(ALL)
    for w in what:
        if w == "season":
            run_season()
        else:
            ALL[w]()
        print(w, "xong", flush=True)
