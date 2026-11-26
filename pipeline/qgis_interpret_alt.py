"""Giải đoán hai bản data đối chứng bằng ĐÚNG chuỗi QGIS của bản chính (qgis_interpret.interpret_year), để so sánh cùng phương pháp.

Chạy bằng Python HỆ THỐNG có PyQGIS:  python3 qgis_interpret_alt.py {s2c1|landsat} [năm ...]
Đầu vào : data/alt/<nguồn>/composite/<năm>.tif (tạo bởi alt_data.py).
Đầu ra  : data/alt/<nguồn>/qgis_shoreline/<năm>.json (đường bờ QGIS, EPSG:32648) và data/alt/<nguồn>/qgis_thresholds.json.
Vì sao cần: bản chính nay lấy đường bờ từ QGIS còn bản đối chứng do alt_data.py tách bằng code Python, nên chênh lệch giữa chúng gộp cả
nguồn ảnh lẫn phương pháp. Cùng chuỗi QGIS cho cả ba nguồn thì phần chênh còn lại chủ yếu do nguồn ảnh.
Ngưỡng theo pixel của chuỗi (SIEVE_PX = 500 px = 5 ha ở 10 m) được quy đổi theo độ phân giải của nguồn; bán kính mở hình thái học (50 m)
và diện tích lấp lỗ (5 ha) tính theo mét nên giữ nguyên.
"""
import json
import os
import sys

import qgis_interpret as Q

ALT = Q.DATA / "alt"
RES = {"s2c1": 10, "landsat": 30}


def main(name, years):
    if name not in RES:
        raise SystemExit(f"Dùng: python3 qgis_interpret_alt.py {{{'|'.join(RES)}}} [năm ...]")
    root = ALT / name
    # interpret_year đọc DATA/composite/<năm>.tif và ghi trung gian vào OUT/work: trỏ sang thư mục của nguồn đối chứng
    Q.DATA, Q.OUT = root, root / "qgis"
    Q.SIEVE_PX = max(1, round(500 * (10 / RES[name]) ** 2))
    (root / "qgis_shoreline").mkdir(parents=True, exist_ok=True)

    app = Q.QgsApplication([], False)
    Q.QgsApplication.setPrefixPath("/usr", True)
    app.initQgis()
    sys.path.append("/usr/share/qgis/python/plugins")
    from processing.core.Processing import Processing
    Processing.initialize()
    from qgis.analysis import QgsNativeAlgorithms
    Q.QgsApplication.processingRegistry().addProvider(QgsNativeAlgorithms())

    commune = Q.QgsVectorLayer(str(Q.COMMUNE), "commune", "ogr")
    reproj = Q.run("native:reprojectlayer", {"INPUT": commune, "TARGET_CRS": Q.CRS, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    zone_buf = Q.run("native:buffer", {"INPUT": reproj, "DISTANCE": Q.ANALYSIS_BUFFER, "DISSOLVE": True, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]
    zone_bank = Q.run("native:buffer", {"INPUT": reproj, "DISTANCE": Q.BANK_ZONE, "DISSOLVE": True, "OUTPUT": "TEMPORARY_OUTPUT"})["OUTPUT"]

    from qgis.core import QgsFeature, QgsVectorFileWriter, QgsVectorLayer
    thr_f = root / "qgis_thresholds.json"
    thr_all = json.loads(thr_f.read_text()) if thr_f.exists() else {}
    for y in years:
        if not (root / "composite" / f"{y}.tif").exists():
            print(f"{name} {y}: bỏ qua (không có composite)")
            continue
        qline, thr, area = Q.interpret_year(y, zone_buf, zone_bank)
        out = QgsVectorLayer(f"MultiLineString?crs={Q.CRS}&field=year:integer&field=source:string", "o", "memory")
        ft = QgsFeature(out.fields()); ft.setGeometry(qline); ft.setAttributes([y, f"qgis_{name}"])
        out.dataProvider().addFeature(ft)
        tmp = root / "qgis_shoreline" / f"{y}.geojson"
        QgsVectorFileWriter.writeAsVectorFormat(out, str(tmp), "utf-8", out.crs(), "GeoJSON")
        os.replace(tmp, root / "qgis_shoreline" / f"{y}.json")
        thr_all[str(y)] = {"mndwi": round(thr, 4), "water_area_km2": round(area, 2), "sieve_px": Q.SIEVE_PX}
        thr_f.write_text(json.dumps(thr_all, indent=1))
        print(f"{name} {y}: Otsu {thr:.4f}, nước {area:.2f} km², bờ {qline.length() / 1000:.2f} km", flush=True)
    os._exit(0)   # như qgis_interpret.py: exitQgis() báo segfault khi dọn lớp tạm; mọi tệp đã ghi xong


if __name__ == "__main__":
    if len(sys.argv) < 2:
        raise SystemExit(f"Dùng: python3 qgis_interpret_alt.py {{{'|'.join(RES)}}} [năm ...]")
    main(sys.argv[1], [int(a) for a in sys.argv[2:]] or Q.YEARS)
