"""qgis_interpret: Otsu, LRR sau khử đột biến, khoảng cách transect và so khớp hai đường bờ. Dữ liệu tổng hợp; bỏ qua nếu không có PyQGIS."""
import pytest

np = pytest.importorskip("numpy")
pytest.importorskip("qgis.core")

from qgis.core import QgsGeometry, QgsPointXY  # noqa: E402

import qgis_interpret as Q  # noqa: E402


def _line(*pts):
    return QgsGeometry.fromPolylineXY([QgsPointXY(*p) for p in pts])


def test_otsu_tach_hai_dinh_histogram():
    v = np.linspace(-1, 1, 200)
    c = np.where(v < 0, np.exp(-((v + 0.5) / 0.1) ** 2), np.exp(-((v - 0.5) / 0.1) ** 2)) * 100
    assert -0.3 < Q.otsu(v, c) < 0.3


def test_lrr_hoi_quy_tuyen_tinh():
    assert Q.lrr({str(2018 + i): 100 + 2 * i for i in range(9)}) == pytest.approx(2.0)


def test_lrr_bo_diem_dot_bien_lech_hon_50m():
    d = {str(2018 + i): 100.0 for i in range(9)}
    d["2022"] = 400.0
    assert Q.lrr(d) == pytest.approx(0.0, abs=1e-9)


def test_lrr_it_hon_3_moc_la_none():
    assert Q.lrr({"2018": 1.0, "2019": 2.0, "2020": None}) is None
    assert Q.lrr({}) is None


def test_transect_distance_lay_giao_diem_gan_goc_nhat():
    tr = _line((0, 0), (100, 0))
    shore = QgsGeometry.unaryUnion([_line((30, -5), (30, 5)), _line((70, -5), (70, 5))])
    assert Q.transect_distance(tr, shore) == pytest.approx(30.0)


def test_transect_distance_khong_giao_la_none():
    assert Q.transect_distance(_line((0, 0), (100, 0)), _line((0, 10), (100, 10))) is None


def test_line_distance_stats_hai_duong_trung_nhau():
    g = _line((0, 0), (500, 0))
    s = Q.line_distance_stats(g, g)
    assert s["hausdorff_m"] == 0 and s["within_1px_pct"] == 100.0


def test_line_distance_stats_lech_song_song_5m():
    s = Q.line_distance_stats(_line((0, 0), (500, 0)), _line((0, 5), (500, 5)))
    assert s["qgis_to_python"]["median_m"] == pytest.approx(5.0)
    assert s["within_1px_pct"] == 100.0


@pytest.fixture(scope="module")
def qgis_processing():
    """Khởi tạo QGIS + Processing một lần cho các test gọi thuật toán Processing."""
    import os, sys
    os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")
    from qgis.core import QgsApplication
    QgsApplication.setPrefixPath("/usr", True)
    app = QgsApplication([], False)
    app.initQgis()
    sys.path.append("/usr/share/qgis/python/plugins")
    from processing.core.Processing import Processing
    Processing.initialize()
    yield
    # không gọi exitQgis(): PyQGIS 3.40 báo segfault khi dọn lớp tạm


def _composite(path, nodata_blocks, size=60):
    gdal = pytest.importorskip("osgeo.gdal")
    arr = np.full((size, size), 0.2, np.float32)
    for r0, r1, c0, c1 in nodata_blocks:
        arr[r0:r1, c0:c1] = np.nan
    ds = gdal.GetDriverByName("GTiff").Create(str(path), size, size, 1, gdal.GDT_Float32)
    ds.SetGeoTransform((500000, 10, 0, 1000000, 0, -10))
    ds.SetProjection('PROJCS["WGS 84 / UTM zone 48N",GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],'
                     'PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]],PROJECTION["Transverse_Mercator"],'
                     'PARAMETER["latitude_of_origin",0],PARAMETER["central_meridian",105],PARAMETER["scale_factor",0.9996],'
                     'PARAMETER["false_easting",500000],PARAMETER["false_northing",0],UNIT["metre",1],AUTHORITY["EPSG","32648"]]')
    ds.GetRasterBand(1).WriteArray(arr)
    ds.FlushCache()
    return path


def test_big_gaps_none_khi_anh_khong_thieu_du_lieu(qgis_processing, tmp_path):
    assert Q.big_gaps(_composite(tmp_path / "a.tif", []), tmp_path, 2000) is None


def test_big_gaps_bo_cum_nho_giu_cum_lon(qgis_processing, tmp_path):
    # cụm 2×2 = 4 px (< 25 px) bị bỏ; cụm 10×10 = 100 px (≥ 25 px) được giữ và đệm 2 pixel (20 m)
    comp = _composite(tmp_path / "b.tif", [(5, 7, 5, 7), (30, 40, 30, 40)])
    gaps = Q.big_gaps(comp, tmp_path, 2001)
    assert gaps is not None and gaps.featureCount() == 1
    g = next(gaps.getFeatures()).geometry()
    assert g.area() > 100 * 100 and g.area() < (100 + 40) ** 2           # 100 px = 10 000 m², cộng đệm 20 m mỗi phía
    assert g.contains(QgsGeometry.fromPointXY(QgsPointXY(500000 + 35 * 10, 1000000 - 35 * 10)))
    assert not g.intersects(QgsGeometry.fromPointXY(QgsPointXY(500000 + 6 * 10, 1000000 - 6 * 10)))
