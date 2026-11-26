"""s7_evaluate: sai số của đường bờ CHÍNH và việc loại quan sát bị despike khỏi U_classify. Dữ liệu tổng hợp, không cần ảnh."""
import json

import pytest

pytest.importorskip("rioxarray")
np = pytest.importorskip("numpy")
pytest.importorskip("geopandas")
from shapely.geometry import LineString, box  # noqa: E402

import config as C  # noqa: E402
import s7_evaluate as E  # noqa: E402


def _transects(n):
    # transect nằm ngang, gốc ở x=0, cách nhau 50 m theo y
    return [LineString([(0, 50 * i), (600, 50 * i)]) for i in range(n)]


def _vline(x, n):
    return LineString([(x, -10), (x, 50 * n)])


ZONE = box(-10, -10, 700, 1000)


def test_err_stats_known_values_and_empty():
    s = E._err_stats([3.0, -3.0, 4.0, -4.0])
    assert s == {"n_transects": 4, "mean_m": 0.0, "mean_abs_m": 3.5, "rmse_m": 3.54}
    assert E._err_stats([]) is None


def test_main_error_all_vs_kept_after_despike():
    n = 6
    codes = [f"T{i}" for i in range(n)]
    ref = _vline(300, n)
    # đường bờ chính lệch +10 m, riêng T4/T5 cắt nhầm bờ khác (lệch −250 m) và bị despike loại
    auto = LineString([(310, -10), (310, 50 * 4 - 10)]).union(LineString([(50, 190), (50, 50 * n)]))
    kept = {"T0", "T1", "T2", "T3"}
    r = E.main_shoreline_error(auto, ref, _transects(n), codes, ZONE, kept)

    assert r["all"]["n_transects"] == n
    assert r["kept"]["n_transects"] == 4
    assert r["kept"]["rmse_m"] == pytest.approx(10.0)          # chỉ phần còn lại sau despike
    assert r["all"]["rmse_m"] > 100                            # gồm cả hai quan sát cắt nhầm
    assert r["n_excluded_by_despike"] == 2


def test_main_error_without_kept_set_reports_only_all():
    n = 3
    r = E.main_shoreline_error(_vline(310, n), _vline(300, n), _transects(n), ["a", "b", "c"], ZONE, None)
    assert set(r) == {"all"} and r["all"]["mean_m"] == pytest.approx(10.0)


def test_main_error_ignores_transects_outside_zone_and_handles_missing_reference():
    n = 3
    far = box(5000, 5000, 6000, 6000)
    assert E.main_shoreline_error(_vline(310, n), _vline(300, n), _transects(n), list("abc"), far, None) is None
    assert E.main_shoreline_error(_vline(310, n), None, _transects(n), list("abc"), ZONE, None) is None
    assert E.main_shoreline_error(None, _vline(300, n), _transects(n), list("abc"), ZONE, None) is None


def test_kept_after_despike_reads_non_null_years_only(tmp_path, monkeypatch):
    feats = [{"properties": {"transect_code": "A", "distances": {"2018": 100.0, "2019": None}}},
             {"properties": {"transect_code": "B", "distances": {"2018": None, "2019": 90.0}}},
             {"properties": {"transect_code": "C", "distances": None}}]
    (tmp_path / "transects.json").write_text(json.dumps({"features": feats}), encoding="utf-8")
    monkeypatch.setattr(C, "OUTPUT", tmp_path)
    assert E.kept_after_despike(2018) == {"A"}
    assert E.kept_after_despike(2019) == {"B"}
    monkeypatch.setattr(C, "OUTPUT", tmp_path / "khong_co")
    assert E.kept_after_despike(2018) is None


def test_evaluation_json_u_classify_matches_config(output_dir, load_json):
    e = load_json(output_dir / "evaluation.json")
    assert "main_shoreline_error" in next(iter(e["by_year"].values()))
    assert e["u_classify_suggestion"] == {str(y): v for y, v in C.U_CLASSIFY.items()}
    assert "despike" in e["u_classify_basis"]
