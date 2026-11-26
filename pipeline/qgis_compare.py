"""Bản đồ QGIS so sánh bản data chính với hai bản đối chứng (Sentinel-2 C1, Landsat 8/9).

Chạy bằng Python HỆ THỐNG có PyQGIS, sau alt_data.py + alt_compare.py:  python3 qgis_compare.py
Đầu ra: data/ban_do/so_sanh_ba_nguon.qgz (mở bằng `qgis`), ../docs/ban_do/so_sanh_*.png|pdf
  1. so_sanh_lrr_3_nguon         — LRR từng transect của ba nguồn, cùng thang màu, cạnh nhau
  2. so_sanh_duong_bo_diem_nong  — đường bờ 2026 của ba nguồn quanh transect lùi mạnh nhất của bản chính
  3. so_sanh_chenh_lech_lrr      — chênh LRR (đối chứng − chính) trên từng transect
"""
import os

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")

from qgis.core import (  # noqa: E402
    Qgis, QgsApplication, QgsFillSymbol, QgsGraduatedSymbolRenderer, QgsLayoutExporter, QgsLayoutItemLabel,
    QgsLayoutItemLegend, QgsLayoutItemMap, QgsLayoutItemPicture, QgsLayoutItemScaleBar, QgsLayoutPoint, QgsLayoutSize,
    QgsPrintLayout, QgsProject, QgsRendererRange, QgsSymbol, QgsTextFormat, QgsUnitTypes, QgsCategorizedSymbolRenderer,
    QgsRendererCategory, QgsRectangle, QgsVectorLayer,
)
from qgis.PyQt.QtGui import QColor, QFont  # noqa: E402

import qgis_maps as Q  # noqa: E402  (dùng lại hàm dựng lớp/bố cục của bản đồ chính)

PROJECT = Q.DATA / "ban_do" / "so_sanh_ba_nguon.qgz"
SRC = {"main": ("Bản chính — Sentinel-2 L2A (Planetary Computer)", "#1565c0"),
       "s2c1": ("Đối chứng 1 — Sentinel-2 L2A Collection 1 (AWS Earth Search)", "#00c853"),
       "landsat": ("Đối chứng 2 — Landsat 8/9 C2 L2 (30 m)", "#ff6d00")}
LRR_BINS = [(-99, -2, "#b2182b", "≤ −2 (lùi mạnh)"), (-2, -0.5, "#ef8a62", "−2 … −0,5"), (-0.5, 0.5, "#d9d9d9", "−0,5 … +0,5 (ổn định)"),
            (0.5, 2, "#67a9cf", "+0,5 … +2"), (2, 99, "#2166ac", "> +2 (bồi)")]
DIFF_BINS = [(-99, -1.5, "#b2182b", "< −1,5"), (-1.5, -0.5, "#ef8a62", "−1,5 … −0,5"), (-0.5, 0.5, "#d9d9d9", "±0,5 (khớp)"),
             (0.5, 1.5, "#67a9cf", "+0,5 … +1,5"), (1.5, 99, "#2166ac", "> +1,5")]


def graduated(field, bins, width=0.9):
    ranges = []
    for lo, hi, col, lab in bins:
        s = QgsSymbol.defaultSymbol(Qgis.GeometryType.Line)
        s.setColor(QColor(col)); s.setWidth(width)
        ranges.append(QgsRendererRange(lo, hi, s, lab))
    return QgsGraduatedSymbolRenderer(field, ranges)


def transect_layer(field, name, bins):
    lyr = Q.vector(Q.OUTPUT / "alt_transects.json", name)
    lyr.setRenderer(graduated(field, bins))
    return lyr


def qgis_transects():
    """Bản sao lớp transect + trường qgis_lrr / qgis_diff (LRR dựng từ đường bờ QGIS, output/qgis_interpret.json), ghi ra tệp để lưu trong project."""
    import json
    from qgis.core import QgsFeature, QgsField, QgsVectorFileWriter
    from qgis.PyQt.QtCore import QVariant
    q = json.loads((Q.OUTPUT / "qgis_interpret.json").read_text())["transect_lrr"]
    src = Q.vector(Q.OUTPUT / "alt_transects.json", "src")
    mem = QgsVectorLayer(f"LineString?crs={Q.CRS.authid()}", "t", "memory")
    mem.dataProvider().addAttributes([QgsField("transect_code", QVariant.String), QgsField("main_lrr", QVariant.Double),
                                      QgsField("qgis_lrr", QVariant.Double), QgsField("qgis_diff", QVariant.Double)])
    mem.updateFields()
    out = []
    for f in src.getFeatures():
        v, m = q.get(f["transect_code"]), f["main_lrr"]
        g = QgsFeature(mem.fields()); g.setGeometry(f.geometry())
        g.setAttributes([f["transect_code"], m, v, (round(v - m, 3) if v is not None and m is not None else None)])
        out.append(g)
    mem.dataProvider().addFeatures(out)
    path = Q.DATA / "qgis" / "transects_lrr"
    path.parent.mkdir(parents=True, exist_ok=True)
    QgsVectorFileWriter.writeAsVectorFormat(mem, str(path) + ".geojson", "utf-8", mem.crs(), "GeoJSON")
    os.replace(str(path) + ".geojson", str(path) + ".json")
    return path.with_suffix(".json")


def text(lay, s, x, y, w, h, size, bold=False):
    it = QgsLayoutItemLabel(lay)
    it.setText(s)
    f = QFont("Noto Sans", size); f.setBold(bold)
    fmt = QgsTextFormat(); fmt.setFont(f); fmt.setSize(size)
    it.setTextFormat(fmt)
    it.attemptMove(QgsLayoutPoint(x, y)); it.attemptResize(QgsLayoutSize(w, h))
    lay.addLayoutItem(it)


def three_panel(project, name, title, subtitle, panels, extent, legend_layer, note, legend_title="LRR (m/năm); âm = lùi bờ"):
    lay = QgsPrintLayout(project)
    lay.initializeDefaults(); lay.setName(name)
    pg = lay.pageCollection().page(0); pg.setPageSize("A4", pg.Landscape)
    text(lay, title, 8, 4, 281, 9, 15, True); text(lay, subtitle, 8, 12, 281, 6, 8)
    w, h = 92, 120
    first = None
    for i, (label, layers) in enumerate(panels):
        x = 8 + i * (w + 2.5)
        text(lay, label, x, 19, w, 10, 8, True)
        m = QgsLayoutItemMap(lay)
        m.attemptMove(QgsLayoutPoint(x, 29)); m.attemptResize(QgsLayoutSize(w, h))
        m.setCrs(Q.CRS); m.setLayers(layers); m.setFrameEnabled(True); m.zoomToExtent(extent)
        lay.addLayoutItem(m)
        first = first or m
    lg = QgsLayoutItemLegend(lay)
    lg.setTitle(legend_title); lg.setLinkedMap(first); lg.setAutoUpdateModel(False)
    for node in lg.model().rootGroup().findLayers():
        if node.layerId() not in {l.id() for l in (legend_layer if isinstance(legend_layer, (list, tuple)) else [legend_layer])}:
            node.parent().removeChildNode(node)
    if isinstance(legend_layer, (list, tuple)):
        lg.setColumnCount(2); lg.setSymbolHeight(3); lg.setLineSpacing(0.6)
    lg.attemptMove(QgsLayoutPoint(8, 153)); lay.addLayoutItem(lg)
    sb = QgsLayoutItemScaleBar(lay)
    sb.setStyle("Single Box"); sb.setLinkedMap(first)
    sb.setUnits(QgsUnitTypes.DistanceKilometers); sb.setUnitLabel("km"); sb.setUnitsPerSegment(2)
    sb.setNumberOfSegments(2); sb.setNumberOfSegmentsLeft(0)
    sb.attemptMove(QgsLayoutPoint(150, 183) if isinstance(legend_layer, (list, tuple)) else QgsLayoutPoint(110, 178)); lay.addLayoutItem(sb)
    na = QgsLayoutItemPicture(lay)
    na.setPicturePath(QgsApplication.pkgDataPath() + "/svg/arrows/NorthArrow_02.svg")
    na.setLinkedMap(first); na.attemptMove(QgsLayoutPoint(270, 150)); na.attemptResize(QgsLayoutSize(14, 20))
    lay.addLayoutItem(na)
    text(lay, note + f" Hệ toạ độ WGS 84 / UTM 48N. Thành lập bằng QGIS {Qgis.version().split('-')[0]}.", 110 if not isinstance(legend_layer, (list, tuple)) else 120, 150, 145, 26, 7)
    project.layoutManager().addLayout(lay)
    return lay


def main():
    QgsApplication.setPrefixPath("/usr", True)
    app = QgsApplication([], False); app.initQgis()
    project = QgsProject.instance(); project.setCrs(Q.CRS)
    project.setTitle("So sánh ba nguồn dữ liệu — bờ sông Hậu, xã Bình Mỹ")
    img = Q.composite(2026)
    xa = Q.vector(Q.OUTPUT / "aoi.json", "Ranh giới xã Bình Mỹ"); xa.setSubsetString("\"role\" = 'commune'")
    xa.renderer().setSymbol(QgsFillSymbol.createSimple({"color": "0,0,0,0", "outline_color": "255,255,0", "outline_width": "0.4", "outline_style": "dash"}))
    lrr = {k: transect_layer(f"{k}_lrr", "Phân nhóm LRR (m/năm)", LRR_BINS) for k in SRC}
    diff = {k: transect_layer(f"{k}_diff", "Chênh LRR (m/năm)", DIFF_BINS) for k in ("s2c1", "landsat")}
    # đường bờ 2026 ba nguồn
    shore, short, width = {}, {"main": "Bờ 2026 — bản chính (S2 PC)", "s2c1": "Bờ 2026 — Sentinel-2 C1", "landsat": "Bờ 2026 — Landsat 8/9",
                                      "qgis": "Bờ 2026 — QGIS tự giải đoán"}, \
        {"main": 0.4, "s2c1": 1.0, "landsat": 1.8, "qgis": 0.8}   # nét mảnh nằm trên nét dày để thấy cả ba khi chúng trùng nhau
    for k, (lab, col) in {**SRC, "qgis": ("", "#e91e63")}.items():
        f = {"main": Q.DATA / "shoreline" / "2026.json", "qgis": Q.DATA / "qgis" / "shoreline" / "2026.json"}.get(
            k, Q.DATA / "alt" / k / "shoreline" / "2026_mndwi.json")
        lyr = Q.vector(f, short[k]); lyr.renderer().setSymbol(Q.line(col, width[k])); shore[k] = lyr
    qt = qgis_transects()
    lrr["qgis"] = Q.vector(qt, "Phân nhóm LRR (m/năm)"); lrr["qgis"].setRenderer(graduated("qgis_lrr", LRR_BINS))
    lrr["main_q"] = Q.vector(qt, "Phân nhóm LRR (m/năm)"); lrr["main_q"].setRenderer(graduated("main_lrr", LRR_BINS))
    diff["qgis"] = Q.vector(qt, "Chênh LRR (m/năm)"); diff["qgis"].setRenderer(graduated("qgis_diff", DIFF_BINS))
    layers = [img, xa, *lrr.values(), *diff.values(), *shore.values()]
    for l in layers:
        project.addMapLayer(l)
    root = project.layerTreeRoot()
    for l in [*diff.values(), *shore.values()]:
        root.findLayer(l.id()).setItemVisibilityChecked(False)

    tr_ext = lrr["main"].extent(); tr_ext.grow(300)
    maps = [
        three_panel(project, "so_sanh_lrr_3_nguon", "So sánh tốc độ biến động bờ (LRR) giữa ba nguồn dữ liệu, 2018–2026",
                    "Cùng bộ 267 transect, cùng thủ tục khử đột biến/hồi quy (bước 5); khác nguồn ảnh, bản xử lý và cảm biến",
                    [("Bản chính — Sentinel-2 L2A (PC), 9 năm", [lrr["main"], xa, img]),
                     ("Đối chứng 1 — Sentinel-2 C1 (AWS), 6 năm tin cậy", [lrr["s2c1"], xa, img]),
                     ("Đối chứng 2 — Landsat 8/9 (30 m), 9 năm", [lrr["landsat"], xa, img])],
                    tr_ext, lrr["main"],
                    "Sentinel-2 C1 bỏ 2018, 2019, 2022 (1–2 cảnh hoặc bị mây che). Phân nhóm 'lùi mạnh' cần LRR ≤ −2 m/năm và ≥ 3 mốc giảm liên tiếp; "
                    "một transect có thể mang màu 'lùi mạnh' (LRR ≤ −2) nhưng không đạt tiêu chí liên tiếp."),
        three_panel(project, "so_sanh_chenh_lrr", "Chênh lệch LRR giữa bản đối chứng và bản chính (đối chứng − chính)",
                    "Màu xám = hai nguồn khớp trong ±0,5 m/năm; đỏ = đối chứng lùi nhiều hơn bản chính, xanh = bồi nhiều hơn",
                    [("Sentinel-2 C1 − bản chính", [diff["s2c1"], xa, img]), ("Landsat − bản chính", [diff["landsat"], xa, img])],
                    tr_ext, diff["s2c1"], "Chênh LRR (m/năm) tính trên cùng transect; Sentinel-2 C1 chỉ dùng 6 năm tin cậy.", legend_title="Chênh LRR (đối chứng − chính)"),
        three_panel(project, "so_sanh_lrr_python_qgis", "LRR dựng từ đường bờ Python và từ đường bờ QGIS tự giải đoán, 2018–2026",
                    "Cùng ảnh Sentinel-2 L2A (PC), cùng 267 transect và thủ tục hồi quy; chỉ khác phần giải đoán ảnh (Python/scikit-image và QGIS/GDAL)",
                    [("Python — bản chính", [lrr["main_q"], xa, img]), ("QGIS tự giải đoán", [lrr["qgis"], xa, img]),
                     ("Chênh LRR (QGIS − Python)", [diff["qgis"], xa, img])],
                    tr_ext, [lrr["main_q"], diff["qgis"]],
                    "QGIS làm lại MNDWI → Otsu → sieve → polygonize → mở hình thái học vector (buffer ∓50 m) rồi đo khoảng cách theo transect. "
                    "Hai cột đầu cùng thang LRR; cột ba dùng thang chênh LRR (xám = khớp trong ±0,5 m/năm)."),
    ]
    # bản đồ chi tiết đường bờ 2026 quanh transect lùi mạnh nhất của bản chính
    worst = max(lrr["main"].getFeatures(), key=lambda f: -(f["main_lrr"] or 0))
    c = worst.geometry().centroid().asPoint()
    ext = QgsRectangle(c.x() - 450, c.y() - 330, c.x() + 450, c.y() + 330)
    detail = Q.add_layout(project, "so_sanh_duong_bo_diem_nong",
                          "Đường bờ 2026 của ba nguồn và bản QGIS quanh transect lùi mạnh nhất (" + str(worst["transect_code"]) + ")",
                          [*shore.values(), lrr["main"], img], [*shore.values(), lrr["main"]], extent=ext,
                          subtitle="Chênh lệch giữa các đường bờ phản ánh độ bất định định vị/phân loại, cỡ 1–3 pixel Sentinel-2; "
                                   "đường QGIS (hồng) tự giải đoán lại từ ảnh bản chính, lệch trung vị ~3 m so với bản Python")
    maps.append(detail)
    Q.FIG.mkdir(parents=True, exist_ok=True); PROJECT.parent.mkdir(parents=True, exist_ok=True)
    for lay in maps:
        ex = QgsLayoutExporter(lay)
        s = QgsLayoutExporter.ImageExportSettings(); s.dpi = 200
        p = QgsLayoutExporter.PdfExportSettings(); p.dpi = 200
        for res, ext_ in ((ex.exportToImage(str(Q.FIG / f"{lay.name()}.png"), s), "png"),
                          (ex.exportToPdf(str(Q.FIG / f"{lay.name()}.pdf"), p), "pdf")):
            print(f"{lay.name()}.{ext_}: {'OK' if res == QgsLayoutExporter.Success else f'LỖI {res}'}")
    project.write(str(PROJECT)); print("Project:", PROJECT)
    app.exitQgis()


if __name__ == "__main__":
    main()
