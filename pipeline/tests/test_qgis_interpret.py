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
