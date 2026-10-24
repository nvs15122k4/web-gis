"""Thành lập bản đồ chuyên đề bằng QGIS cho báo cáo (Chương 3) và project QGIS tổng hợp mọi lớp kết quả.

Chạy bằng Python HỆ THỐNG có PyQGIS (không phải .venv), sau run.py và s8_crosscheck.py:  python3 qgis_maps.py
Đầu ra: data/ban_do/ket_qua_song_hau.qgz (mở bằng `qgis`), ../docs/ban_do/<tên>.png (300 dpi) và .pdf.
  1. vi_tri            — vị trí khu vực nghiên cứu trên composite Sentinel-2 2026
  2. duong_bo          — đường bờ 2018–2026
  3. phan_loai         — transect theo mức biến động (Mục 2.5a), điểm nóng, vùng sạt lở tham chiếu
  4. duong_bo_chi_tiet — phóng to điểm nóng mạnh nhất: đường bờ 2018 và 2026 cùng transect
  5. doi_chieu_2021    — đường bờ pipeline 2021 so với đường bờ suy từ ESA WorldCover 2021 (bước 8)
"""
import os
from pathlib import Path

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")

from qgis.core import (  # noqa: E402
    Qgis, QgsApplication, QgsCategorizedSymbolRenderer, QgsCoordinateReferenceSystem, QgsFillSymbol,
    QgsLayoutExporter, QgsLayoutItemLabel, QgsLayoutItemLegend, QgsLayoutItemMap, QgsLayoutItemMapGrid,
    QgsLayoutItemPicture, QgsLayoutItemScaleBar, QgsLayoutPoint, QgsLayoutSize, QgsLineSymbol, QgsMultiBandColorRenderer,
    QgsPrintLayout, QgsProject, QgsRasterLayer, QgsRectangle, QgsRendererCategory, QgsUnitTypes, QgsVectorLayer,
    QgsContrastEnhancement, QgsCoordinateTransform, QgsTextFormat,
)
from qgis.PyQt.QtGui import QColor, QFont  # noqa: E402

ROOT = Path(__file__).parent
DATA, OUTPUT = ROOT / "data", ROOT / "output"
PROJECT = DATA / "ban_do" / "ket_qua_song_hau.qgz"
FIG = ROOT.parent / "docs" / "ban_do"
YEARS = list(range(2018, 2027))
CRS = QgsCoordinateReferenceSystem("EPSG:32648")
STUDY = QgsRectangle(528300, 1157300, 538900, 1167900)     # đoạn bờ sông Hậu qua Bình Mỹ (UTM 48N)
CLASSES = [  # cùng màu với web/resources/js/colors.json
    ("severe_erosion", "Lùi bờ mạnh", "#d0312d"), ("mild_erosion", "Lùi bờ nhẹ", "#b8862b"),
    ("stable", "Ổn định", "#8a8a8a"), ("accretion", "Bồi tụ", "#2e9d4f"),
]
YEAR_COLORS = ["#fff5a0", "#fde27a", "#fcc45b", "#f9a243", "#f37f35", "#e45a31", "#cb3a35", "#a8213c", "#7c1240"]
SOURCE = ("Nguồn: Sentinel-2 L2A © Copernicus/ESA (Microsoft Planetary Computer); ranh giới xã © OpenStreetMap; "
          "ESA WorldCover © ESA 2021. Hệ toạ độ WGS 84 / UTM 48N (EPSG:32648). Thành lập bằng QGIS {v}.")


def vector(path, name, layer=None):
    uri = f"{path}|layername={layer}" if layer else str(path)
    lyr = QgsVectorLayer(uri, name, "ogr")
    if not lyr.isValid():
        raise SystemExit(f"Không mở được {path}")
    return lyr


def composite(year):
    lyr = QgsRasterLayer(str(DATA / "composite" / f"{year}.tif"), f"Sentinel-2 mùa khô {year} (SWIR1-NIR-Green)")
    r = QgsMultiBandColorRenderer(lyr.dataProvider(), 3, 2, 1)
    for band, setter in ((3, r.setRedContrastEnhancement), (2, r.setGreenContrastEnhancement),
                         (1, r.setBlueContrastEnhancement)):
        ce = QgsContrastEnhancement(lyr.dataProvider().dataType(band))
        ce.setContrastEnhancementAlgorithm(QgsContrastEnhancement.StretchToMinimumMaximum)
        ce.setMinimumValue(0.0); ce.setMaximumValue(0.35)
        setter(ce)
    lyr.setRenderer(r)
    return lyr


def line(color, width, style="solid"):
    return QgsLineSymbol.createSimple({"line_color": color, "line_width": str(width), "line_style": style})


def build_layers():
    L = {"img": composite(2026), "img2021": composite(2021)}
    aoi = vector(OUTPUT / "aoi.json", "Ranh giới xã Bình Mỹ")
    aoi.setSubsetString("\"role\" = 'commune'")
    aoi.renderer().setSymbol(QgsFillSymbol.createSimple(
        {"color": "0,0,0,0", "outline_color": "255,255,0", "outline_width": "0.6", "outline_style": "dash"}))
    L["xa"] = aoi

    sl = vector(OUTPUT / "shorelines.json", "Đường bờ theo năm")
    sl.setRenderer(QgsCategorizedSymbolRenderer("year", [
        QgsRendererCategory(y, line(c, 0.5), str(y)) for y, c in zip(YEARS, YEAR_COLORS)]))
    L["bo"] = sl

    tr = vector(OUTPUT / "transects.json", "Transect theo mức biến động")
    tr.setRenderer(QgsCategorizedSymbolRenderer("map_get(\"stats\", 'change_class')", [
        QgsRendererCategory(k, line(c, 0.8), lab) for k, lab, c in CLASSES]))
    L["tr"] = tr

    hs = vector(OUTPUT / "hotspots.json", "Điểm nóng xói lở")
    hs.renderer().setSymbol(QgsFillSymbol.createSimple(
        {"color": "208,49,45,90", "outline_color": "255,255,255", "outline_width": "0.5"}))
    L["hs"] = hs

    ref = vector(OUTPUT / "reference.json", "Vùng sạt lở tham chiếu 2019–2020")
    sym = ref.renderer().symbol()
    if ref.geometryType() == 1:  # đường
        ref.renderer().setSymbol(line("#ff00ff", 1.6))
    else:
        sym.setColor(QColor(255, 0, 255, 70))
    L["ref"] = ref

    auto = vector(OUTPUT / "shorelines.json", "Đường bờ pipeline 2021 (MNDWI)")
    auto.setSubsetString("\"year\" = 2021")
    auto.renderer().setSymbol(line("#00e5ff", 0.6))
    L["auto21"] = auto
    wc = vector(DATA / "crosscheck" / "worldcover_2021.json", "Đường bờ ESA WorldCover 2021")
    wc.renderer().setSymbol(line("#ff3b30", 0.6, "dash"))
    L["wc21"] = wc
    return L


def add_layout(project, name, title, layers, legend_layers, extent=STUDY, subtitle="", height=176):
    lay = QgsPrintLayout(project)
    lay.initializeDefaults()  # A4 ngang
    lay.setName(name)
    page = lay.pageCollection().page(0)
    page.setPageSize("A4", page.Landscape)

    def label(text, x, y, w, h, size, bold=False):
        it = QgsLayoutItemLabel(lay)
        it.setText(text)
        fmt = QgsTextFormat(); f = QFont("Noto Sans", size); f.setBold(bold)
        fmt.setFont(f); fmt.setSize(size)
        it.setTextFormat(fmt)
        it.attemptMove(QgsLayoutPoint(x, y)); it.attemptResize(QgsLayoutSize(w, h))
        lay.addLayoutItem(it)

    label(title, 8, 5, 281, 9, 15, bold=True)
    if subtitle:
        label(subtitle, 8, 13, 281, 6, 9)

    m = QgsLayoutItemMap(lay)
    m.attemptMove(QgsLayoutPoint(8, 25)); m.attemptResize(QgsLayoutSize(200, height))
    m.setCrs(CRS); m.setLayers(layers); m.setFrameEnabled(True)
    small = extent.width() < 3000  # bản đồ chi tiết
    m.zoomToExtent(extent)
    grid = QgsLayoutItemMapGrid("Lưới", m)
    step = 200 if small else 2000
    grid.setIntervalX(step); grid.setIntervalY(step)
    grid.setStyle(QgsLayoutItemMapGrid.Cross); grid.setCrossLength(2)
    grid.setAnnotationEnabled(True); grid.setAnnotationPrecision(0)
    gfmt = QgsTextFormat(); gfmt.setFont(QFont("Noto Sans")); gfmt.setSize(6)
    grid.setAnnotationTextFormat(gfmt)
    grid.setAnnotationDirection(QgsLayoutItemMapGrid.Vertical, QgsLayoutItemMapGrid.Left)
    grid.setAnnotationDirection(QgsLayoutItemMapGrid.Vertical, QgsLayoutItemMapGrid.Right)
    m.grids().addGrid(grid)
    lay.addLayoutItem(m)

    lg = QgsLayoutItemLegend(lay)
    lg.setTitle("Chú giải")
    lg.setLinkedMap(m)
    lg.setAutoUpdateModel(False)
    keep = {lyr.id() for lyr in legend_layers}
    tree = lg.model().rootGroup()
    for node in tree.findLayers():
        if node.layerId() not in keep:
            node.parent().removeChildNode(node)
    lg.attemptMove(QgsLayoutPoint(214, 25)); lg.attemptResize(QgsLayoutSize(75, 120))
    lay.addLayoutItem(lg)

    sb = QgsLayoutItemScaleBar(lay)
    sb.setStyle("Single Box"); sb.setLinkedMap(m)
    if small:
        sb.setUnits(QgsUnitTypes.DistanceMeters); sb.setUnitLabel("m"); sb.setUnitsPerSegment(100)
    else:
        sb.setUnits(QgsUnitTypes.DistanceKilometers); sb.setUnitLabel("km"); sb.setUnitsPerSegment(1)
    sb.setNumberOfSegments(2); sb.setNumberOfSegmentsLeft(0)
    sb.attemptMove(QgsLayoutPoint(214, 160))
    lay.addLayoutItem(sb)

    na = QgsLayoutItemPicture(lay)
    na.setPicturePath(QgsApplication.pkgDataPath() + "/svg/arrows/NorthArrow_02.svg")
    na.setLinkedMap(m); na.setNorthMode(QgsLayoutItemPicture.GridNorth)
    na.attemptMove(QgsLayoutPoint(270, 150)); na.attemptResize(QgsLayoutSize(14, 20))
    lay.addLayoutItem(na)

    label(SOURCE.format(v=Qgis.version().split("-")[0]), 214, 178, 75, 22, 6)
    project.layoutManager().addLayout(lay)
    return lay


def main():
    QgsApplication.setPrefixPath("/usr", True)
    app = QgsApplication([], False)
    app.initQgis()
    project = QgsProject.instance()
    project.setCrs(CRS)
    project.setTitle("Kết quả giám sát biến động bờ sông Hậu — xã Bình Mỹ, An Giang")
    L = build_layers()
    order = ["wc21", "auto21", "ref", "hs", "tr", "bo", "xa", "img", "img2021"]
    for k in order:
        project.addMapLayer(L[k])
    root = project.layerTreeRoot()
    for k in ("wc21", "auto21", "img2021"):
        root.findLayer(L[k].id()).setItemVisibilityChecked(False)

    worst = max(L["hs"].getFeatures(), key=lambda f: f["severity"])
    hot = QgsCoordinateTransform(L["hs"].crs(), CRS, project) \
        .transformBoundingBox(worst.geometry().boundingBox()).buffered(450)
    ends = vector(OUTPUT / "shorelines.json", "Đường bờ 2018 và 2026")
    ends.setSubsetString("\"year\" IN (2018, 2026)")
    ends.setRenderer(QgsCategorizedSymbolRenderer("year", [
        QgsRendererCategory(2018, line("#ffeb3b", 0.7), "2018"), QgsRendererCategory(2026, line("#ff1744", 0.7), "2026")]))
    project.addMapLayer(ends)
    root.findLayer(ends.id()).setItemVisibilityChecked(False)

    sub_cls = "Phân loại theo Mục 2.5a (LRR, độ bất định, R²). U_classify đang dùng giá trị tạm 10 m."
    maps = [
        add_layout(project, "vi_tri", "Bản đồ vị trí khu vực nghiên cứu — xã Bình Mỹ, tỉnh An Giang",
                   [L["xa"], L["img"]], [L["xa"]],
                   extent=L["img"].extent(), height=200 * L["img"].extent().height() / L["img"].extent().width(),
                   subtitle="Nền: ảnh tổng hợp trung vị Sentinel-2 mùa khô (01–03/2026)"),
        add_layout(project, "duong_bo", "Bản đồ đường bờ sông Hậu giai đoạn 2018–2026",
                   [L["bo"], L["xa"], L["img"]], [L["bo"], L["xa"]],
                   subtitle="Đường bờ trích xuất tự động từ MNDWI + ngưỡng Otsu, ảnh mùa khô từng năm"),
        add_layout(project, "phan_loai", "Bản đồ phân loại mức biến động bờ sông Hậu 2018–2026",
                   [L["hs"], L["ref"], L["tr"], L["xa"], L["img"]], [L["tr"], L["hs"], L["ref"], L["xa"]],
                   subtitle=sub_cls),
        add_layout(project, "duong_bo_chi_tiet", "Biến động đường bờ tại điểm nóng xói lở mạnh nhất (2018 → 2026)",
                   [ends, L["hs"], L["tr"], L["img"]], [ends, L["tr"], L["hs"]], extent=hot,
                   subtitle=f"{worst['notes']}; nền Sentinel-2 2026"),
        add_layout(project, "doi_chieu_2021", "Đối chiếu đường bờ 2021 với sản phẩm độc lập ESA WorldCover",
                   [L["wc21"], L["auto21"], L["xa"], L["img2021"]], [L["auto21"], L["wc21"], L["xa"]],
                   subtitle="Đối chiếu chéo (bước 8) — không thay thế ground truth số hoá thủ công (Mục 2.4)"),
    ]
    FIG.mkdir(parents=True, exist_ok=True)
    PROJECT.parent.mkdir(parents=True, exist_ok=True)
    for lay in maps:
        ex = QgsLayoutExporter(lay)
        img = QgsLayoutExporter.ImageExportSettings(); img.dpi = 300
        pdf = QgsLayoutExporter.PdfExportSettings(); pdf.dpi = 300
        for res, path in ((ex.exportToImage(str(FIG / f"{lay.name()}.png"), img), "png"),
                          (ex.exportToPdf(str(FIG / f"{lay.name()}.pdf"), pdf), "pdf")):
            print(f"{lay.name()}.{path}: {'OK' if res == QgsLayoutExporter.Success else f'LỖI {res}'}")
    project.write(str(PROJECT))
    print(f"Project: {PROJECT}")
    app.exitQgis()


if __name__ == "__main__":
    main()
