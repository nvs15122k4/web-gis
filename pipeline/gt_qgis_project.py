"""Tạo project QGIS sẵn sàng số hoá ground truth: data/ground_truth/so_hoa_ground_truth.qgz

Chạy bằng Python HỆ THỐNG có PyQGIS (không phải .venv):  python3 gt_qgis_project.py
Cần cài trước:  sudo apt install qgis python3-qgis   và đã chạy `.venv/bin/python gt_kit.py`.
"""
import os
from pathlib import Path

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")

from qgis.core import (  # noqa: E402
    QgsApplication, QgsCoordinateReferenceSystem, QgsDefaultValue, QgsEditorWidgetSetup, QgsFillSymbol, QgsProject,
    QgsPalLayerSettings, QgsRasterLayer, QgsSnappingConfig, QgsTextBufferSettings, QgsTextFormat, QgsTolerance,
    QgsVectorLayer, QgsVectorLayerSimpleLabeling,
)
from qgis.PyQt.QtGui import QColor  # noqa: E402

GT = Path(__file__).parent / "data" / "ground_truth"
GPKG = GT / "ground_truth.gpkg"
YEARS = [2018, 2022, 2026]
ESRI = ("type=xyz&url=https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/"
        "%7Bz%7D/%7By%7D/%7Bx%7D&zmax=19")


def main():
    app = QgsApplication([], False)
    app.initQgis()
    project = QgsProject.instance()
    project.setCrs(QgsCoordinateReferenceSystem("EPSG:32648"))
    project.setTitle("Số hoá ground truth — sông Hậu, Bình Mỹ")
    root = project.layerTreeRoot()

    win = QgsVectorLayer(f"{GPKG}|layername=windows", "Ô mẫu (không sửa)", "ogr")
    win.renderer().setSymbol(QgsFillSymbol.createSimple(
        {"color": "0,0,0,0", "outline_color": "255,230,0", "outline_width": "0.8"}))
    label = QgsPalLayerSettings()
    label.fieldName = "name || ' — ' || kind"
    label.isExpression = True
    fmt = QgsTextFormat()
    fmt.setColor(QColor(255, 230, 0))
    fmt.setSize(11)
    buf = QgsTextBufferSettings()
    buf.setEnabled(True)
    buf.setColor(QColor(0, 0, 0))
    fmt.setBuffer(buf)
    label.setFormat(fmt)
    win.setLabeling(QgsVectorLayerSimpleLabeling(label))
    win.setLabelsEnabled(True)
    win.setReadOnly(True)
    project.addMapLayer(win, False)
    root.addLayer(win)

    for y in sorted(YEARS, reverse=True):
        group = root.addGroup(f"Năm {y}")
        water = QgsVectorLayer(f"{GPKG}|layername=water_{y}", f"water_{y} — SỐ HOÁ VÀO ĐÂY", "ogr")
        water.renderer().setSymbol(QgsFillSymbol.createSimple(
            {"color": "0,190,255,70", "outline_color": "0,220,255", "outline_width": "0.5"}))
        idx = water.fields().indexOf("loai")
        water.setEditorWidgetSetup(idx, QgsEditorWidgetSetup(
            "ValueMap", {"map": [{"song — lòng sông Hậu": "song"}, {"khac — ao, kênh, ruộng ngập": "khac"}]}))
        water.setDefaultValueDefinition(idx, QgsDefaultValue("'song'"))
        project.addMapLayer(water, False)
        group.addLayer(water)
        img = QgsRasterLayer(str(GT / "img" / f"{y}_swir_nir_green.tif"), f"Sentinel-2 {y} (SWIR1-NIR-Green)")
        project.addMapLayer(img, False)
        group.addLayer(img)
        group.setItemVisibilityChecked(y == max(YEARS))

    ref = QgsRasterLayer(ESRI, "Tham khảo: ảnh Esri độ phân giải cao (ảnh GẦN ĐÂY, không đúng năm)", "wms")
    if ref.isValid():
        project.addMapLayer(ref, False)
        root.addLayer(ref).setItemVisibilityChecked(False)

    snap = QgsSnappingConfig(project)
    snap.setEnabled(True)
    snap.setMode(QgsSnappingConfig.SnappingMode.AllLayers)
    snap.setTypeFlag(QgsSnappingConfig.SnappingTypes.VertexFlag | QgsSnappingConfig.SnappingTypes.SegmentFlag)
    snap.setTolerance(8)
    snap.setUnits(QgsTolerance.UnitType.Pixels)
    project.setSnappingConfig(snap)
    project.setAvoidIntersectionsMode(QgsProject.AvoidIntersectionsMode.AllowIntersections)

    out = GT / "so_hoa_ground_truth.qgz"
    project.write(str(out))
    print(f"-> {out}")
    app.exitQgis()


if __name__ == "__main__":
    main()
