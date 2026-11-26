"""Giải đoán lại ảnh bằng QGIS (PyQGIS + Processing) rồi so với kết quả của pipeline Python.

Chạy bằng Python HỆ THỐNG có PyQGIS:  python3 qgis_interpret.py [năm ...]
Với từng ảnh data/composite/<năm>.tif, QGIS tự làm lại toàn bộ chuỗi giải đoán, KHÔNG dùng raster/ngưỡng/đường bờ của Python:
  1. native:buffer + gdal:cliprasterbymasklayer  — cắt ảnh theo ranh giới xã đệm 1 km (cùng vùng phân tích với bước 2)
  2. gdal:rastercalculator                       — MNDWI = (Green − SWIR1)/(Green + SWIR1)
  3. Otsu trên histogram do QgsRasterDataProvider.histogram() của QGIS trả về (QGIS không có sẵn thuật toán Otsu)
  4. gdal:rastercalculator + gdal:sieve          — mặt nạ nước, lọc đốm/lỗ nhỏ (thay cho mở hình thái học của Python)
  5. gdal:polygonize → giữ vùng nước lớn nhất → native:polygonstolines → native:clip theo ranh giới xã (đệm 100 m)
So sánh với bản Python ĐÃ LƯU (data/shoreline_python, data/transects_python.json, output/transects_python.json — mốc đối chiếu cố định
vì đường bờ chính của pipeline nay là bản QGIS, xem config.SHORELINE_ENGINE). Đầu ra output/qgis_interpret.json, dữ liệu trung gian data/qgis/):
  - ngưỡng Otsu và diện tích nước hai bên;
  - khoảng cách giữa hai đường bờ (lấy mẫu mỗi 10 m trên đường bờ QGIS, đo tới đường bờ Python);
  - khoảng cách theo transect (giao điểm gần gốc nhất, như s4_transects.distance) và LRR dựng lại từ đường bờ QGIS,
    so với LRR của bản chính (output/transects.json).
Ghi chú phương pháp: Python dò đường bờ bằng contour dưới pixel trên trường chỉ số liên tục, còn QGIS dò theo cạnh pixel của mặt nạ
nhị phân — hai cách không đồng nhất nên độ lệch cỡ ≤ 1 pixel (10 m) là bình thường.
"""
import json
import sys
from pathlib import Path

import numpy as np

import os

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")

from qgis.core import (  # noqa: E402
    QgsApplication, QgsCoordinateReferenceSystem, QgsGeometry, QgsPointXY, QgsProcessingFeedback, QgsRasterBandStats,
    QgsRasterLayer, QgsVectorLayer, QgsFeatureRequest,
)

ROOT = Path(__file__).parent
DATA, OUTPUT = ROOT / "data", ROOT / "output"
OUT = DATA / "qgis"
YEARS = list(range(2018, 2027))
COMMUNE = DATA / "ref" / "binhmy_osm.json"
CRS = "EPSG:32648"
ANALYSIS_BUFFER, BANK_ZONE = 1000, 100       # m — khớp s2_water.ANALYSIS_BUFFER và s3_shoreline.BANK_ZONE
SIEVE_PX = 500                               # pixel (5 ha) — khớp MIN_HOLE của s3_shoreline
OPEN_M = 50                                  # m — bán kính mở hình thái học (5 px)
MIN_HOLE_M2 = 50_000                         # m² — lấp lỗ < 5 ha như MIN_HOLE
SAMPLE_STEP = 10                             # m
MIN_PIECE = 200                              # m — như s3_shoreline.MIN_PIECE
MIN_POINTS = 3
DESPIKE_M = 50                               # như config.DESPIKE_M

_proc = None


def run(alg, params):
    """Chạy một thuật toán Processing và trả về kết quả; lỗi thì dừng chương trình."""
    import processing
    return processing.run(alg, params, feedback=QgsProcessingFeedback())


def otsu(values, counts):
    """Otsu 1988 trên histogram (giá trị đầu mỗi bin, số đếm)."""
    w = counts.astype(float)
    total = w.sum()
    cw = np.cumsum(w)
    cm = np.cumsum(w * values)
    mt = cm[-1]
    w0, w1 = cw, total - cw
    with np.errstate(divide="ignore", invalid="ignore"):
        m0, m1 = cm / w0, (mt - cm) / w1
        var = w0 * w1 * (m0 - m1) ** 2
    return float(values[np.nanargmax(var)])


def _feat(f, g):
    f.setGeometry(g)
    return f


def interpret_year(year, zone_buf, zone_bank):
    """Giải đoán một năm bằng QGIS; trả về (đường bờ QgsGeometry, ngưỡng Otsu, diện tích nước km², đường dẫn đường bờ)."""
    work = OUT / "work"
    work.mkdir(parents=True, exist_ok=True)
    clip = work / f"{year}_clip.tif"
    run("gdal:cliprasterbymasklayer", {
        "INPUT": str(DATA / "composite" / f"{year}.tif"), "MASK": zone_buf, "SOURCE_CRS": CRS, "TARGET_CRS": CRS,
        "NODATA": -9999, "CROP_TO_CUTLINE": True, "KEEP_RESOLUTION": True, "DATA_TYPE": 6, "OUTPUT": str(clip)})
    mndwi = work / f"{year}_mndwi.tif"
    run("gdal:rastercalculator", {
        "INPUT_A": str(clip), "BAND_A": 1, "INPUT_B": str(clip), "BAND_B": 3,
        "FORMULA": "(A-B)/(A+B)", "NO_DATA": -9999, "RTYPE": 5, "OUTPUT": str(mndwi)})
    lyr = QgsRasterLayer(str(mndwi), f"mndwi_{year}")
    prov = lyr.dataProvider()
    st = prov.bandStatistics(1, QgsRasterBandStats.All)
    nb = 1024
    h = prov.histogram(1, nb, st.minimumValue, st.maximumValue)
    step = (st.maximumValue - st.minimumValue) / nb
    centers = st.minimumValue + step * (np.arange(nb) + 0.5)
    thr = otsu(centers, np.array(h.histogramVector))
    water = work / f"{year}_water.tif"
    run("gdal:rastercalculator", {
        "INPUT_A": str(mndwi), "BAND_A": 1, "FORMULA": f"(A>{thr:.6f})*1", "NO_DATA": 255, "RTYPE": 1, "OUTPUT": str(water)})
    sieved = work / f"{year}_sieve.tif"
    run("gdal:sieve", {"INPUT": str(water), "THRESHOLD": SIEVE_PX, "EIGHT_CONNECTEDNESS": False, "NO_MASK": False,
                       "OUTPUT": str(sieved)})
    poly = run("gdal:polygonize", {"INPUT": str(sieved), "BAND": 1, "FIELD": "DN", "EIGHT_CONNECTEDNESS": False,
                                   "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    wl = QgsVectorLayer(poly, "poly", "ogr") if isinstance(poly, str) else poly
    wl.selectByExpression('"DN" = 1')
    feats = sorted(wl.selectedFeatures(), key=lambda f: -f.geometry().area())
    if not feats:
        raise SystemExit(f"{year}: QGIS không tìm thấy vùng nước")
    # Mở hình thái học dạng vector (buffer −50 m rồi +50 m, như OPEN_RADIUS = 5 px của Python) để bỏ kênh rạch hẹp
    from qgis.core import QgsFeature
    body = QgsVectorLayer(f"Polygon?crs={CRS}", "water", "memory")
    big = [f.geometry() for f in feats if f.geometry().area() >= 1e6]
    body.dataProvider().addFeatures([_feat(QgsFeature(body.fields()), g) for g in big])
    opened = run("native:buffer", {"INPUT": run("native:buffer", {"INPUT": body, "DISTANCE": -OPEN_M, "SEGMENTS": 8,
                 "DISSOLVE": False, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"], "DISTANCE": OPEN_M, "SEGMENTS": 8,
                 "DISSOLVE": False, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    parts = sorted(opened.getFeatures(), key=lambda f: -f.geometry().area())
    area_km2 = parts[0].geometry().area() / 1e6          # vùng nước liên thông lớn nhất (sông Hậu + Vàm Nao)
    mem = QgsVectorLayer(f"Polygon?crs={CRS}", "water1", "memory")
    mem.dataProvider().addFeatures([_feat(QgsFeature(mem.fields()), parts[0].geometry())])
    mem = run("native:deleteholes", {"INPUT": mem, "MIN_AREA": MIN_HOLE_M2, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    lines = run("native:polygonstolines", {"INPUT": mem, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    clipped = run("native:clip", {"INPUT": lines, "OVERLAY": zone_bank, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    parts = []
    for f in clipped.getFeatures():
        g = f.geometry()
        for part in (g.asGeometryCollection() if g.isMultipart() else [g]):
            if part.length() >= MIN_PIECE:
                parts.append(part)
    merged = QgsGeometry.unaryUnion(parts).mergeLines() if parts else QgsGeometry()
    return merged, thr, area_km2


def line_distance_stats(qline, pline):
    """Khoảng cách từ các điểm lấy mẫu mỗi 10 m trên đường bờ QGIS tới đường bờ Python (và ngược lại)."""
    def sample(g):
        out = []
        for part in (g.asGeometryCollection() if g.isMultipart() else [g]):
            n = max(int(part.length() // SAMPLE_STEP), 1)
            out += [part.interpolate(i * part.length() / n) for i in range(n + 1)]
        return out
    def dists(src, dst):
        return np.array([p.distance(dst) for p in sample(src)])
    a, b = dists(qline, pline), dists(pline, qline)
    return {
        "qgis_to_python": {"mean_m": round(float(a.mean()), 2), "median_m": round(float(np.median(a)), 2),
                           "p95_m": round(float(np.percentile(a, 95)), 2), "rmse_m": round(float(np.sqrt((a ** 2).mean())), 2)},
        "python_to_qgis": {"mean_m": round(float(b.mean()), 2), "median_m": round(float(np.median(b)), 2),
                           "p95_m": round(float(np.percentile(b, 95)), 2), "rmse_m": round(float(np.sqrt((b ** 2).mean())), 2)},
        "hausdorff_m": round(float(max(a.max(), b.max())), 2),
        "within_1px_pct": round(float(((a <= 10).mean() + (b <= 10).mean()) * 50), 1),
        "within_2px_pct": round(float(((a <= 20).mean() + (b <= 20).mean()) * 50), 1),
    }


def transect_distance(tr, line):
    """Khoảng cách từ gốc transect tới giao điểm gần nhất với đường bờ (như s4_transects.distance); None nếu không giao."""
    x = tr.intersection(line)
    if x.isEmpty():
        return None
    pts = [QgsPointXY(p) for p in (x.asMultiPoint() if x.isMultipart() else [x.asPoint()])] if x.type() == 0 else []
    if not pts:
        return None
    o = tr.asPolyline()[0]
    return round(min(o.distance(p) for p in pts), 2)


def lrr(dist):
    """Despike (lệch > 50 m so với trung vị) rồi hồi quy tuyến tính theo năm — như s5_stats.despike/stats."""
    vals = [d for d in dist.values() if d is not None]
    if not vals:
        return None
    med = float(np.median(vals))
    s = sorted((int(y), d) for y, d in dist.items() if d is not None and abs(d - med) <= DESPIKE_M)
    if len(s) < MIN_POINTS:
        return None
    t = np.array([y for y, _ in s], float)
    d = np.array([v for _, v in s], float)
    return float(np.polyfit(t, d, 1)[0])


def main(years):
    app = QgsApplication([], False)
    QgsApplication.setPrefixPath("/usr", True)
    app.initQgis()
    sys.path.append("/usr/share/qgis/python/plugins")
    import processing
    from processing.core.Processing import Processing
    Processing.initialize()
    from qgis.analysis import QgsNativeAlgorithms
    QgsApplication.processingRegistry().addProvider(QgsNativeAlgorithms())

    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "shoreline").mkdir(exist_ok=True)
    commune = QgsVectorLayer(str(COMMUNE), "commune", "ogr")
    commune.setCrs(QgsCoordinateReferenceSystem("EPSG:4326")) if not commune.crs().isValid() else None
    reproj = run("native:reprojectlayer", {"INPUT": commune, "TARGET_CRS": CRS, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    zone_buf = run("native:buffer", {"INPUT": reproj, "DISTANCE": ANALYSIS_BUFFER, "DISSOLVE": True, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    zone_bank = run("native:buffer", {"INPUT": reproj, "DISTANCE": BANK_ZONE, "DISSOLVE": True, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]

    py_thr = json.loads((DATA / "water" / "thresholds.json").read_text())
    transects = json.loads((DATA / "transects_python.json").read_text())["features"]
    tgeom = {f["properties"]["transect_code"]: QgsGeometry.fromPolylineXY([QgsPointXY(*c) for c in f["geometry"]["coordinates"]])
             for f in transects}
    py_stats = {f["properties"]["transect_code"]: (f["properties"].get("stats") or {}).get("lrr_m_per_year")
                for f in json.loads((OUTPUT / "transects_python.json").read_text())["features"]}
    py_dist = {f["properties"]["transect_code"]: f["properties"]["distances"] for f in transects}

    per_year, qdist = {}, {c: {} for c in tgeom}
    for y in years:
        if not (DATA / "composite" / f"{y}.tif").exists():
            print(f"{y}: bỏ qua (không có ảnh composite)")
            continue
        qline, thr, area = interpret_year(y, zone_buf, zone_bank)
        pf = DATA / "shoreline_python" / f"{y}.json"
        pl = QgsVectorLayer(str(pf), "py", "ogr")
        pline = QgsGeometry.unaryUnion([f.geometry() for f in pl.getFeatures()])
        res = {"otsu_qgis": round(thr, 4), "otsu_python": py_thr[str(y)]["mndwi"],
               "water_area_km2_qgis": round(area, 2), "length_km_qgis": round(qline.length() / 1000, 2),
               "length_km_python": round(pline.length() / 1000, 2), **line_distance_stats(qline, pline)}
        # khoảng cách theo transect: QGIS vs Python
        diffs = []
        for code, tg in tgeom.items():
            d = transect_distance(tg, qline)
            qdist[code][str(y)] = d
            p = py_dist[code].get(str(y))
            if d is not None and p is not None:
                diffs.append(d - p)
        if diffs:
            diffs = np.array(diffs)
            res["transect_offset"] = {"n": int(len(diffs)), "mean_m": round(float(diffs.mean()), 2),
                                      "mean_abs_m": round(float(np.abs(diffs).mean()), 2),
                                      "rmse_m": round(float(np.sqrt((diffs ** 2).mean())), 2)}
        per_year[str(y)] = res
        out = QgsVectorLayer(f"MultiLineString?crs={CRS}&field=year:integer&field=source:string", "o", "memory")
        from qgis.core import QgsFeature, QgsVectorFileWriter, QgsProject
        ft = QgsFeature(out.fields()); ft.setGeometry(qline); ft.setAttributes([y, "qgis"])
        out.dataProvider().addFeature(ft)
        QgsVectorFileWriter.writeAsVectorFormat(out, str(OUT / "shoreline" / f"{y}.geojson"), "utf-8", out.crs(), "GeoJSON")
        os.replace(OUT / "shoreline" / f"{y}.geojson", OUT / "shoreline" / f"{y}.json")
        print(f"{y}: Otsu QGIS {thr:.4f} / Python {py_thr[str(y)]['mndwi']:.4f} · trung vị cách {res['qgis_to_python']['median_m']} m · "
              f"≤2 pixel {res['within_2px_pct']}%")

    # LRR từ đường bờ QGIS so với LRR bản chính
    q_lrr = {c: lrr(d) for c, d in qdist.items()}
    pairs = [(q_lrr[c], py_stats[c]) for c in tgeom if q_lrr[c] is not None and py_stats.get(c) is not None]
    trend = None
    if len(pairs) > 2:
        q, p = np.array(pairs).T
        trend = {"n_transects": len(pairs), "pearson_r": round(float(np.corrcoef(q, p)[0, 1]), 3),
                 "bias_mps": round(float((q - p).mean()), 3), "mae_mps": round(float(np.abs(q - p).mean()), 3),
                 "rmse_mps": round(float(np.sqrt(((q - p) ** 2).mean())), 3),
                 "same_sign_pct": round(float((np.sign(q) == np.sign(p)).mean() * 100), 1),
                 "severe_agree": int(((q <= -2) & (p <= -2)).sum()), "severe_qgis": int((q <= -2).sum()),
                 "severe_python": int((p <= -2).sum())}
        print("LRR QGIS vs Python:", trend)
    (OUTPUT / "qgis_interpret.json").write_text(json.dumps({
        "method": "QGIS %s: MNDWI (gdal:rastercalculator) → Otsu (histogram QGIS) → sieve %d px → polygonize → đường bờ" %
                  (__import__("qgis.core", fromlist=["Qgis"]).Qgis.version().split("-")[0], SIEVE_PX),
        "per_year": per_year, "trend_lrr": trend,
        "transect_lrr": {c: (round(v, 3) if v is not None else None) for c, v in q_lrr.items()},
    }, ensure_ascii=False, indent=1))
    print("Đã ghi", OUTPUT / "qgis_interpret.json")
    sys.stdout.flush()
    os._exit(0)   # exitQgis() làm PyQGIS 3.40 báo segfault khi dọn lớp tạm; mọi tệp đã ghi xong


if __name__ == "__main__":
    main([int(a) for a in sys.argv[1:]] or YEARS)
